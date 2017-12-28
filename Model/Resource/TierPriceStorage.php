<?php

namespace BigBridge\ProductImport\Model\Resource;

use BigBridge\ProductImport\Api\Product;
use BigBridge\ProductImport\Model\Db\Magento2DbConnection;

/**
 * @author Patrick van Bergen
 */
class TierPriceStorage
{
    /** @var  Magento2DbConnection */
    protected $db;

    /** @var  MetaData */
    protected $metaData;

    public function __construct(
        Magento2DbConnection $db,
        MetaData $metaData)
    {
        $this->db = $db;
        $this->metaData = $metaData;
    }

    /**
     * @param Product[] $products
     */
    public function insertTierPrices(array $products)
    {
        $this->upsertTierPrices($products);
    }

    /**
     * @param Product[] $products
     */
    public function updateTierPrices(array $products)
    {
        // updating tier prices relies on the unique key (`entity_id`,`all_groups`,`customer_group_id`,`qty`,`website_id`)
        // this handles the inserts and updates
        // the deletes are handled by an diff on the serialized tier price data

        $this->upsertTierPrices($products);

        $existingTierPricesSerialized = $this->getExistingTierPrices($products);
        $newTierPrices = $this->getExistingTierPrices($products);
        $removeList = array_diff_key($existingTierPricesSerialized, $newTierPrices);
        $this->removeTierPricesByValue($removeList);
    }

    /**
     * @param Product[] $products
     */
    protected function upsertTierPrices(array $products)
    {
        $values = [];

        foreach ($products as $product) {

            $tierPrices = $product->getTierPrices();
            if ($tierPrices !== null) {

                $entityId = $product->id;

                foreach ($tierPrices as $tierPrice) {
                    $allGroups = (int)($tierPrice->getCustomerGroupId() === null);
                    $customerGroupId = (int)$tierPrice->getCustomerGroupId();
                    $qty = $tierPrice->getQuantity();
                    $value = $tierPrice->getValue();
                    $websiteId = $tierPrice->getWebsiteId();
                    $values[] = "({$entityId}, {$allGroups}, {$customerGroupId}, {$qty}, {$value}, {$websiteId})";
                }
            }
        }

        if (!empty($values)) {
            $this->db->execute("
                INSERT INTO `{$this->metaData->tierPriceTable}` (`entity_id`, `all_groups`, `customer_group_id`, `qty`, `value`, `website_id`)
                VALUES " . implode(', ', $values) . "
                ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)
            ");
        }
    }

    protected function removeTierPricesByValue(array $tierPriceValueIds)
    {
        if (empty($tierPriceValueIds)) {
            return;
        }

        $this->db->execute("
            DELETE FROM `{$this->metaData->tierPriceTable}`
            WHERE `value_id` IN (" . implode(',', $tierPriceValueIds) . ")
        ");
    }

    protected function getExistingTierPrices(array $products)
    {
        if (empty($products)) {
            return [];
        }

        $productIds = array_column($products, 'id');

        $tierPrices = $this->db->fetchMap("
            SELECT CONCAT(' ', `entity_id`, `all_groups`, `customer_group_id`, `qty`, `value`, `website_id`), `value_id`
            FROM `{$this->metaData->tierPriceTable}`
            WHERE `entity_id` IN (" . implode(', ', $productIds) . ")
        ");

        return $tierPrices;
    }

    /**
     * @param Product[] $products
     * @return array
     */
    protected function getNewTierPrices(array $products)
    {
        $tierPrices = [];

        foreach ($products as $product) {
            foreach ($product->getTierPrices() as $tierPrice) {
                $serialized = sprintf("%s %s %s %s %s %s",
                    $product->id,
                    (int)($tierPrice->getCustomerGroupId() === null),
                    (int)$tierPrice->getCustomerGroupId(),
                    $tierPrice->getQuantity(),
                    $tierPrice->getValue(),
                    $tierPrice->getWebsiteId()
                );

                $tierPrices[$serialized] = $tierPrice;
            }
        }

        return $tierPrices;
    }
}