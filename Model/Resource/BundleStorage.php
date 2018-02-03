<?php

namespace BigBridge\ProductImport\Model\Resource;

use BigBridge\ProductImport\Api\Data\BundleProduct;
use BigBridge\ProductImport\Api\Data\Product;
use BigBridge\ProductImport\Model\Db\Magento2DbConnection;
use BigBridge\ProductImport\Model\Resource\Resolver\BundleProductReferenceResolver;
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
class BundleStorage extends ProductStorage
{
    public function __construct(
        Magento2DbConnection $db,
        MetaData $metaData,
        Validator $validator,
        BundleProductReferenceResolver $referenceResolver,
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
     * @param Product[] $insertProducts
     * @param Product[] $updateProducts
     */
    public function performTypeSpecificStorage(array $insertProducts, array $updateProducts)
    {
        $this->createOptions($insertProducts);

        $changedProducts = $this->detectChangedProducts($updateProducts);
        $this->removeOptions($changedProducts);
        $this->createOptions($changedProducts);
    }

    /**
     * @param BundleProduct[] $products
     */
    protected function createOptions(array $products)
    {
        foreach ($products as $product) {

            foreach ($product->getOptions() as $i => $option) {

                $required = (int)$option->isRequired();
                $position = $i + 1;
                $type = $this->db->quote($option->getInputType());

                $this->db->execute("
                    INSERT INTO `{$this->metaData->bundleOptionTable}`
                    SET 
                        `parent_id` = {$product->id},
                        `required` = {$required},
                        `position` = {$position},
                        `type` = {$type}
                ");

                $option->id = $this->db->getLastInsertId();

                foreach ($option->getSelections() as $j => $selection) {

                    $selectionProductId = $selection->getProductId();
                    $selectionPosition = $j + 1;
                    $default = (int)$selection->isDefault();
                    $selectionPriceType = $selection->getPriceType();
                    $selectionPriceValue = $this->db->quote($selection->getPriceValue());
                    $selectionQuantity = $selection->getQuantity();
                    $selectionCanChangeQuantity = (int)$selection->isCanChangeQuantity();

                    $this->db->execute("
                        INSERT INTO `{$this->metaData->bundleSelectionTable}`
                        SET
                            `option_id` = {$option->id},
                            `parent_product_id` = {$product->id},
                            `product_id` = {$selectionProductId},
                            `position` = {$selectionPosition},
                            `is_default` = {$default},
                            `selection_price_type` = {$selectionPriceType},
                            `selection_price_value` = {$selectionPriceValue},
                            `selection_qty` = {$selectionQuantity},
                            `selection_can_change_qty` = {$selectionCanChangeQuantity}
                    ");
                }
            }

            foreach ($product->getStoreViews() as $storeView) {
                foreach ($storeView->getOptionInformations() as $optionInformation) {

                    $optionInfoOptionId = $optionInformation->getOption()->id;
                    $storeId = $storeView->getStoreViewId();
                    $title = $this->db->quote($optionInformation->getTitle());

                    $this->db->execute("
                        INSERT INTO `{$this->metaData->bundleOptionValueTable}`
                        SET
                            `option_id` = {$optionInfoOptionId},
                            `store_id` = {$storeId},
                            `title` = {$title} 
                    ");
                }
            }
        }
    }

    /**
     * @param BundleProduct[] $products
     */
    protected function removeOptions(array $products)
    {
        $productIds = array_column($products, 'id');

        if (empty($productIds)) {
            return;
        }

        $this->db->execute("
            DELETE FROM `{$this->metaData->bundleOptionTable}`
            WHERE `parent_id` IN (" . implode(', ', $productIds) . ")
        ");
    }

    /**
     * @param BundleProduct[] $products
     * @return BundleProduct[]
     */
    protected function detectChangedProducts(array $products): array
    {
        if (empty($products)) {
            return [];
        }

        // fetch all data that forms the option, per table

        $productIds = array_column($products, 'id');

        $optionInfo = $this->db->fetchAllAssoc("
            SELECT `option_id`, `parent_id`, `required`, `type`
            FROM `{$this->metaData->bundleOptionTable}` 
            WHERE `parent_id` IN (" . implode(', ', $productIds) . ")
            ORDER BY `position`
        ");

        $optionIds = array_column($optionInfo, 'option_id');

        $option2product = [];
        foreach ($optionInfo as $optionData) {
            $option2product[$optionData['option_id']] = $optionData['parent_id'];
        }

        $selectionInfo = $this->db->fetchAllAssoc("
            SELECT `parent_product_id`, `product_id`, `is_default`, `selection_price_type`, `selection_price_value`, `selection_qty`, `selection_can_change_qty`
            FROM `{$this->metaData->bundleSelectionTable}`
            WHERE `option_id` IN (" . implode(', ', $optionIds) . ")
            ORDER BY `selection_id`
        ");

        $titleInfo = $this->db->fetchAllAssoc("
            SELECT `option_id`, `title`
            FROM `{$this->metaData->bundleOptionValueTable}` 
            WHERE `option_id` IN (" . implode(', ', $optionIds) . ")
            ORDER BY `value_id`
        ");

        // create a string with all option fields, per product id

        $productInfo = [];
        foreach ($products as $product) {
            $productInfo[$product->id] = '';
        }

        foreach ($optionInfo as $optionData) {
            $productInfo[$optionData['parent_id']] .= '*' . $optionData['required'] . '-' . $optionData['type'];
        }
        foreach ($selectionInfo as $selectionData) {
            $productInfo[$selectionData['parent_product_id']] .= '*' . $selectionData['product_id'] .
                '-' . $selectionData['is_default'] .
                '-' . $selectionData['selection_price_type'] .
                '-' . $selectionData['selection_price_value'] .
                '-' . $selectionData['selection_qty'].
                '-' . $selectionData['selection_can_change_qty'];
        }
        foreach ($titleInfo as $titleData) {
            $productId = $option2product[$titleData['option_id']];
            $productInfo[$productId] .= '*' . $titleData['title'];
        }

        // compare the stored data with the new data to determine which products have changed

        $changed = [];

        foreach ($products as $product) {

            $serialized = '';

            foreach ($product->getOptions() as $option) {
                $serialized .= '*' . (int)$option->isRequired() . '-' . $option->getInputType();
            }
            foreach ($product->getOptions() as $option) {
                foreach ($option->getSelections() as $selection) {
                    $serialized .= '*' . $selection->getProductId() .
                        '-' . (int)$selection->isDefault() .
                        '-' . $selection->getPriceType() .
                        '-' . sprintf('%.4f', $selection->getPriceValue()) .
                        '-' . sprintf('%.4f', $selection->getQuantity()) . '-' .
                        (int)$selection->isCanChangeQuantity();
                }
            }
            foreach ($product->getStoreViews() as $storeView) {
                foreach ($storeView->getOptionInformations() as $optionInformation) {
                    $serialized .= '*' . $optionInformation->getTitle();
                }
            }

            if ($productInfo[$product->id] !== $serialized) {
                $changed[] = $product;
            }
        }

        return $changed;
    }
}