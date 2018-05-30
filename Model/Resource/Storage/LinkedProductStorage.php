<?php

namespace BigBridge\ProductImport\Model\Resource\Storage;

use BigBridge\ProductImport\Api\Data\Product;
use BigBridge\ProductImport\Model\Data\LinkInfo;
use BigBridge\ProductImport\Model\Persistence\Magento2DbConnection;
use BigBridge\ProductImport\Model\Resource\MetaData;

/**
 * @author Patrick van Bergen
 */
class LinkedProductStorage
{
    /** @var  Magento2DbConnection */
    protected $db;

    /** @var MetaData */
    protected $metaData;

    public function __construct(Magento2DbConnection $db, MetaData $metaData)
    {
        $this->db = $db;
        $this->metaData = $metaData;
    }

    /**
     * @param Product[] $products
     */
    public function updateLinkedProducts(array $products)
    {
        foreach ([LinkInfo::RELATED, LinkInfo::UP_SELL, LinkInfo::CROSS_SELL] as $linkType) {

            $affectedProducts = [];

            foreach ($products as $product) {
                if ($product->getLinkedProductIds($linkType) !== null) {
                    $affectedProducts[] = $product;
                }
            }

            $this->removeProductLinks($linkType, $affectedProducts);
            $this->insertProductLinks($linkType, $affectedProducts);
        }
    }

    /**
     * @param string $linkType
     * @param Product[] $products
     */
    protected function removeProductLinks(string $linkType, array $products)
    {
        $linkInfo = $this->metaData->linkInfo[$linkType];
        $productIds = array_column($products, 'id');

        $this->db->deleteMultipleWithWhere($this->metaData->linkTable, 'product_id', $productIds, "`link_type_id` = {$linkInfo->typeId}");
    }

    /**
     * @param string $linkType
     * @param Product[] $products
     */
    protected function insertProductLinks(string $linkType, array $products)
    {
        $linkInfo = $this->metaData->linkInfo[$linkType];

        foreach ($products as $product) {
            $linkedIds = $product->getLinkedProductIds($linkType);

            // check if the user has specified links of this type at all
            if ($linkedIds === null) {
                continue;
            }

            $position = 1;
            foreach ($linkedIds as $linkedId) {

                $this->db->execute("
                    INSERT INTO `{$this->metaData->linkTable}`
                    SET 
                        `product_id` = ?,
                        `linked_product_id` = ?,
                        `link_type_id` = ?
                ", [
                    $product->id,
                    $linkedId,
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

                $position++;
            }
        }
    }
}
