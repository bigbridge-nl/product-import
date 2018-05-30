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
        $this->removeLinkedProducts($products);
        $this->insertLinkedProducts($products);
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