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

    /** @var  ImportConfig */
    protected $config;

    public function __construct(MetaData $metaData)
    {
        $this->metaData = $metaData;
    }

    public function setConfig(ImportConfig $config)
    {
        $this->config = $config;
    }

    /**
     * Checks $product for all known requirements.
     *
     * @param Product $product
     * @return array An array with [ok, error]
     */
    public function validate(Product $product)
    {
        $attributeInfo = $this->metaData->eavAttributeInfo;

        $error = "";

        // sku
        $sku = $product->sku;
        if (is_string($sku)) {
            $product->sku = $sku = trim($product->sku);
            if ($sku === "") {
                $error .= "; missing sku";
            } elseif (mb_strlen($sku) > self::SKU_MAX_LENGTH) {
                $error .= "; sku has " . mb_strlen($sku) . ' characters (max ' . self::SKU_MAX_LENGTH . ")";
            }
        } elseif (is_null($sku)) {
            $error .= "; missing sku";
        } else {
            $error .= "; sku is a " . gettype($sku) . ", should be a string";
        }

        // attribute set name
        $attributeSetName = $product->attribute_set_name;
        if (is_string($attributeSetName)) {
            $product->attribute_set_name = $attributeSetName = trim($product->attribute_set_name);
            if ($attributeSetName === "") {
                $error .= "; missing attribute set name";
            } elseif (!array_key_exists($attributeSetName, $this->metaData->attributeSetMap)) {
                $error .= "; unknown attribute set name: " . $attributeSetName;
            }
        } elseif (is_null($attributeSetName)) {
            $error .= "; missing attribute set name";
        } else {
            $error .= "; attribute set name is a " . gettype($attributeSetName) . ", should be a string";
        }

        // store view code
        $storeViewCode = $product->store_view_code;
        if (is_string($storeViewCode)) {
            $product->store_view_code = $storeViewCode = trim($storeViewCode);
            if ($storeViewCode === "") {
                $error .= "; missing store view code";
            } elseif (!array_key_exists($storeViewCode, $this->metaData->storeViewMap)) {
                $error .= "; unknown store view code: " . $storeViewCode;
            }
        } elseif (is_null($storeViewCode)) {
            $error .= "; missing store view code";
        } else {
            $error .= "; store view code is a " . gettype($storeViewCode) . ", should be a string";
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

        foreach ($this->config->eavAttributes as $eavAttribute) {

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