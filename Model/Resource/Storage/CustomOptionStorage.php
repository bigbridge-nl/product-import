<?php

namespace BigBridge\ProductImport\Model\Resource\Storage;

use BigBridge\ProductImport\Api\Data\Product;
use BigBridge\ProductImport\Model\Persistence\Magento2DbConnection;
use BigBridge\ProductImport\Model\Resource\MetaData;

/**
 * @author Patrick van Bergen
 */
class CustomOptionStorage
{
    /** @var Magento2DbConnection */
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
    public function updateCustomOptions(array $products)
    {
        $affectedProducts = [];

        foreach ($products as $product) {
            if ($product->getCustomOptions() !== null) {
                $affectedProducts[] = $product;
            }
        }

        $this->removeCustomOptions($affectedProducts);
        $this->insertCustomOptions($affectedProducts);
    }

    /**
     * @param Product[] $products
     */
    public function insertCustomOptions(array $products)
    {
        foreach ($products as $product) {

            foreach ($customOptions = $product->getCustomOptions() as $i => $customOption) {

                $this->db->execute("
                    INSERT INTO `{$this->metaData->customOptionTable}`
                    SET 
                        `product_id` = ?,
                        `type` = ?,
                        `is_require` = ?,
                        `sku` = ?,
                        `max_characters` = ?,
                        `file_extension` = ?,
                        `image_size_x` = ?,
                        `image_size_y` = ?,
                        `sort_order` = ?
                ", [
                    $product->id,
                    $customOption->getType(),
                    (int)$customOption->isRequired(),
                    $customOption->getSku(),
                    $customOption->getMaxCharacters(),
                    $customOption->getFileExtensions(),
                    $customOption->getImageSizeX(),
                    $customOption->getImageSizeY(),
                    ($i + 1)
                ]);

                $optionId = $this->db->getLastInsertId();
                $customOption->setOptionId($optionId);

                // value sku's
                $optionTypeIds = [];
                foreach ($customOption->getValues() as $j => $valueSku) {
                    $this->db->execute("
                            INSERT INTO `{$this->metaData->customOptionTypeValueTable}`
                            SET 
                                `option_id` = ?,
                                `sku` = ?,
                                `sort_order` = ?
                        ", [
                        $optionId,
                        $valueSku,
                        (int)($j + 1)
                    ]);
                    $optionTypeIds[] = $this->db->getLastInsertId();
                }

                foreach ($product->getStoreViews() as $storeView) {

                    // option price and price type
                    foreach ($storeView->getCustomOptionPrices() as $priceStruct) {
                        if ($priceStruct->getCustomOption() === $customOption) {
                            $this->db->execute("
                                INSERT INTO `{$this->metaData->customOptionPriceTable}`
                                SET 
                                    `option_id` = ?,
                                    `store_id` = ?,
                                    `price` = ?,
                                    `price_type` = ?
                            ", [
                                $optionId,
                                $storeView->getStoreViewId(),
                                $priceStruct->getPrice(),
                                $priceStruct->getPriceType()
                            ]);
                            break;
                        }
                    }

                    // option title
                    foreach ($storeView->getCustomOptionTitles() as $titleStruct) {
                        if ($titleStruct->getCustomOption() === $customOption) {
                            $this->db->execute("
                                INSERT INTO `{$this->metaData->customOptionTitleTable}`
                                SET 
                                    `option_id` = ?,
                                    `store_id` = ?,
                                    `title` = ?
                            ", [
                                $optionId,
                                $storeView->getStoreViewId(),
                                $titleStruct->getTitle()
                            ]);
                            break;
                        }
                    }

                    $customOptionValues = $storeView->getCustomOptionValues($customOption);
                    if (!empty($customOptionValues)) {

                        // option values per store view
                        foreach ($optionTypeIds as $index => $optionTypeId) {

                            $value = $customOptionValues[$index];

                            $this->db->execute("
                            INSERT INTO `{$this->metaData->customOptionTypeTitleTable}`
                            SET 
                                `option_type_id` = ?,
                                `store_id` = ?,
                                `title` = ?
                        ", [
                                $optionTypeId,
                                $storeView->getStoreViewId(),
                                $value->getTitle()
                            ]);
                            $this->db->execute("
                            INSERT INTO `{$this->metaData->customOptionTypePriceTable}`
                            SET 
                                `option_type_id` = ?,
                                `store_id` = ?,
                                `price` = ?,
                                `price_type` = ?
                        ", [
                                $optionTypeId,
                                $storeView->getStoreViewId(),
                                $value->getPrice(),
                                $value->getPriceType()
                            ]);
                        }
                    }
                }
            }
        }
    }

    /**
     * @param Product[] $products
     */
    protected function removeCustomOptions(array $products)
    {
        $productIds = array_column($products, 'id');

        $this->db->deleteMultiple($this->metaData->customOptionTable, 'product_id', $productIds);
    }
}