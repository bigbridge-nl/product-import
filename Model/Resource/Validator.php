<?php

namespace BigBridge\ProductImport\Model\Resource;

use BigBridge\ProductImport\Model\Data\Product;
use BigBridge\ProductImport\Model\Reference;

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

        $errors = [];

        // sku
        if (is_string($product->sku)) {
            $product->sku = trim($product->sku);
            if ($product->sku === "") {
                $errors[] = "missing sku";
            } elseif (mb_strlen($product->sku) > self::SKU_MAX_LENGTH) {
                $errors[] = "sku has " . mb_strlen($product->sku) . ' characters (max ' . self::SKU_MAX_LENGTH . ")";
            }
        } elseif (is_null($product->sku)) {
            $errors[] = "missing sku";
        } else {
            $errors[] = "sku is a " . gettype($product->sku) . ", should be a string";
        }

        // attribute set id
        if (is_string($product->attribute_set_id)) {
            if (!in_array($product->attribute_set_id, $this->metaData->productAttributeSetMap)) {
                $product->attribute_set_id = trim($product->attribute_set_id);
                if ($product->attribute_set_id === "") {
                    $errors[] = "missing attribute set id";
                } elseif (!is_numeric($product->attribute_set_id)) {
                    $errors[] = "attribute set id is a " . gettype($product->attribute_set_id) . ", should be an integer";
                } else {
                    $errors[] = "attribute set id does not exist: " . $product->attribute_set_id;
                }
            }
        } elseif (is_integer($product->attribute_set_id)) {
            $product->attribute_set_id = (string)$product->attribute_set_id;
            if (!in_array($product->attribute_set_id, $this->metaData->productAttributeSetMap)) {
                $errors[] = "attribute set id does not exist: " . $product->attribute_set_id;
            }
        } elseif (is_null($product->attribute_set_id)) {
            $errors[] = "missing attribute set id";
        } else {
            $errors[] = "attribute set id is a " . gettype($product->attribute_set_id) . ", should be a string";
        }

        // attribute set id
        if (is_string($product->store_view_id)) {
            if (!in_array($product->store_view_id, $this->metaData->storeViewMap)) {
                $product->store_view_id = trim($product->store_view_id);
                if ($product->store_view_id === "") {
                    $errors[] = "missing store view id";
                } elseif (!is_numeric($product->store_view_id)) {
                    $errors[] = "store view id is a " . gettype($product->store_view_id) . ", should be an integer";
                } else {
                    $errors[] = "store view id does not exist: " . $product->store_view_id;
                }
            }
        } elseif (is_integer($product->store_view_id)) {
            $product->store_view_id = (string)$product->store_view_id;
            if (!in_array($product->store_view_id, $this->metaData->storeViewMap)) {
                $errors[] = "store view id does not exist: " . $product->store_view_id;
            }
        } elseif (is_null($product->store_view_id)) {
            $errors[] = "missing store view id";
        } else {
            $errors[] = "store view id is a " . gettype($product->store_view_id) . ", should be a string";
        }

        // category_ids
        if (!is_array($product->category_ids)) {
            if ($product->category_ids instanceof Reference) {
                $errors[] = "category_ids is a Reference, should be a References(!) object";
            } else {
                $errors[] = "category_ids is a " . gettype($product->category_ids) . ", should be a References object or an array of integers";
            }
        } else {
            foreach ($product->category_ids as $id) {
                if (!preg_match('/\d+/', $id)) {
                    $errors[] = "category_ids should be a References object or an array of integers";
                    break;
                }
            }
        }

        // website_ids
        if (!is_array($product->website_ids)) {
            if ($product->website_ids instanceof Reference) {
                $errors[] = "website_ids is a Reference, should be a References(!) object";
            } else {
                $errors[] = "website_ids is a " . gettype($product->website_ids) . ", should be a References object or an array of integers";
            }
        } else {
            foreach ($product->website_ids as $id) {
                if (!preg_match('/\d+/', $id)) {
                    $errors[] = "website_ids should be a References object or an array of integers";
                    break;
                }
            }
        }

        foreach ($product as $eavAttribute => $value) {

            if (!array_key_exists($eavAttribute, $attributeInfo)) {
                continue;
            }

            $info = $attributeInfo[$eavAttribute];

            if (is_null($value)) {

                if ($info->isRequired) {
                    $errors[] = "missing " . $eavAttribute;
                }

            } else {

                if (is_string($value)) {

                    $value = trim($value);

                    $product->$eavAttribute = $value;

                } elseif (is_integer($value)) {

                    if ($info->backendType != MetaData::TYPE_INTEGER) {
                        $errors[]= $eavAttribute . " is an integer (" . $value . "), should be a string";
                        continue;
                    }

                    $product->$eavAttribute = (string)$value;

                } elseif (is_object($value)) {

                    $errors[] = $eavAttribute . " is an object (" . get_class($value) . "), should be a string";
                    continue;

                } else {

                    $errors[] = $eavAttribute . " is a " . gettype($value) . ", should be a string";
                    continue;

                }

                // empty values

                if ($value === "") {
                    if ($info->isRequired) {
                        $errors[] = "missing " . $eavAttribute;
                    } elseif (!in_array($info->backendType, [MetaData::TYPE_VARCHAR, MetaData::TYPE_TEXT])) {
                        $product->$eavAttribute = null;
                    }
                    continue;
                }

                // validate value

                switch ($info->backendType) {
                    case MetaData::TYPE_VARCHAR:
                        if (mb_strlen($value) > 255) {
                            $errors[] = $eavAttribute . " has " . mb_strlen($value) . " characters (max 255)";
                        }
                        break;
                    case MetaData::TYPE_TEXT:
                        if (strlen($value) > 65536) {
                            $errors[] = $eavAttribute . " has " . strlen($value) . " bytes (max 65536)";
                        }
                        break;
                    case MetaData::TYPE_DECIMAL:
                        if (!preg_match('/^\d{1,12}(\.\d{0,4})?$/', $value)) {
                            $errors[] = $eavAttribute . " is not a positive decimal number with dot (" . $value . ")";
                        }
                        break;
                    case MetaData::TYPE_DATETIME:
                        if (!preg_match('/^\d{4}-\d{2}-\d{2}( \d{2}:\d{2}:\d{2})?$/', $value)) {
                            $errors[] = $eavAttribute . " is not a MySQL date or date time (" . $value . ")";
                        }
                        break;
                    case MetaData::TYPE_INTEGER:
                        if (!preg_match('/^-?\d+$/', $value)) {
                            $errors[] = $eavAttribute . " is not an integer (" . $value . ")";
                        } else {
                            // validate possible options
                            if ($info->frontendInput === MetaData::FRONTEND_SELECT) {
                                if (!array_key_exists($value, $info->optionValues)) {
//                                      $errors[] = "illegal value for " . $eavAttribute . " status: (" . $value  . "), 3 (allowed = " . implode(", ", $info->optionValues) . ")";
                                }
                            }
                        }
                        break;
                }
            }
        }

        if (!empty($errors)) {
            $product->ok = false;
            $product->errors = array_merge($product->errors, $errors);
        }
    }
}