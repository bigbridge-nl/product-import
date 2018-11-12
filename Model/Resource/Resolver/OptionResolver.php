<?php

namespace BigBridge\ProductImport\Model\Resource\Resolver;

use BigBridge\ProductImport\Model\Persistence\Magento2DbConnection;
use BigBridge\ProductImport\Model\Resource\MetaData;

/**
 * @author Patrick van Bergen
 */
class OptionResolver
{
    /** @var Magento2DbConnection */
    protected $db;

    /** @var MetaData */
    protected $metaData;

    /** @var array */
    protected $allOptionValues = [];

    public function __construct(
        Magento2DbConnection $db,
        MetaData $metaData
    )
    {
        $this->db = $db;
        $this->metaData = $metaData;
    }

    public function clearCache()
    {
        $this->allOptionValues = [];
    }

    protected function loadOptionValues(string $attributeCode)
    {
        if (!array_key_exists($attributeCode, $this->allOptionValues)) {

            $this->allOptionValues[$attributeCode] = $this->db->fetchMap("
                SELECT V.`value`, O.`option_id`
                FROM {$this->metaData->attributeTable} A
                INNER JOIN {$this->metaData->attributeOptionTable} O ON O.attribute_id = A.attribute_id
                INNER JOIN {$this->metaData->attributeOptionValueTable} V ON V.option_id = O.option_id
                WHERE A.`attribute_code` = ? AND A.`entity_type_id` = ? AND V.store_id = 0
            ", [
                $attributeCode,
                $this->metaData->productEntityTypeId
            ]);
        }
    }

    protected function addAttributeOption(string $attributeCode, string $optionName): int
    {
        $attributeId = $this->metaData->productEavAttributeInfo[$attributeCode]->attributeId;

        $sortOrder = count($this->allOptionValues[$attributeCode]) + 1;

        $this->db->execute("
            INSERT INTO {$this->metaData->attributeOptionTable}
            SET attribute_id = ?, sort_order = ?
        ", [
            $attributeId,
            $sortOrder
        ]);

        $optionId = $this->db->getLastInsertId();

        // update cached values
        $this->allOptionValues[$attributeCode][$optionName] = $optionId;

        $this->db->execute("
            INSERT INTO {$this->metaData->attributeOptionValueTable}
            SET option_id = ?, store_id = 0, value = ?
        ", [
            $optionId,
            $optionName
        ]);

        return $optionId;
    }

    public function resolveOption(string $attributeCode, string $optionName, array $autoCreateOptionAttributes)
    {
        $error = "";
        $id = null;

        if (!array_key_exists($attributeCode, $this->metaData->productEavAttributeInfo)) {
            $error = "attribute code not found: " . $attributeCode;
        } else {

            // lazy load option values
            $this->loadOptionValues($attributeCode);

            if (!array_key_exists($optionName, $this->allOptionValues[$attributeCode])) {

                if (in_array($attributeCode, $autoCreateOptionAttributes)) {
                    $id = $this->addAttributeOption($attributeCode, $optionName);
                } else {
                    $error = "option '" . $optionName . "' not found in attribute '" . $attributeCode . "'";
                }

            } else {
                $id = $this->allOptionValues[$attributeCode][$optionName];
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

            // lazy load option values
            $this->loadOptionValues($attributeCode);

            $missingOptions = [];
            foreach ($optionNames as $optionName) {

                if (!array_key_exists($optionName, $this->allOptionValues[$attributeCode])) {
                    if (in_array($attributeCode, $autoCreateOptionAttributes)) {
                        $ids[] = $this->addAttributeOption($attributeCode, $optionName);
                    } else {
                        $missingOptions[] = $optionName;
                    }
                } else {
                    $ids[] = $this->allOptionValues[$attributeCode][$optionName];
                }
            }

            if (!empty($missingOptions)) {
                $error = "option(s) " . implode(', ', $missingOptions) . " not found in attribute " . $attributeCode;
            }
        }

        return [$ids, $error];
    }
}