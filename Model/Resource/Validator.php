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
#todo no need to check required fields for existing products
        $ok = true;
        $error = "";
        $sep = "";

        $sku = is_string($product->sku) ? trim($product->sku) : "";
        $name = is_string($product->name) ? trim($product->name) : "";
        $attributeSetName = is_string($product->attributeSetName) ? trim($product->attributeSetName) : "";

        if ($sku === "") {
            $ok = false;
            $error .= $sep . "missing sku";
            $sep = "; ";
        }

        if ($name === "") {
            $ok = false;
            $error .= $sep . "missing name";
            $sep = "; ";
        }

        if ($attributeSetName === "") {
            $ok = false;
            $error .= $sep . "missing attribute set name";
            $sep = "; ";
        } elseif (!array_key_exists($attributeSetName, $this->metaData->attributeSetMap)) {
            $ok = false;
            $error .= $sep . "unknown attribute set name: " . $attributeSetName;
            $sep = "; ";
        }

        foreach ($this->config->eavAttributes as $eavAttribute) {

        }

        return [$ok, $error];
    }
}