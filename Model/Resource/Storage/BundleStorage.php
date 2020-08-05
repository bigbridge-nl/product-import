<?php

namespace BigBridge\ProductImport\Model\Resource\Storage;

use BigBridge\ProductImport\Api\Data\BundleProduct;
use BigBridge\ProductImport\Api\Data\Product;
use BigBridge\ProductImport\Model\Persistence\Magento2DbConnection;
use BigBridge\ProductImport\Model\Resource\MetaData;

/**
 * @author Patrick van Bergen
 */
class BundleStorage
{
    /** @var  Magento2DbConnection */
    protected $db;

    /** @var  MetaData */
    protected $metaData;

    public function __construct(
        Magento2DbConnection $db,
        MetaData $metaData)
    {
        $this->db = $db;
        $this->metaData = $metaData;
    }

    /**
     * @param Product[] $products
     */
    public function performTypeSpecificStorage(array $products)
    {
        $affectedProducts = [];

        foreach ($products as $product) {
            if ($product->getOptions() !== null) {
                $affectedProducts[] = $product;
            }
        }

        $this->removeOptions($affectedProducts);
        $this->createOptions($affectedProducts);
    }

    /**
     * @param BundleProduct[] $products
     */
    public function createOptions(array $products)
    {
        $magento22 = (version_compare($this->metaData->magentoVersion, "2.2.0") >= 0);

        foreach ($products as $product) {

            foreach ($product->getOptions() as $i => $option) {

                $this->db->execute("
                INSERT INTO `{$this->metaData->bundleOptionTable}`
                SET 
                    `parent_id` = ?,
                    `required` = ?,
                    `position` = ?,
                    `type` = ?
            ", [
                    $product->id,
                    (int)$option->isRequired(),
                    $i + 1,
                    $option->getInputType()]);

                $option->id = $this->db->getLastInsertId();

                foreach ($option->getSelections() as $j => $selection) {

                    $this->db->execute("
                    INSERT INTO `{$this->metaData->bundleSelectionTable}`
                    SET
                        `option_id` = ?,
                        `parent_product_id` = ?,
                        `product_id` = ?,
                        `position` = ?,
                        `is_default` = ?,
                        `selection_price_type` = ?,
                        `selection_price_value` = ?,
                        `selection_qty` = ?,
                        `selection_can_change_qty` = ?
                ", [
                        $option->id,
                        $product->id,
                        $selection->getProductId(),
                        $j + 1,
                        (int)$selection->isDefault(),
                        $selection->getPriceType(),
                        $selection->getPriceValue(),
                        $selection->getQuantity(),
                        (int)$selection->isCanChangeQuantity()]);
                }
            }

            foreach ($product->getStoreViews() as $storeView) {
                foreach ($storeView->getOptionInformations() as $optionInformation) {

                    if ($magento22) {

                        $this->db->execute("
                        INSERT INTO `{$this->metaData->bundleOptionValueTable}`
                        SET
                            `option_id` = ?,
                            `store_id` = ?,
                            `title` = ?,
				            `parent_product_id` = ? 
                    ", [
                            $optionInformation->getOption()->id,
                            $storeView->getStoreViewId(),
                            $optionInformation->getTitle(),
                            $product->id
                        ]);

                    } else {

                        $this->db->execute("
                        INSERT INTO `{$this->metaData->bundleOptionValueTable}`
                        SET
                            `option_id` = ?,
                            `store_id` = ?,
                            `title` = ? 
                    ", [
                            $optionInformation->getOption()->id,
                            $storeView->getStoreViewId(),
                            $optionInformation->getTitle()
                        ]);
                    }
                }
            }
        }
    }

    /**
     * @param Product[] $products
     */
    public function removeOptions(array $products)
    {
        $productIds = array_column($products, 'id');

        $this->db->deleteMultiple($this->metaData->bundleOptionTable, 'parent_id', $productIds);
    }
}