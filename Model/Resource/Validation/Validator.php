<?php

namespace BigBridge\ProductImport\Model\Resource\Validation;

use BigBridge\ProductImport\Api\Product;
use BigBridge\ProductImport\Api\ProductStockItem;
use BigBridge\ProductImport\Api\TierPrice;
use BigBridge\ProductImport\Model\Data\EavAttributeInfo;
use BigBridge\ProductImport\Model\Resource\MetaData;

/**
 * @author Patrick van Bergen
 */
class Validator
{
    const SKU_MAX_LENGTH = 64;
    const DATE_PATTERN = '/^\d{4}-\d{2}-\d{2}( \d{2}:\d{2}:\d{2})?$/';
    const DECIMAL_PATTERN = '/^-?\d{1,12}(\.\d{0,4})?$/';

    /** @var  MetaData */
    protected $metaData;

    /** @var ImageValidator */
    protected $imageValidator;

    public function __construct(MetaData $metaData, ImageValidator $imageValidator)
    {
        $this->metaData = $metaData;
        $this->imageValidator = $imageValidator;
    }

    /**
     * Checks $product for all known requirements.
     *
     * @param Product $product
     */
    public function validate(Product $product)
    {
        $attributeInfo = $this->metaData->productEavAttributeInfo;
        $storeViews = $product->getStoreViews();

        // sku
        if ($product->getSku() === "") {
            $product->addError("missing sku");
        } elseif (mb_strlen($product->getSku()) > self::SKU_MAX_LENGTH) {
            $product->addError("sku has " . mb_strlen($product->getSku()) . ' characters (max ' . self::SKU_MAX_LENGTH . ")");
        }

        // attribute_set_id
        if ($product->id === null) {
            if ($product->getAttributeSetId() === null) {
                $product->addError("missing attribute set id");
            }
        }

        // category_ids
        $categoryIds = $product->getCategoryIds();
        foreach ($categoryIds as $id) {
            if (!preg_match('/^\d+$/', $id)) {
                $product->addError("category_ids should be an array of integers");
                break;
            }
        }

        // website_ids
        $websiteIds = $product->getWebsiteIds();
        foreach ($websiteIds as $id) {
            if (!preg_match('/^\d+$/', $id)) {
                $product->addError("website_ids should be an array of integers");
                break;
            }
        }

        // tier prices
        $tierPrices = $product->getTierPrices();
        if ($tierPrices !== null) {
            foreach ($tierPrices as $tierPrice) {
                if (!($tierPrice instanceof TierPrice)) {
                    $product->addError("tierprices should be an array of TierPrice");
                    break;
                }
            }
        }

        // images
        $this->imageValidator->validateImages($product);

        foreach ($storeViews as $storeViewCode => $storeView) {

            foreach ($storeView->getAttributes() as $eavAttribute => $value) {

                if (!array_key_exists($eavAttribute, $attributeInfo)) {
                    $product->addError("attribute does not exist: " . $eavAttribute);
                    continue;
                }

                $info = $attributeInfo[$eavAttribute];

                // remove empty values

                if ($value === "") {
                    $storeView->removeAttribute($eavAttribute);
                    continue;
                }

                // validate value

                switch ($info->backendType) {
                    case MetaData::TYPE_VARCHAR:
                        if (mb_strlen($value) > 255) {
                            $product->addError($eavAttribute . " has " . mb_strlen($value) . " characters (max 255)");
                        }
                        break;
                    case MetaData::TYPE_TEXT:
                        if (strlen($value) > 65536) {
                            $product->addError($eavAttribute . " has " . strlen($value) . " bytes (max 65536)");
                        }
                        break;
                    case MetaData::TYPE_DECIMAL:
                        if (!preg_match(self::DECIMAL_PATTERN, $value)) {
                            $product->addError($eavAttribute . " is not a decimal number with dot (" . $value . ")");
                        }
                        break;
                    case MetaData::TYPE_DATETIME:
                        if (!preg_match(self::DATE_PATTERN, $value)) {
                            $product->addError($eavAttribute . " is not a MySQL date or date time (" . $value . ")");
                        }
                        break;
                    case MetaData::TYPE_INTEGER:
                        if (!preg_match('/^-?\d+$/', $value)) {
                            $product->addError($eavAttribute . " is not an integer (" . $value . ")");
                        }
                        break;
                }
            }
        }

        // stock items
        foreach ($product->getStockItems() as $stockItem) {
            $stockAttributes = $stockItem->getAttributes();

            // dates
            if (array_key_exists(ProductStockItem::LOW_STOCK_DATE, $stockAttributes)) {
                $value = $stockAttributes[ProductStockItem::LOW_STOCK_DATE];
                if (!preg_match(self::DATE_PATTERN, $value)) {
                    $product->addError(ProductStockItem::LOW_STOCK_DATE . " is not a MySQL date or date time (" . $value . ")");
                }
            }

            $decimalAttributes =
                [ProductStockItem::QTY, ProductStockItem::MIN_QTY, ProductStockItem::NOTIFY_STOCK_QTY,
                ProductStockItem::MIN_SALE_QTY, ProductStockItem::MAX_SALE_QTY, ProductStockItem::QTY_INCREMENTS];


            // decimals
            foreach ($decimalAttributes as $decimalAttribute) {
                if (array_key_exists($decimalAttribute, $stockAttributes)) {
                    $value = $stockAttributes[$decimalAttribute];
                    if (!preg_match(self::DECIMAL_PATTERN, $value)) {
                        $product->addError($decimalAttribute . " is not a decimal number with dot (" . $value . ")");
                    }
                }
            }
        }

        // required values

        if ($product->id === null) {

            // new product

            if (!array_key_exists(Product::GLOBAL_STORE_VIEW_CODE, $storeViews)) {
                $product->addError("product has no global values. Please specify global() for name and price");
            } else {

                // check required values

// todo: depends on product type
// for example: https://magento.stackexchange.com/questions/147349/the-value-of-attribute-price-view-must-be-set-in-magento-2

                $globalAttributes = $storeViews[Product::GLOBAL_STORE_VIEW_CODE]->getAttributes();

                $requiredValues = ['name', 'price'];
                foreach ($requiredValues as $attributeCode) {
                    if (!array_key_exists($attributeCode, $globalAttributes)) {
                        $product->addError("missing " . $attributeCode);
                    }
                }
            }
        }
    }
}