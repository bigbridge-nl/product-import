<?php

namespace BigBridge\ProductImport\Model\Resource\Validation;

use BigBridge\ProductImport\Api\Data\BundleProduct;
use BigBridge\ProductImport\Api\Data\ConfigurableProduct;
use BigBridge\ProductImport\Api\Data\GroupedProduct;
use BigBridge\ProductImport\Api\Data\Product;
use BigBridge\ProductImport\Api\Data\ProductStockItem;
use BigBridge\ProductImport\Api\Data\ProductStoreView;
use BigBridge\ProductImport\Api\Data\SourceItem;
use BigBridge\ProductImport\Api\Data\TierPrice;
use BigBridge\ProductImport\Helper\Decimal;
use BigBridge\ProductImport\Model\Data\EavAttributeInfo;
use BigBridge\ProductImport\Model\Resource\MetaData;

/**
 * @author Patrick van Bergen
 */
class Validator
{
    const SKU_MAX_LENGTH = 64;
    const DATE_PATTERN = '/^\d{4}-\d{2}-\d{2}( \d{2}:\d{2}:\d{2})?$/';

    /** @var  MetaData */
    protected $metaData;

    /** @var ImageValidator */
    protected $imageValidator;

    /** @var CustomOptionsValidator */
    protected $customOptionsValidator;

    /** @var WeeeValidator */
    protected $weeeValidator;

    /** @var ConfigurableValidator */
    protected $configurableValidator;

    /** @var BundleValidator */
    protected $bundleValidator;

    /** @var GroupedValidator */
    protected $groupedValidator;

    public function __construct(
        MetaData $metaData,
        ImageValidator $imageValidator,
        CustomOptionsValidator $customOptionsValidator,
        WeeeValidator $weeeValidator,
        ConfigurableValidator $configurableValidator,
        BundleValidator $bundleValidator,
        GroupedValidator $groupedValidator)
    {
        $this->metaData = $metaData;
        $this->imageValidator = $imageValidator;
        $this->customOptionsValidator = $customOptionsValidator;
        $this->weeeValidator = $weeeValidator;
        $this->configurableValidator = $configurableValidator;
        $this->groupedValidator = $groupedValidator;
        $this->bundleValidator = $bundleValidator;
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
        $globalAttributes = $storeViews[Product::GLOBAL_STORE_VIEW_CODE]->getAttributes();

        // sku
        if ($product->getSku() === "") {
            $product->addError("missing sku");
        } elseif (mb_strlen($product->getSku()) > self::SKU_MAX_LENGTH) {
            $product->addError("sku has " . mb_strlen($product->getSku()) . ' characters (max ' . self::SKU_MAX_LENGTH . ")");
        }

        // check required values
        if ($product->id === null) {

            // attribute_set_id
            if ($product->getAttributeSetId() === null) {
                $product->addError("missing attribute set id");
            }

            // name
            if (!array_key_exists('name', $globalAttributes) || $globalAttributes['name'] === "" || $globalAttributes['name'] === null) {
                $product->addError("missing name");
            }

            // price
            if (!($product instanceof GroupedProduct) && !($product instanceof BundleProduct)) {
                if (!array_key_exists('price', $globalAttributes) || $globalAttributes['price'] === "" || $globalAttributes['price'] === null) {
                    $product->addError("missing price");
                }
            }

        }

        // category_ids
        $categoryIds = $product->getCategoryIds();
        if ($categoryIds !== null) {
            foreach ($categoryIds as $id) {
                if (!preg_match('/^\d+$/', $id)) {
                    $product->addError("category_ids should be an array of integers");
                    break;
                }
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

        // source items
        foreach ($product->getSourceItems() as $sourceItem) {

            $sourceItemAttributes = $sourceItem->getAttributes();

            foreach ([SourceItem::QUANTITY, SourceItem::NOTIFY_STOCK_QTY] as $sourceItemAttribute) {

                if (array_key_exists($sourceItemAttribute, $sourceItemAttributes)) {

                    $value = $sourceItemAttributes[$sourceItemAttribute];

                    if (!preg_match(Decimal::DECIMAL_PATTERN, $value)) {
                        $product->addError("source item " . $sourceItemAttribute . " is not a decimal number with dot (" . $value . ")");
                    }
                }
            }
        }

        // images
        $this->imageValidator->validateImages($product);

        // custom options
        $this->customOptionsValidator->validateCustomOptions($product);

        // weee
        $this->weeeValidator->validateWeees($product);

        // other attributes
        foreach ($storeViews as $storeViewCode => $storeView) {

            foreach ($storeView->getAttributes() as $eavAttribute => $value) {

                if (!array_key_exists($eavAttribute, $attributeInfo)) {
                    $product->addError("attribute does not exist: " . $eavAttribute);
                    continue;
                }

                $info = $attributeInfo[$eavAttribute];

                if ($value === null || $value === "") {
                    continue;
                }

                // validate value

                switch ($info->backendType) {
                    case EavAttributeInfo::TYPE_VARCHAR:
                        if (mb_strlen($value) > 255) {
                            $product->addError($eavAttribute . " has " . mb_strlen($value) . " characters (max 255)");
                        }
                        break;
                    case EavAttributeInfo::TYPE_TEXT:
                        if (strlen($value) > 65536) {
                            $product->addError($eavAttribute . " has " . strlen($value) . " bytes (max 65536)");
                        }
                        break;
                    case EavAttributeInfo::TYPE_DECIMAL:
                        if (!preg_match(Decimal::$decimalEavPattern, $value)) {
                            $product->addError($eavAttribute . " is not a decimal number with dot (" . $value . ")");
                        } elseif ($value < 0.00) {
                            if (in_array($eavAttribute, [ProductStoreView::ATTR_PRICE, ProductStoreView::ATTR_SPECIAL_PRICE, ProductStoreView::ATTR_COST, ProductStoreView::ATTR_WEIGHT, ProductStoreView::ATTR_MSRP])) {
                                $product->addError($eavAttribute . " must be positive (" . $value . ")");
                            }
                        }
                        break;
                    case EavAttributeInfo::TYPE_DATETIME:
                        if (!preg_match(self::DATE_PATTERN, $value)) {
                            $product->addError($eavAttribute . " is not a MySQL date or date time (" . $value . ")");
                        }
                        break;
                    case EavAttributeInfo::TYPE_INTEGER:
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
                    if (!preg_match(Decimal::DECIMAL_PATTERN, $value)) {
                        $product->addError($decimalAttribute . " is not a decimal number with dot (" . $value . ")");
                    }
                }
            }
        }
    }

    /**
     * @param Product $product
     * @param Product[] $batchProducts
     */
    public function validateCompound(Product $product, array $batchProducts)
    {
        switch ($product->getType()) {
            case ConfigurableProduct::TYPE_CONFIGURABLE:
                /** @var ConfigurableProduct $product */
                $this->configurableValidator->validate($product, $batchProducts);
                break;
            case GroupedProduct::TYPE_GROUPED:
                /** @var GroupedProduct $product */
                $this->groupedValidator->validate($product, $batchProducts);
                break;
            case BundleProduct::TYPE_BUNDLE:
                /** @var BundleProduct $product */
                $this->bundleValidator->validate($product, $batchProducts);
        }
    }
}
