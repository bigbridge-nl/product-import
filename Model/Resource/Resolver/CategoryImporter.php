<?php

namespace BigBridge\ProductImport\Model\Resource\Resolver;

use BigBridge\ProductImport\Api\ImportConfig;
use BigBridge\ProductImport\Model\Data\CategoryInfo;
use BigBridge\ProductImport\Model\Data\EavAttributeInfo;
use BigBridge\ProductImport\Model\Persistence\Magento2DbConnection;
use BigBridge\ProductImport\Model\Resource\MetaData;
use Magento\Catalog\Model\Category;

/**
 * @author Patrick van Bergen
 */
class CategoryImporter
{
    /** @var string Internal category path separator, i.e. 1/2/18/125 */
    const CATEGORY_ID_PATH_SEPARATOR = '/';

    /**  @var Magento2DbConnection */
    protected $db;

    /** @var MetaData */
    protected $metaData;

    /** @var NameToUrlKeyConverter */
    protected $nameToUrlKeyConverter;

    /** @var array A category-path =>  category-id map */
    protected $categoryCache = [];

    /** @var CategoryInfo[] A category-id => CategoryInfo map */
    protected $allCategoryInfo = null;

    public function __construct(Magento2DbConnection $db, MetaData $metaData, NameToUrlKeyConverter $nameToUrlKeyConverter)
    {
        $this->db = $db;
        $this->metaData = $metaData;
        $this->nameToUrlKeyConverter = $nameToUrlKeyConverter;
    }

    public function clearCache()
    {
        $this->allCategoryInfo = null;
    }

    /**
     * @return CategoryInfo[]
     */
    protected function loadCategoryInfo()
    {
        if ($this->allCategoryInfo === null) {

            $urlKeyAttributeId = $this->metaData->categoryAttributeMap['url_key'];

            $categoryData = $this->db->fetchAllAssoc("
                SELECT E.`entity_id`, E.`path`, URL_KEY.`value` as url_key, URL_KEY.`store_id`
                FROM `{$this->metaData->categoryEntityTable}` E
                LEFT JOIN `{$this->metaData->categoryEntityTable}_varchar` URL_KEY ON URL_KEY.`entity_id` = E.`entity_id` 
                    AND URL_KEY.`attribute_id` = ? 
            ", [
                $urlKeyAttributeId
            ]);

            /** @var CategoryInfo[] $categories */
            $categories = [];

            foreach ($categoryData as $categoryDatum) {

                $categoryId = $categoryDatum['entity_id'];
                $storeId = (int)$categoryDatum['store_id'];
                $urlKey = (string)$categoryDatum['url_key'];

                if (array_key_exists($categoryId, $categories)) {

                    $categories[$categoryId]->urlKeys[$storeId] = $urlKey;

                } else {

                    $categories[$categoryId] = new CategoryInfo(
                        explode('/', $categoryDatum['path']),
                        [$storeId => $urlKey]
                    );

                }
            }

            $this->allCategoryInfo = $categories;
        }

        return $this->allCategoryInfo;
    }

    /**
     * @param $categoryId
     * @return CategoryInfo|null
     */
    public function getCategoryInfo($categoryId)
    {
        // lazy load categories
        $this->loadCategoryInfo();

        return array_key_exists($categoryId, $this->allCategoryInfo) ? $this->allCategoryInfo[$categoryId] : null;
    }

    /**
     * Return the url_keys of all child categories of the given parent.
     *
     * @param int $parentCategoryId
     * @param int $storeViewId
     * @return array
     */
    protected function getExistingCategoryUrlKeys(int $parentCategoryId, int $storeViewId)
    {
        $urlKeys = [];

        foreach ($this->allCategoryInfo as $categoryInfo) {
            $pathLength = count($categoryInfo->path);
            if ($pathLength > 1) {
                if ($parentCategoryId == $categoryInfo->path[$pathLength - 2]) {
                    if (array_key_exists($storeViewId, $categoryInfo->urlKeys)) {
                        $urlKeys[] = $categoryInfo->urlKeys[$storeViewId];
                    }
                }
            }
        }

        return $urlKeys;
    }

    /**
     * @param int $categoryId
     * @param int[] $idPath The ids of the parent categories, including $categoryId
     * @param array $urlKeys A store-id => url_key array
     */
    protected function addCategoryInfo(int $categoryId, array $idPath, array $urlKeys)
    {
        $this->allCategoryInfo[$categoryId] = new CategoryInfo($idPath, $urlKeys);
    }

    /**
     * Returns the names of the categories.
     * Category names may be paths separated with /
     *
     * @param array $categoryPaths
     * @param bool $autoCreateCategories
     * @param string $categoryNamePathSeparator
     * @param string $categoryUrlType
     * @return array
     */
    public function importCategoryPaths(
        array $categoryPaths,
        bool $autoCreateCategories,
        string $categoryNamePathSeparator,
        string $categoryUrlType)
    {
        // lazy load categories
        $this->loadCategoryInfo();

        $ids = [];
        $error = "";

        foreach ($categoryPaths as $path) {
            if (array_key_exists($path, $this->categoryCache)) {
                $id = $this->categoryCache[$path];
                $ids[] = $id;
            } else {
                list($id, $error) = $this->importCategoryPath($path, $autoCreateCategories, $categoryNamePathSeparator, $categoryUrlType);

                if ($error !== "") {
                    $ids = [];
                    break;
                }

                $this->categoryCache[$path] = $id;
                $ids[] = $id;
            }
        }

        return [$ids, $error];
    }

    /**
     * Creates a path of categories, if necessary, and returns the new id.
     *
     * @param string $namePath A / separated path of category names.
     * @param bool $autoCreateCategories
     * @param string $categoryNamePathSeparator
     * @param string $categoryUrlType
     * @return array
     */
    public function importCategoryPath(string $namePath, bool $autoCreateCategories, string $categoryNamePathSeparator, string $categoryUrlType): array
    {
        // lazy load categories
        $this->loadCategoryInfo();

        $categoryId = Category::TREE_ROOT_ID;
        $error = "";

        $idPath = [$categoryId];

        $categoryNames = explode($categoryNamePathSeparator, $namePath);

        foreach ($categoryNames as $categoryName) {

            $categoryId = $this->getChildCategoryId($categoryId, $categoryName);

            if (is_null($categoryId)) {
                if (!$autoCreateCategories) {
                    $error = "category not found: " . $categoryName;
                    break;
                } else {
                    $categoryId = $this->importChildCategory($idPath, $categoryName, $categoryUrlType);
                }
            }

            $idPath[] = $categoryId;
        }

        return [$categoryId, $error];
    }

    /**
     * @param int $parentId
     * @param string $categoryName
     * @return int|null
     */
    protected function getChildCategoryId(int $parentId, string $categoryName)
    {
        $categoryEntityTable = $this->metaData->categoryEntityTable;
        $nameAttributeId = $this->metaData->categoryAttributeMap['name'];

        $childCategoryId = $this->db->fetchSingleCell("
            SELECT E.`entity_id`
            FROM `{$categoryEntityTable}` E
            INNER JOIN `{$categoryEntityTable}_varchar` A ON A.`entity_id` = E.`entity_id` AND A.`attribute_id` = ? AND A.`store_id` = 0 
            WHERE `parent_id` = ? AND A.`value` = ?
        ", [
            $nameAttributeId,
            $parentId,
            $categoryName
        ]);

        return is_null($childCategoryId) ? null : (int)$childCategoryId;
    }

    /**
     * @param int[] $idPath
     * @param string $categoryName
     * @param string $categoryUrlType
     * @return int
     */
    protected function importChildCategory(array $idPath, string $categoryName, string $categoryUrlType): int
    {
        $categoryEntityTable = $this->metaData->categoryEntityTable;
        $urlRewriteTable = $this->metaData->urlRewriteTable;
        $attributeSetId = $this->metaData->defaultCategoryAttributeSetId;

        $parentLevel = count($idPath) - 1;
        $childLevel = $parentLevel + 1;
        $parentId = $idPath[$parentLevel];
        $parentPath = implode(self::CATEGORY_ID_PATH_SEPARATOR, $idPath);

        // update parent data
        $this->db->execute("
            UPDATE {$categoryEntityTable} 
                SET `children_count` = `children_count` + 1
            WHERE `entity_id` = ?
        ", [
            $parentId
        ]);

        $position = $this->db->fetchSingleCell("
            SELECT MAX(`position`)
            FROM `{$categoryEntityTable}`
            WHERE `path` LIKE ? AND level = ?
        ", [
            "{$parentPath}/%",
            $childLevel
        ]);
        $nextPosition = is_null($position) ? 1 : (int)$position + 1;

        // write child data
        $this->db->execute("
            INSERT INTO `{$categoryEntityTable}`
            SET    
                `attribute_set_id` = ?, 
                `parent_id` = ?, 
                `position` = ?, 
                `level` = ?, 
                `children_count` = 0
        ", [
            $attributeSetId,
            $parentId,
            $nextPosition,
            $childLevel
        ]);

        $categoryId = $this->db->getLastInsertId();

        // add path that contains the new id
        $childPath = $parentPath . self::CATEGORY_ID_PATH_SEPARATOR . $categoryId;

        $this->db->execute("
            UPDATE `{$categoryEntityTable}`
            SET 
                `path` = ?
            WHERE `entity_id` = ?
        ", [
            $childPath,
            $categoryId
        ]);

        if (count($idPath) >= 2) {

            // url
            $existingUrlKeys = $this->getExistingCategoryUrlKeys($parentId, 0);

            $urlKey = $this->nameToUrlKeyConverter->createUniqueUrlKeyFromName($categoryName, $existingUrlKeys);
            if ($categoryUrlType === ImportConfig::CATEGORY_URL_FLAT) {
                $urlPath = $urlKey;
            } else if (count($idPath) === 2) {
                $urlPath = $urlKey;
            } else {
                $parentUrlPath = $this->getParentUrlPath($parentId);
                $urlPath = $parentUrlPath . '/' . $urlKey;
            }
            $targetPath = "catalog/category/view/id/" . $categoryId;

            // url_rewrite
            foreach ($this->metaData->storeViewMap as $storeViewId) {
                $suffix = $this->metaData->categoryUrlSuffixes[$storeViewId];
                $requestPath = $urlPath . $suffix;
                $this->db->execute("
                INSERT INTO `{$urlRewriteTable}`
                SET    
                    `entity_type` = 'category', 
                    `entity_id` = ?,
                    `request_path` = ?, 
                    `target_path` = ?, 
                    `redirect_type` = 0, 
                    `store_id` = ?,
                    `description` = null,
                    `is_autogenerated` = 1,
                    `metadata` = null
            ", [
                    $categoryId,
                    $requestPath,
                    $targetPath,
                    $storeViewId
                ]);
            }

            $this->importEavAttribute($categoryId, 'url_key', $urlKey, EavAttributeInfo::TYPE_VARCHAR, 0);
            $this->importEavAttribute($categoryId, 'url_path', $urlPath, EavAttributeInfo::TYPE_VARCHAR, 0);

        } else {

            $urlKey = null;

        }

        $this->importEavAttribute($categoryId, 'name', $categoryName, EavAttributeInfo::TYPE_VARCHAR, 0);
        $this->importEavAttribute($categoryId, 'display_mode', "PRODUCTS", EavAttributeInfo::TYPE_VARCHAR, 0);
        $this->importEavAttribute($categoryId, 'is_active', 1, EavAttributeInfo::TYPE_INTEGER, 0);
        $this->importEavAttribute($categoryId, 'is_anchor', 1, EavAttributeInfo::TYPE_INTEGER, 0);
        $this->importEavAttribute($categoryId, 'include_in_menu', 1, EavAttributeInfo::TYPE_INTEGER, 0);
        $this->importEavAttribute($categoryId, 'custom_use_parent_settings', 0, EavAttributeInfo::TYPE_INTEGER, 0);
        $this->importEavAttribute($categoryId, 'custom_apply_to_products', 0, EavAttributeInfo::TYPE_INTEGER, 0);

        // !important: add this new category to the metadata collected
        $newIdPath = $idPath;
        $newIdPath[] = $categoryId;
        $this->addCategoryInfo($categoryId, $newIdPath, [0 => $urlKey]);

        return $categoryId;
    }

    protected function getParentUrlPath(int $parentId)
    {
        $attributeId = $this->metaData->categoryAttributeMap['url_path'];

        $urlPath = $this->db->fetchSingleCell("
            SELECT `value`
            FROM `{$this->metaData->categoryEntityTable}_varchar`
            WHERE 
                `entity_id` = ? AND
                `attribute_id` = ? AND
                `store_id` = 0
        ", [
            $parentId,
            $attributeId
        ]);

        return $urlPath;
    }

    protected function importEavAttribute(int $categoryId, string $attributeCode, $value, string $dataType, int $storeId)
    {
        $categoryEntityTable = $this->metaData->categoryEntityTable;

        if (!array_key_exists($attributeCode, $this->metaData->categoryAttributeMap)) {
            die('Category attribute not found: ' . $attributeCode);
        }

        $attributeId = $this->metaData->categoryAttributeMap[$attributeCode];

        $this->db->execute("
            INSERT INTO `{$categoryEntityTable}_{$dataType}`
            SET
                `entity_id` = ?,
                `attribute_id` = ?,
                `store_id` = ?,
                `value` = ?
        ", [
            $categoryId,
            $attributeId,
            $storeId,
            $value
        ]);
    }
}
