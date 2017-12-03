<?php

namespace BigBridge\ProductImport\Model\Resource;

use BigBridge\ProductImport\Api\Product;

/**
 * @author Patrick van Bergen
 */
class Validator
{
    const SKU_MAX_LENGTH = 64;

    /** @var  MetaData */
    protected $metaData;

    public function __construct(MetaData $metaData)
    {
        $this->metaData = $metaData;
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

        if ($product->id === null) {

            // attribute_set_id
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
                        if (!preg_match('/^\d{1,12}(\.\d{0,4})?$/', $value)) {
                            $product->addError($eavAttribute . " is not a positive decimal number with dot (" . $value . ")");
                        }
                        break;
                    case MetaData::TYPE_DATETIME:
                        if (!preg_match('/^\d{4}-\d{2}-\d{2}( \d{2}:\d{2}:\d{2})?$/', $value)) {
                            $product->addError($eavAttribute . " is not a MySQL date or date time (" . $value . ")");
                        }
                        break;
                    case MetaData::TYPE_INTEGER:
                        if (!preg_match('/^-?\d+$/', $value)) {
                            $product->addError($eavAttribute . " is not an integer (" . $value . ")");
                        } else {
                            // validate possible options
                            if ($info->frontendInput === MetaData::FRONTEND_SELECT) {
                                if (!array_key_exists($value, $info->optionValues)) {
                                    //                                      $product->addError("illegal value for " . $eavAttribute . " status: (" . $value  . "), 3 (allowed = " . implode(", ", $info->optionValues) . ")"(;
                                }
                            }
                        }
                        break;
//                    }
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