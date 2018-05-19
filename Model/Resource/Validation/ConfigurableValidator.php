<?php

namespace BigBridge\ProductImport\Model\Resource\Validation;

use BigBridge\ProductImport\Api\Data\ConfigurableProduct;
use BigBridge\ProductImport\Model\Data\EavAttributeInfo;
use BigBridge\ProductImport\Model\Resource\MetaData;

/**
 * @author Patrick van Bergen
 */
class ConfigurableValidator
{
    /** @var  MetaData */
    protected $metaData;

    public function __construct(
        MetaData $metaData)
    {
        $this->metaData = $metaData;
    }

    /**
     * @param ConfigurableProduct $product
     */
    public function validate(ConfigurableProduct $product)
    {
        $this->validateSuperAttributes($product);
        $this->validateVariants($product);
        $this->validateVariantSuperAttributeValues($product);
    }

    /**
     * @param ConfigurableProduct $product
     */
    protected function validateSuperAttributes(ConfigurableProduct $product)
    {
        if (empty($product->getSuperAttributeCodes())) {
            $product->addError("Specify at least 1 super attribute");
        }

        foreach ($product->getSuperAttributeCodes() as $superAttributeCode) {

            if (!array_key_exists($superAttributeCode, $this->metaData->productEavAttributeInfo)) {
                $product->addError("Attribute does not exist: " . $superAttributeCode);
            } else {
                $info = $this->metaData->productEavAttributeInfo[$superAttributeCode];
                if ($info->scope !== EavAttributeInfo::SCOPE_GLOBAL) {
                    $product->addError("Attribute does not have global scope: " . $superAttributeCode);
                }
                if ($info->frontendInput !== EavAttributeInfo::FRONTEND_SELECT) {
                    $product->addError("Attribute input type is not dropdown: " . $superAttributeCode);
                }
            }

        }
    }

    /**
     * @param ConfigurableProduct $product
     */
    protected function validateVariants(ConfigurableProduct $product)
    {
        if (empty($product->getVariants())) {
            $product->addError("Specify at least 1 variant");
        }

        $skus = [];

        foreach ($product->getVariants() as $variant) {
            if (!$variant->isOk()) {
                $skus[] = $variant->getSku();
            }
        }

        if (!empty($skus)) {
            $product->addError("These variants have errors: " . implode(', ', $skus));
        }
    }

    /**
     * @param ConfigurableProduct $product
     */
    protected function validateVariantSuperAttributeValues(ConfigurableProduct $product)
    {
        $configurations = [];

        foreach ($product->getVariants() as $variant) {

            $config = '';
            $sep = "";

            foreach ($product->getSuperAttributeCodes() as $superAttributeCode) {

                $value = $variant->global()->getAttribute($superAttributeCode);

                if ($value === null) {
                    $product->addError("Variant " . $variant->getSku() . " does not have a value for " . $superAttributeCode);
                } else {
                    $config .= $sep . $value;
                    $sep = "-";
                }
            }

            if ($config !== "") {
                if (array_key_exists($config, $configurations)) {
                    $product->addError("The variants " . $variant->getSku() . ' and ' . $configurations[$config]->getSku() .
                        " have the same combination of super attributes: " . $config);
                } else {
                    $configurations[$config] = $variant;
                }
            }

        }
    }
}