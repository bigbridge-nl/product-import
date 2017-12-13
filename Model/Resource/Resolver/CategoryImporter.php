<?php

namespace BigBridge\ProductImport\Model\Resource\Resolver;

use BigBridge\ProductImport\Model\Db\Magento2DbConnection;
use BigBridge\ProductImport\Model\Resource\MetaData;
use Magento\Catalog\Model\Category;

/**
 * @author Patrick van Bergen
 */
class CategoryImporter
{
    const CATEGORY_ID_PATH_SEPARATOR = '/';

    /**  @var Magento2DbConnection */
    protected $db;

    /** @var MetaData */
    protected $metaData;

    /** @var array  */
    protected $categoryCache = [];

    /** @var NameToUrlKeyConverter */
    protected $nameToUrlKeyConverter;

    public function __construct(Magento2DbConnection $db, MetaData $metaData, NameToUrlKeyConverter $nameToUrlKeyConverter)
    {
        $this->db = $db;
        $this->metaData = $metaData;
        $this->nameToUrlKeyConverter = $nameToUrlKeyConverter;
    }

    /**
     * Returns the names of the categories.
     * Category names may be paths separated with /
     *
     * @param array $categoryPaths
     * @param bool $autoCreateCategories
     * @param string $categoryNamePathSeparator
     * @return array
     */
    public function importCategoryPaths(array $categoryPaths, bool $autoCreateCategories, string $categoryNamePathSeparator)
    {
        $ids = [];
        $error = "";

        foreach ($categoryPaths as $path) {
            if (array_key_exists($path, $this->categoryCache)) {
                $id = $this->categoryCache[$path];
                $ids[] = $id;
            } else {
                list($id, $error) = $this->importCategoryPath($path, $autoCreateCategories, $categoryNamePathSeparator);

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
     * @return array
     */
    public function importCategoryPath(string $namePath, bool $autoCreateCategories, string $categoryNamePathSeparator): array
    {
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
                }
            }

            if ($categoryId === null) {
                $categoryId = $this->importChildCategory($idPath, $categoryName);
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
            INNER JOIN `{$categoryEntityTable}_varchar` A ON A.`entity_id` = E.`entity_id` AND A.`attribute_id` = {$nameAttributeId} AND A.`store_id` = 0 
            WHERE `parent_id` = {$parentId} AND A.`value` = " . $this->db->quote($categoryName) . "
        ");

        return is_null($childCategoryId) ? null : (int)$childCategoryId;
    }

    /**
     * @param int[] $idPath
     * @param string $categoryName
     * @return int
     */
    protected function importChildCategory(array $idPath, string $categoryName): int
    {
        $categoryEntityTable = $this->metaData->categoryEntityTable;
        $urlRewriteTable = $this->metaData->urlRewriteTable;
        $attributeSetId = $this->metaData->defaultCategoryAttributeSetId;

        $parentId = $idPath[count($idPath) - 1];
        $parentPath = implode(self::CATEGORY_ID_PATH_SEPARATOR, $idPath);
        $parentLevel = count($idPath);
        $childLevel = $parentLevel + 1;

        // update parent data
        $this->db->execute("
            UPDATE {$categoryEntityTable} 
                SET `children_count` = `children_count` + 1
            WHERE `entity_id` = {$parentId}
        ");

        $position = $this->db->fetchSingleCell("
            SELECT MAX(`position`)
            FROM `{$categoryEntityTable}`
            WHERE `path` LIKE '{$parentPath}/%' AND level = {$parentLevel}
        ");
        $nextPosition = is_null($position) ? 1 : $position + 1;

        // write child data
        $this->db->execute("
            INSERT INTO `{$categoryEntityTable}`
            SET    
                `attribute_set_id` = {$attributeSetId}, 
                `parent_id` = {$parentId}, 
                `position` = {$nextPosition}, 
                `level` = {$childLevel}, 
                `children_count` = 0
        ");

        $categoryId = $this->db->getLastInsertId();

        // add path that contains the new id
        $childPath = $parentPath . self::CATEGORY_ID_PATH_SEPARATOR . $categoryId;

        $this->db->execute("
            UPDATE `{$categoryEntityTable}`
            SET 
                `path` = '{$childPath}'
            WHERE `entity_id` = {$categoryId}
        ");

        // url
        $urlKey = $this->nameToUrlKeyConverter->createUrlKeyFromName($categoryName);
        if (count($idPath) == 1) {
            $urlPath = $urlKey;
        } else {
            $parentUrlPath = $this->getParentUrlPath($parentId);
            $urlPath = $parentUrlPath . '/' . $urlKey;
        }
        $requestPath = $urlPath . $this->metaData->categoryUrlSuffix;
        $targetPath = "catalog/category/view/id/" . $categoryId;

#todo $requestPath moet uniek zijn, evenals url_key

        // url_rewrite
        $this->db->execute("
            INSERT INTO `{$urlRewriteTable}`
            SET    
                `entity_type` = 'category', 
                `entity_id` = {$categoryId},
                `request_path` = '{$requestPath}', 
                `target_path` = '{$targetPath}', 
                `redirect_type` = 0, 
                `store_id` = 0,
                `description` = null,
                `is_autogenerated` = 1,
                `metadata` = null
        ");

        $this->importEavAttribute($categoryId, 'name', $categoryName, MetaData::TYPE_VARCHAR, 0);
        $this->importEavAttribute($categoryId, 'display_mode', "PRODUCTS", MetaData::TYPE_VARCHAR, 0);
#todo make url key unique
        $this->importEavAttribute($categoryId, 'url_key', $urlKey, MetaData::TYPE_VARCHAR, 0);
        $this->importEavAttribute($categoryId, 'url_path', $urlPath, MetaData::TYPE_VARCHAR, 0);

        $this->importEavAttribute($categoryId, 'is_active', 1, MetaData::TYPE_INTEGER, 0);
        $this->importEavAttribute($categoryId, 'is_anchor', 1, MetaData::TYPE_INTEGER, 0);
        $this->importEavAttribute($categoryId, 'include_in_menu', 1, MetaData::TYPE_INTEGER, 0);
        $this->importEavAttribute($categoryId, 'custom_use_parent_settings', 0, MetaData::TYPE_INTEGER, 0);
        $this->importEavAttribute($categoryId, 'custom_apply_to_products', 0, MetaData::TYPE_INTEGER, 0);

        // !important: add this new category to the metadata collected
        $newIdPath = $idPath;
        $newIdPath[] = $categoryId;
        $this->metaData->addCategoryInfo($categoryId, $newIdPath, [0 => $urlKey]);

        return $categoryId;
    }

    protected function getParentUrlPath(int $parentId): string
    {
        $attributeId = $this->metaData->categoryAttributeMap['url_path'];

        $urlPath = $this->db->fetchSingleCell("
            SELECT `value`
            FROM `{$this->metaData->categoryEntityTable}_varchar`
            WHERE 
                `entity_id` = $parentId AND
                `attribute_id` = $attributeId AND
                `store_id` = 0
        ");

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
                `entity_id` = {$categoryId},
                `attribute_id` = {$attributeId},
                `store_id` = " . $storeId . ",
                `value` = " . $this->db->quote($value) . "
        ");
    }
}