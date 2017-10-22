<?php

namespace BigBridge\ProductImport\Model\Resource\Id;

use BigBridge\ProductImport\Model\Db\Magento2DbConnection;
use BigBridge\ProductImport\Model\Resource\MetaData;

/**
 * @author Patrick van Bergen
 */
class CategoryImporter
{
    const CATEGORY_PATH_SEPARATOR = '/';

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
     * @return int[]
     */
    public function importCategoryPaths(array $categoryPaths)
    {
        $ids = [];

        foreach ($categoryPaths as $path) {
            if (array_key_exists($path, $this->categoryCache)) {
                $id = $this->categoryCache[$path];
                $ids[] = $id;
            } else {
                $id = $this->importCategoryPath($path);
                $this->categoryCache[$path] = $id;
                $ids[] = $id;
            }
        }

        return [$ids, ""];
    }

    /**
     * Creates a path of categories, if necessary, and returns the new id.
     *
     * @param string $namePath A / separated path of category names.
     * @return int
     */
    public function importCategoryPath(string $namePath): int
    {
        $categoryId = \Magento\Catalog\Model\Category::TREE_ROOT_ID;

        $idPath = [$categoryId];

        $categoryNames = explode(self::CATEGORY_PATH_SEPARATOR, $namePath);

        foreach ($categoryNames as $categoryName) {

            $categoryId = $this->getChildCategoryId($categoryId, $categoryName);

            if ($categoryId === null) {
                $categoryId = $this->importChildCategory($idPath, $categoryName);
            }

            $idPath[] = $categoryId;
        }

        return $categoryId;
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
        $parentPath = implode(self::CATEGORY_PATH_SEPARATOR, $idPath);
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
        $childPath = $parentPath . self::CATEGORY_PATH_SEPARATOR . $categoryId;

        $this->db->execute("
            UPDATE `{$categoryEntityTable}`
            SET 
                `path` = '{$childPath}'
            WHERE `entity_id` = {$categoryId}
        ");

        // url
        $parentUrlPath = $this->getParentUrlPath($parentId);
        $urlKey = $this->nameToUrlKeyConverter->createUrlKeyFromName($categoryName);
        $urlPath = $parentUrlPath . '/' . $urlKey;
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
#todo
        $this->importEavAttribute($categoryId, 'url_key', $urlKey, MetaData::TYPE_VARCHAR, 0);
        $this->importEavAttribute($categoryId, 'url_path', $urlPath, MetaData::TYPE_VARCHAR, 0);

        $this->importEavAttribute($categoryId, 'is_active', 1, MetaData::TYPE_INTEGER, 0);
        $this->importEavAttribute($categoryId, 'is_anchor', 1, MetaData::TYPE_INTEGER, 0);
        $this->importEavAttribute($categoryId, 'include_in_menu', 1, MetaData::TYPE_INTEGER, 0);
        $this->importEavAttribute($categoryId, 'custom_use_parent_settings', 0, MetaData::TYPE_INTEGER, 0);
        $this->importEavAttribute($categoryId, 'custom_apply_to_products', 0, MetaData::TYPE_INTEGER, 0);

        return $categoryId;
    }

    protected function getParentUrlPath(int $parentId): string
    {
        $attributeId = $this->metaData->categoryAttributeMap['url_path'];

        $urlPath = $this->db->fetchSingleCell("
            SELECT `value`
            FROM `catalog_category_entity_varchar`
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