<?php

namespace BigBridge\ProductImport\Model\Resource\Resolver;

use BigBridge\ProductImport\Model\Resource\MetaData;

/**
 * @author Patrick van Bergen
 */
class OptionResolver
{
    /** @var MetaData */
    protected $metaData;

    public function __construct(MetaData $metaData)
    {
        $this->metaData = $metaData;
    }

    public function resolveOption(string $attributeCode, string $optionName, array $autoCreateOptionAttributes)
    {
        $error = "";
        $id = null;

        if (!array_key_exists($attributeCode, $this->metaData->productEavAttributeInfo)) {

            $error = "attribute code not found: " . $attributeCode;

        } else {

            $info = $this->metaData->productEavAttributeInfo[$attributeCode];

            if (!array_key_exists($optionName, $info->optionValues)) {

                if (in_array($attributeCode, $autoCreateOptionAttributes)) {
                    $id = $this->metaData->addAttributeOption($attributeCode, $optionName);
                } else {
                    $error = "option " . $optionName . " not found in attribute " . $attributeCode;
                }

            } else {
                $id = $info->optionValues[$optionName];
            }

        }

        return [$id, $error];
    }


    public function resolveOptions(string $attributeCode, array $optionNames, array $autoCreateOptionAttributes): array
    {
        $error = "";
        $ids = [];

        if (!array_key_exists($attributeCode, $this->metaData->productEavAttributeInfo)) {

            $error = "attribute code not found: " . $attributeCode;

        } else {

            $info = $this->metaData->productEavAttributeInfo[$attributeCode];

            $missingOptions = [];

            foreach ($optionNames as $optionName) {

                if (!array_key_exists($optionName, $info->optionValues)) {

                    if (in_array($attributeCode, $autoCreateOptionAttributes)) {
                        $ids[] = $this->metaData->addAttributeOption($attributeCode, $optionName);
                    } else {
                        $missingOptions[] = $optionName;
                    }

                } else {
                    $ids[] = $info->optionValues[$optionName];
                }
            }

            if (!empty($missingOptions)) {
                $error = "option(s) " . implode(', ', $missingOptions) . " not found in attribute " . $attributeCode;
            }

        }

        return [$ids, $error];
    }
}