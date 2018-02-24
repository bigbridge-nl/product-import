<?php

namespace BigBridge\ProductImport\Model\Resource\Storage;

use BigBridge\ProductImport\Api\Data\Product;
use BigBridge\ProductImport\Model\Data\LinkInfo;
use BigBridge\ProductImport\Model\Db\Magento2DbConnection;
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
    public function insertLinkedProducts(array $products)
    {
        foreach ([LinkInfo::RELATED, LinkInfo::UP_SELL, LinkInfo::CROSS_SELL] as $linkType) {
            $this->insertProductLinks($linkType, $products);
        }
    }

    /**
     * @param Product[] $products
     */
    public function updateLinkedProducts(array $products)
    {
        foreach ([LinkInfo::RELATED, LinkInfo::UP_SELL, LinkInfo::CROSS_SELL] as $linkType) {

            $changedProducts = $this->findProductsWithChangedLinks($linkType, $products);

            $this->removeProductLinks($linkType, $changedProducts);
            $this->insertProductLinks($linkType, $changedProducts);

        }
    }

    /**
     * @param string $linkType
     * @param Product[] $products
     * @return array
     */
    protected function findProductsWithChangedLinks(string $linkType, array $products): array
    {
        if (empty($products)) {
            return [];
        }

        $productIds = array_column($products, 'id');

        $linkInfo = $this->metaData->linkInfo[$linkType];

        // Note: the position of the linked products is taken in account as well
        $existingLinks = $this->db->fetchMap("
            SELECT `product_id`, GROUP_CONCAT(L.`linked_product_id` ORDER BY P.`value` SEPARATOR ' ')
            FROM `{$this->metaData->linkTable}` L
            INNER JOIN `{$this->metaData->linkAttributeIntTable}` P ON P.`link_id` = L.`link_id` AND P.product_link_attribute_id = {$linkInfo->positionAttributeId}
            WHERE 
                L.`link_type_id` = ? AND
                L.`product_id` IN (" . $this->db->getMarks($productIds) . ")                 
            GROUP by L.`product_id`
        ", array_merge([
            $linkInfo->typeId
        ], $productIds));

        $changed = [];

        foreach ($products as $product) {
            
            $linkedIds = $product->getLinkedProductIds($linkType);

            // if the user has not specified links of this type, do not change existing links
            if ($linkedIds === null) {
                continue;
            }

            $serializedlinkedIds = implode(' ', $linkedIds);

            if (!array_key_exists($product->id, $existingLinks) || $existingLinks[$product->id] !== $serializedlinkedIds) {
                $changed[] = $product;
            }
        }

        return $changed;
    }

    /**
     * @param string $linkType
     * @param Product[] $products
     */
    protected function removeProductLinks(string $linkType, array $products)
    {
        $productIds = array_column($products, 'id');
        $linkInfo = $this->metaData->linkInfo[$linkType];

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
