<?php

namespace BigBridge\ProductImport\Model\Resource;

use BigBridge\ProductImport\Model\Data\Product;
use BigBridge\ProductImport\Model\ImportConfig;

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
     * @return array An array with [ok, error]
     */
    public function validate(Product $product, ImportConfig $config)
    {
        $attributeInfo = $this->metaData->productEavAttributeInfo;

        $error = "";

        // sku
        if (is_string($product->sku)) {
            $product->sku = trim($product->sku);
            if ($product->sku === "") {
                $error .= "; missing sku";
            } elseif (mb_strlen($product->sku) > self::SKU_MAX_LENGTH) {
                $error .= "; sku has " . mb_strlen($product->sku) . ' characters (max ' . self::SKU_MAX_LENGTH . ")";
            }
        } elseif (is_null($product->sku)) {
            $error .= "; missing sku";
        } else {
            $error .= "; sku is a " . gettype($product->sku) . ", should be a string";
        }

        // attribute set id
        if (is_string($product->attribute_set_id)) {
            if (!in_array($product->attribute_set_id, $this->metaData->productAttributeSetMap)) {
                $product->attribute_set_id = trim($product->attribute_set_id);
                if ($product->attribute_set_id === "") {
                    $error .= "; missing attribute set id";
                } elseif ($product->attribute_set_id === NameConverter::NOT_FOUND) {
                    $error .= "; unknown attribute set name";
                } elseif (!is_numeric($product->attribute_set_id)) {
                    $error .= "; attribute set id is a " . gettype($product->attribute_set_id) . ", should be an integer";
                } else {
                    $error .= "; attribute set id does not exist: " . $product->attribute_set_id;
                }
            }
        } elseif (is_integer($product->attribute_set_id)) {
            $product->attribute_set_id = (string)$product->attribute_set_id;
            if (!in_array($product->attribute_set_id, $this->metaData->productAttributeSetMap)) {
                $error .= "; attribute set id does not exist: " . $product->attribute_set_id;
            }
        } elseif (is_null($product->attribute_set_id)) {
            $error .= "; missing attribute set id";
        } else {
            $error .= "; attribute set id is a " . gettype($product->attribute_set_id) . ", should be a string";
        }

        // attribute set id
        if (is_string($product->store_view_id)) {
            if (!in_array($product->store_view_id, $this->metaData->storeViewMap)) {
                $product->store_view_id = trim($product->store_view_id);
                if ($product->store_view_id === "") {
                    $error .= "; missing store view id";
                } elseif ($product->store_view_id === NameConverter::NOT_FOUND) {
                    $error .= "; unknown store view code";
                } elseif (!is_numeric($product->store_view_id)) {
                    $error .= "; store view id is a " . gettype($product->store_view_id) . ", should be an integer";
                } else {
                    $error .= "; store view id does not exist: " . $product->store_view_id;
                }
            }
        } elseif (is_integer($product->store_view_id)) {
            $product->store_view_id = (string)$product->store_view_id;
            if (!in_array($product->store_view_id, $this->metaData->storeViewMap)) {
                $error .= "; store view id does not exist: " . $product->store_view_id;
            }
        } elseif (is_null($product->store_view_id)) {
            $error .= "; missing store view id";
        } else {
            $error .= "; store view id is a " . gettype($product->store_view_id) . ", should be a string";
        }

        // category_ids
        if (!is_array($product->category_ids)) {
            $error .= "; category_ids is string, should be array of integers";
        } else {
            foreach ($product->category_ids as $id) {
                if (!preg_match('/\d+/', $id)) {
                    $error .= "; category_ids should be an array of integers";
                    break;
                }
            }
        }

        foreach ($config->eavAttributes as $eavAttribute) {

            $info = $attributeInfo[$eavAttribute];

            if (!property_exists($product, $eavAttribute)) {
                $product->$eavAttribute = null;
            }

            $value = $product->$eavAttribute;

            if (is_null($value)) {

                if ($info->isRequired) {
                    $error .= "; missing " . $eavAttribute;
                    continue;
                }

            } else {

                // convert all to trimmed string

                if (is_string($value)) {

                    $value = trim($value);

                    $product->$eavAttribute = $value;

                } elseif (is_integer($value)) {

                    if ($info->backendType != MetaData::TYPE_INTEGER) {
                        $error .= "; " . $eavAttribute . " is an integer (" . $value . "), should be a string";
                        continue;
                    }

                    $product->$eavAttribute = (string)$value;

                } elseif (is_object($value)) {
                    $error .= "; " . $eavAttribute . " is an object (" . get_class($value) . "), should be a string";
                    continue;
                } else {
                    $error .= "; " . $eavAttribute . " is a " . gettype($value) . ", should be a string";
                    continue;
                }

                // empty values

                if ($value === "") {
                    if ($info->isRequired) {
                        $error .= "; missing " . $eavAttribute;
                    } elseif (!in_array($info->backendType, [MetaData::TYPE_VARCHAR, MetaData::TYPE_TEXT])) {
                        $product->$eavAttribute = null;
                    }
                    continue;
                }

                // validate value

                switch ($info->backendType) {
                    case MetaData::TYPE_VARCHAR:
                        if (mb_strlen($value) > 255) {
                            $error .= "; " . $eavAttribute . " has " . mb_strlen($value) . " characters (max 255)";
                        }
                        break;
                    case MetaData::TYPE_TEXT:
                        if (strlen($value) > 65536) {
                            $error .= "; " . $eavAttribute . " has " . strlen($value) . " bytes (max 65536)";
                        }
                        break;
                    case MetaData::TYPE_DECIMAL:
                        if (!preg_match('/^\d{1,12}(\.\d{0,4})?$/', $value)) {
                            $error .= "; " . $eavAttribute . " is not a positive decimal number with dot (" . $value . ")";
                        }
                        break;
                    case MetaData::TYPE_DATETIME:
                        if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $value)) {
                            $error .= "; " . $eavAttribute . " is not a MySQL date time (" . $value . ")";
                        }
                        break;
                    case MetaData::TYPE_INTEGER:
                        if (!preg_match('/^-?\d+$/', $value)) {
                            $error .= "; " . $eavAttribute . " is not an integer (" . $value . ")";
                        } else {
                            // validate possible options
                            if ($info->frontendInput === MetaData::FRONTEND_SELECT) {
                                if (!array_key_exists($value, $info->optionValues)) {
//                                      $error .= "; illegal value for " . $eavAttribute . " status: (" . $value  . "), 3 (allowed = " . implode(", ", $info->optionValues) . ")";
                                }
                            }
                        }
                        break;
                }
            }
        }

        if ($error !== "") {
            $error = preg_replace("/^; /", "", $error);
        }

        return [$error == "", $error];
    }
}