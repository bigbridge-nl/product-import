<?php

namespace BigBridge\ProductImport\Model\Resource;

use BigBridge\ProductImport\Api\Data\GroupedProduct;
use BigBridge\ProductImport\Model\Data\LinkInfo;
use BigBridge\ProductImport\Model\Db\Magento2DbConnection;
use BigBridge\ProductImport\Model\Resource\Resolver\GroupedProductReferenceResolver;
use BigBridge\ProductImport\Model\Resource\Resolver\UrlKeyGenerator;
use BigBridge\ProductImport\Model\Resource\Storage\ImageStorage;
use BigBridge\ProductImport\Model\Resource\Storage\LinkedProductStorage;
use BigBridge\ProductImport\Model\Resource\Storage\ProductEntityStorage;
use BigBridge\ProductImport\Model\Resource\Storage\StockItemStorage;
use BigBridge\ProductImport\Model\Resource\Storage\TierPriceStorage;
use BigBridge\ProductImport\Model\Resource\Storage\UrlRewriteStorage;
use BigBridge\ProductImport\Model\Resource\Validation\Validator;

/**
 * @author Patrick van Bergen
 */
class GroupedStorage extends ProductStorage
{
    public function __construct(
        Magento2DbConnection $db,
        MetaData $metaData,
        Validator $validator,
        GroupedProductReferenceResolver $referenceResolver,
        UrlKeyGenerator $urlKeyGenerator,
        UrlRewriteStorage $urlRewriteStorage,
        ProductEntityStorage $productEntityStorage,
        ImageStorage $imageStorage,
        LinkedProductStorage $linkedProductStorage,
        TierPriceStorage $tierPriceStorage,
        StockItemStorage $stockItemStorage)
    {
        parent::__construct($db, $metaData, $validator, $referenceResolver, $urlKeyGenerator, $urlRewriteStorage, $productEntityStorage, $imageStorage, $linkedProductStorage, $tierPriceStorage, $stockItemStorage);
    }

    /**
     * @param GroupedProduct[] $insertProducts
     * @param GroupedProduct[] $updateProducts
     */
    public function performTypeSpecificStorage(array $insertProducts, array $updateProducts)
    {
        $this->insertLinkedProducts($insertProducts);
        $this->updateLinkedProducts($updateProducts);
    }

    /**
     * @param GroupedProduct[] $products
     */
    public function insertLinkedProducts(array $products)
    {
        $this->insertGroupMembers($products);
    }

    /**
     * @param GroupedProduct[] $products
     */
    public function updateLinkedProducts(array $products)
    {
        $changedProducts = $this->findProductsWithChangedGroupMembers($products);

        $this->removeGroupMembers($changedProducts);
        $this->insertGroupMembers($changedProducts);
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

        $productIds = array_column($products, 'id');

        $linkInfo = $this->metaData->linkInfo[LinkInfo::SUPER];

        // Note: the position and default quantity of the member products are taken in account as well
        $existingMembers = $this->db->fetchMap("
            SELECT `product_id`, CONCAT(
                GROUP_CONCAT(L.`linked_product_id` ORDER BY P.`value` SEPARATOR ' '),
                ' ',
                GROUP_CONCAT(Q.`value` ORDER BY P.`value` SEPARATOR ' '))
            FROM `{$this->metaData->linkTable}` L
            INNER JOIN `{$this->metaData->linkAttributeIntTable}` P ON P.`link_id` = L.`link_id` AND P.product_link_attribute_id = ?
            INNER JOIN `{$this->metaData->linkAttributeDecimalTable}` Q ON Q.`link_id` = L.`link_id` AND Q.product_link_attribute_id = ?
            WHERE 
                L.`link_type_id` = ? AND
                L.`product_id` IN (" . $this->db->getMarks($productIds) . ")                 
            GROUP by L.`product_id`
        ", array_merge([
            $linkInfo->positionAttributeId,
            $linkInfo->defaultQuantityAttributeId,
            $linkInfo->typeId
        ], $productIds));

        $changed = [];

        foreach ($products as $product) {

            $members = $product->getMembers();
            $memberIds = array_column($members, 'id');
            $serializedMemberData = implode(' ', $memberIds);

            foreach ($members as $member) {
                $serializedMemberData .= ' ' . sprintf('%.4f', $member->getDefaultQuantity());
            }

            if (!array_key_exists($product->id, $existingMembers) || $existingMembers[$product->id] !== $serializedMemberData) {
                $changed[] = $product;
            }
        }

        return $changed;
    }

    /**
     * @param GroupedProduct[] $products
     */
    protected function removeGroupMembers(array $products)
    {
        $productIds = array_column($products, 'id');
        $linkInfo = $this->metaData->linkInfo[LinkInfo::SUPER];

        $this->db->deleteMultipleWithWhere($this->metaData->linkTable, 'product_id', $productIds, "`link_type_id` = {$linkInfo->typeId}");
    }

    /**
     * @param GroupedProduct[] $products
     */
    protected function insertGroupMembers(array $products)
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
}