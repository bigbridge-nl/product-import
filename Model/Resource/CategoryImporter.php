<?php

namespace BigBridge\ProductImport\Model\Resource;

use BigBridge\ProductImport\Model\Db\Magento2DbConnection;

/**
 * @author Patrick van Bergen
 */
class CategoryImporter
{
    const CATEGORY_PATH_SEPARATOR = '/';

    /**  @var Magento2DbConnection */
    private $db;

    /** @var MetaData */
    private $metaData;

    public function __construct(Magento2DbConnection $db, MetaData $metaData)
    {
        $this->db = $db;
        $this->metaData = $metaData;
    }

    /**
     * Creates a path of categories, if necessary, and returns the new id.
     *
     * @param string $namePath A / separated path of category names.
     * @return int
     */
    public function importCategoryPath(string $namePath): int
    {
#todo move to batch start
        $this->db->execute("START TRANSACTION");

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

        $this->db->execute("COMMIT");

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

        $insertId = $this->db->getLastInsertId();

        // add path that contains the new id
        $childPath = $parentPath . self::CATEGORY_PATH_SEPARATOR . $insertId;

        $this->db->execute("
            UPDATE `{$categoryEntityTable}`
            SET 
                `path` = '{$childPath}'
            WHERE `entity_id` = {$insertId}
        ");

        $this->importEavAttribute($insertId, 'name', $categoryName, MetaData::TYPE_VARCHAR, 0);
        $this->importEavAttribute($insertId, 'display_mode', "PRODUCTS", MetaData::TYPE_VARCHAR, 0);
#todo
        $this->importEavAttribute($insertId, 'url_key', $categoryName, MetaData::TYPE_VARCHAR, 0);
        $this->importEavAttribute($insertId, 'url_path', $categoryName, MetaData::TYPE_VARCHAR, 0);

        $this->importEavAttribute($insertId, 'is_active', 1, MetaData::TYPE_INTEGER, 0);
        $this->importEavAttribute($insertId, 'is_anchor', 1, MetaData::TYPE_INTEGER, 0);
        $this->importEavAttribute($insertId, 'include_in_menu', 1, MetaData::TYPE_INTEGER, 0);
        $this->importEavAttribute($insertId, 'custom_use_parent_settings', 0, MetaData::TYPE_INTEGER, 0);
        $this->importEavAttribute($insertId, 'custom_apply_to_products', 0, MetaData::TYPE_INTEGER, 0);

        return $insertId;
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