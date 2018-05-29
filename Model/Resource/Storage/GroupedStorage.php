<?php

namespace BigBridge\ProductImport\Model\Resource\Storage;

use BigBridge\ProductImport\Api\Data\GroupedProduct;
use BigBridge\ProductImport\Api\Data\Product;
use BigBridge\ProductImport\Model\Data\LinkInfo;
use BigBridge\ProductImport\Model\Persistence\Magento2DbConnection;
use BigBridge\ProductImport\Model\Resource\MetaData;

/**
 * @author Patrick van Bergen
 */
class GroupedStorage
{
    /** @var  Magento2DbConnection */
    protected $db;

    /** @var  MetaData */
    protected $metaData;

    public function __construct(
        Magento2DbConnection $db,
        MetaData $metaData,
        ImageStorage $imageStorage)
    {
        $this->db = $db;
        $this->metaData = $metaData;
    }

    /**
     * @param GroupedProduct[] $products
     */
    public function performTypeSpecificStorage(array $products)
    {
        $changedProducts = $this->findProductsWithChangedGroupMembers($products);

        $this->removeLinkedProducts($changedProducts);
        $this->insertLinkedProducts($changedProducts);
    }

    /**
     * @param GroupedProduct[] $products
     * @return GroupedProduct[]
     */
    protected function findProductsWithChangedGroupMembers(array $products)
    {
        if (empty($products)) {
            return [];
        }

        $changed = [];

        $linkInfo = $this->metaData->linkInfo[LinkInfo::SUPER];
        $productIds = array_column($products, 'id');

        $allProductData = $this->db->fetchGrouped("
            SELECT 
                L.`product_id`, L.`link_id`, L.`linked_product_id`,
                P.`value` AS `position`,
                Q.`value` AS `default_quantity`  
            FROM `{$this->metaData->linkTable}` L
            INNER JOIN `{$this->metaData->linkAttributeIntTable}` P ON P.`link_id` = L.`link_id`
            INNER JOIN `{$this->metaData->linkAttributeDecimalTable}` Q ON Q.`link_id` = L.`link_id`
            WHERE
                P.`product_link_attribute_id` = ? AND
                Q.`product_link_attribute_id` = ? AND 
                L.`link_type_id` = ? AND  
                L.`product_id` IN (" . $this->db->getMarks($productIds) . ")
            ORDER BY L.`product_id`, P.`value`    
        ", array_merge([
            $linkInfo->positionAttributeId,
            $linkInfo->defaultQuantityAttributeId,
            $linkInfo->typeId
        ], $productIds),
            ['product_id', 'link_id']);

        foreach ($products as $product) {
            if (!array_key_exists($product->id, $allProductData)) {
                $changed[$product->id] = $product;
                continue;
            }
            $productData = $allProductData[$product->id];
            $members = $product->getMembers();
            if (count($productData) != count($members)) {
                $changed[$product->id] = $product;
                continue;
            }
            $i = 0;
            foreach ($productData as $memberData) {
                $member = $members[$i++];

                if ($member->getDefaultQuantity() != $memberData['default_quantity']) {
                    $changed[$product->id] = $product;
                    break;
                }
                $position = $i + 1;
                if ($position != $memberData['position']) {
                    $changed[$product->id] = $product;
                    break;
                }
            }
        }

        return $changed;
    }


    /**
     * @param GroupedProduct[] $products
     */
    public function insertLinkedProducts(array $products)
    {
        $linkInfo = $this->metaData->linkInfo[LinkInfo::SUPER];

        foreach ($products as $product) {
            $position = 1;
            foreach ($product->getMembers() as $i => $member) {

                $this->db->execute("
                    INSERT INTO `{$this->metaData->linkTable}`
                    SET 
                        `product_id` = ?,
                        `linked_product_id` = ?,
                        `link_type_id` = ?
                ", [
                    $product->id,
                    $member->getProductId(),
                    $linkInfo->typeId
                ]);

                $linkId = $this->db->getLastInsertId();

                $this->db->execute("
                    INSERT INTO `{$this->metaData->linkAttributeIntTable}`
                    SET
                        `product_link_attribute_id` = ?,
                        `link_id` = ?,
                        `value` = ?
                ", [
                    $linkInfo->positionAttributeId,
                    $linkId,
                    $position
                ]);

                $this->db->execute("
                    INSERT INTO `{$this->metaData->linkAttributeDecimalTable}`
                    SET
                        `product_link_attribute_id` = ?,
                        `link_id` = ?,
                        `value` = ?
                ", [
                    $linkInfo->defaultQuantityAttributeId,
                    $linkId,
                    $member->getDefaultQuantity()
                ]);

                $position++;
            }
        }
    }

    /**
     * @param Product[] $products
     */
    public function removeLinkedProducts(array $products)
    {
        $productIds = array_column($products, 'id');
        $linkInfo = $this->metaData->linkInfo[LinkInfo::SUPER];

        $this->db->deleteMultipleWithWhere($this->metaData->linkTable, 'product_id', $productIds, "`link_type_id` = {$linkInfo->typeId}");
    }
}