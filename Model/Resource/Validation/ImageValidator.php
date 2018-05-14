<?php

namespace BigBridge\ProductImport\Model\Resource\Validation;

use BigBridge\ProductImport\Api\Data\Product;
use BigBridge\ProductImport\Model\Data\EavAttributeInfo;
use BigBridge\ProductImport\Model\Resource\MetaData;

/**
 * @author Patrick van Bergen
 */
class ImageValidator
{
    /** @var  MetaData */
    protected $metaData;

    public function __construct(MetaData $metaData)
    {
        $this->metaData = $metaData;
    }

    public function validateImages(Product $product)
    {
        foreach ($product->getStoreViews() as $storeView) {
            foreach ($storeView->getImageRoles() as $attributeCode => $image) {

                if (!array_key_exists($attributeCode, $this->metaData->productEavAttributeInfo)) {
                    $product->addError("Image role attribute does not exist: " . $attributeCode);
                } else {
                    $info = $this->metaData->productEavAttributeInfo[$attributeCode];
                    if ($info->frontendInput !== EavAttributeInfo::FRONTEND_MEDIA_IMAGE) {
                        $product->addError("Image role attribute input type is not media image: " . $attributeCode);
                    }
                }

            }
        }
    }
}