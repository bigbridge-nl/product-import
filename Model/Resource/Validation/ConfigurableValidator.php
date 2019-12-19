<?php

namespace BigBridge\ProductImport\Model\Resource\Validation;

use BigBridge\ProductImport\Api\Data\ConfigurableProduct;
use BigBridge\ProductImport\Api\Data\Product;
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
     * @param Product[] $batchProducts
     */
    public function validate(ConfigurableProduct $product, array $batchProducts)
    {
        $this->validateSuperAttributes($product);
        $this->validateVariants($product, $batchProducts);
    }

    /**
     * @param ConfigurableProduct $product
     */
    protected function validateSuperAttributes(ConfigurableProduct $product)
    {
        if ($product->getSuperAttributeCodes() === null) {

            if ($product->id === null) {
                $product->addError("specify the super attributes with setSuperAttributeCodes()");
                return;
            }

        } else {

            foreach ($product->getSuperAttributeCodes() as $superAttributeCode) {

                if (!array_key_exists($superAttributeCode, $this->metaData->productEavAttributeInfo)) {
                    $product->addError("attribute does not exist: " . $superAttributeCode);
                } else {
                    $info = $this->metaData->productEavAttributeInfo[$superAttributeCode];
                    if ($info->scope !== EavAttributeInfo::SCOPE_GLOBAL) {
                        $product->addError("attribute does not have global scope: " . $superAttributeCode);
                    }
                    if ($info->frontendInput !== EavAttributeInfo::FRONTEND_SELECT) {
                        $product->addError("attribute input type is not dropdown: " . $superAttributeCode);
                    }
                }
            }
        }
    }

    /**
     * @param ConfigurableProduct $product
     */
    protected function validateVariants(ConfigurableProduct $product, array $batchProducts)
    {
        if ($product->id === null && $product->getVariantSkus() === null) {
            $product->addError("specify the variants with setVariantSkus()");
        }

        if ($product->getVariantSkus() !== null) {
            foreach ($product->getVariantSkus() as $variantSku) {
                if (array_key_exists($variantSku, $batchProducts)) {
                    if (!$batchProducts[$variantSku]->isOk()) {
                        $product->addError("A member product is invalid: " . $variantSku);
                        break;
                    }
                }
            }
        }
    }
}
