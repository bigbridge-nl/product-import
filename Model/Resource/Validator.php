<?php

namespace BigBridge\ProductImport\Model\Resource;

use BigBridge\ProductImport\Model\Data\Product;
use BigBridge\ProductImport\Model\ImportConfig;

/**
 * @author Patrick van Bergen
 */
class Validator
{

    /** @var  MetaData */
    private $metaData;

    /** @var  ImportConfig */
    private $config;

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
        $attributeInfo = $this->metaData->attributeInfo;

#todo no need to check required fields for existing products

        $error = "";

        $product->sku = $sku = is_string($product->sku) ? trim($product->sku) : "";
        $product->attributeSetName = $attributeSetName = is_string($product->attributeSetName) ? trim($product->attributeSetName) : "";

        if ($sku === "") {
            $error .= "; missing sku";
        }

        if ($attributeSetName === "") {
            $error .= "; missing attribute set name";
        } elseif (!array_key_exists($attributeSetName, $this->metaData->attributeSetMap)) {
            $error .= "; unknown attribute set name: " . $attributeSetName;
        }

        foreach ($this->config->eavAttributes as $eavAttribute) {
            // this check be done once for config: if (array_key_exists($eavAttribute, $attributeInfo)) {

            $info = $attributeInfo[$eavAttribute];

            $value = $product->$eavAttribute;
            if (is_string($value)) {

                $value = trim($value);

                if ($info->isRequired && $value === "") {
                    $error .= "; missing " . $eavAttribute;
                }

                if ($info->backendType === MetaData::TYPE_DECIMAL) {
                    if (!preg_match('/^\d{1,12}(\.\d{0,4})?$/', $value)) {
                        $error .= "; " . $eavAttribute . " is not a decimal number (" . $value . ")";
                    }
                }

                $product->$eavAttribute = $value;

            } elseif (is_null($value)) {

                if ($info->isRequired) {
                    $error .= "; missing " . $eavAttribute;
                }

            } elseif (is_object($value)) {
                $error .= "; " . $eavAttribute . " is an object (" . get_class($value) . "), should be a string";
            } else {
                $error .= "; " . $eavAttribute . " is a " . gettype($value) . ", should be a string";
            }
        }

        if ($error !== "") {
            $error = preg_replace("/^; /", "", $error);
        }

        return [$error == "", $error];
    }
}