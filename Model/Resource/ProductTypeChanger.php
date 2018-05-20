<?php

namespace BigBridge\ProductImport\Model\Resource;

use BigBridge\ProductImport\Api\Data\Product;
use BigBridge\ProductImport\Api\Data\SimpleProduct;
use BigBridge\ProductImport\Model\Db\Magento2DbConnection;

/**
 * @author Patrick van Bergen
 */
class ProductTypeChanger
{
    /**  @var Magento2DbConnection */
    protected $db;

    /** @var MetaData */
    protected $metaData;

    public function __construct(Magento2DbConnection $db, MetaData $metaData)
    {
        $this->db = $db;
        $this->metaData = $metaData;
    }

    public function handleTypeChanges(array $updatedProducts)
    {
        if (empty($updatedProducts)) {
            return;
        }

        $productIds = array_column($updatedProducts, 'id');

        $oldTypes = $this->db->fetchMap("
            SELECT `entity_id`, `type_id`
            FROM `" . $this->metaData->productEntityTable . "`
            WHERE `entity_id` IN (" . implode(',', $productIds) . ")
        ");

        foreach ($updatedProducts as $product) {

            $oldType = $oldTypes[$product->id];
            $newType = $product->getType();

            if ($oldType !== $newType) {
                $this->convertProductType($product, $oldType);
            }
        }
    }

    protected function convertProductType(Product $product, string $oldType)
    {
        $newType = $product->getType();

        switch ($oldType) {
            case SimpleProduct::TYPE_SIMPLE:
                // allowed no changes needed
                break;
            default:
                $product->addError("Type conversion from {$oldType} to {$newType} is not supported");
        }
    }
}