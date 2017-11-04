<?php

namespace BigBridge\ProductImport\Model\Resource\Reference;

use BigBridge\ProductImport\Model\Data\Product;
use BigBridge\ProductImport\Model\Db\Magento2DbConnection;
use BigBridge\ProductImport\Model\GeneratedUrlKey;
use BigBridge\ProductImport\Model\ImportConfig;
use BigBridge\ProductImport\Model\Resource\MetaData;

/**
 * This class generates url_keys for products, based on their name (and if necessary, concatenated with id or sku)
 *
 * @author Patrick van Bergen
 */
class UrlKeyGenerator
{
    /** @var MetaData */
    protected $metaData;

    /** @var NameToUrlKeyConverter */
    protected $nameToUrlKeyConverter;

    /** @var Magento2DbConnection */
    protected $db;

    public function __construct(Magento2DbConnection $db, MetaData $metaData, NameToUrlKeyConverter $nameToUrlKeyConverter)
    {
        $this->metaData = $metaData;
        $this->nameToUrlKeyConverter = $nameToUrlKeyConverter;
        $this->db = $db;
    }

    /**
     * @param Product[] $newProducts
     * @param bool $autoCreateUrlKeysForNewProducts
     * @param string $urlKeyScheme
     * @param string $duplicateUrlKeyStrategy
     */
    public function createUrlKeysForNewProducts(array $newProducts, string $urlKeyScheme, string $duplicateUrlKeyStrategy)
    {
        if (empty($newProducts)) {
            return;
        }

        // collect the ids of a bunch of url keys that will be generated
        $urlKey2Id = $this->collectExistingUrlKeys($newProducts, $urlKeyScheme, $duplicateUrlKeyStrategy);

        foreach ($newProducts as $product) {

            if (is_string($product->url_key)) {

                // a url_key was specified, check if it exists

                if (array_key_exists($product->store_view_id, $urlKey2Id) && array_key_exists($product->url_key, $urlKey2Id[$product->store_view_id])) {
                    $product->errors[] = "Url key already exists: " . $product->url_key;
                    $product->ok = false;
                }

                // add the new key to the local map
                $urlKey2Id[$product->store_view_id][$product->url_key] = $product->id;

            } elseif ($product->url_key instanceof GeneratedUrlKey) {

                // no url_key was specified
                // generate a key. this may cause product to error

                $product->url_key = $this->generateUrlKey($product, $urlKey2Id, $urlKeyScheme, $duplicateUrlKeyStrategy);

                // add the new key to the local map
                if ($product->url_key !== null) {
                    $urlKey2Id[$product->store_view_id][$product->url_key] = $product->id;
                }
            }
        }
    }

    /**
     * @param Product[] $existingProducts
     * @param bool $autoCreateUrlKeysForExistingProducts
     * @param string $urlKeyScheme
     * @param string $duplicateUrlKeyStrategy
     */
    public function createUrlKeysForExistingProducts(array $existingProducts, string $urlKeyScheme, string $duplicateUrlKeyStrategy)
    {
        if (empty($existingProducts)) {
            return;
        }

        // collect the ids of a bunch of url keys that will be generated
        $urlKey2Id = $this->collectExistingUrlKeys($existingProducts, $urlKeyScheme, $duplicateUrlKeyStrategy);

        foreach ($existingProducts as $product) {

            if (is_string($product->url_key)) {

                // a url_key was specified, check if it exists

                if (array_key_exists($product->store_view_id, $urlKey2Id) && array_key_exists($product->url_key, $urlKey2Id[$product->store_view_id])) {

                    // if so, does it belong to this product?

                    if ($urlKey2Id[$product->store_view_id][$product->url_key] != $product->id) {

                        $product->errors[] = "Url key already exists: " . $product->url_key;
                        $product->ok = false;
                    }

                }

            } elseif ($product->url_key instanceof GeneratedUrlKey) {

                // no url_key was specified

                // check if the existing url key is valid
                $existingUrlKey = $this->checkExistingUrlKey($product, $urlKey2Id, $urlKeyScheme, $duplicateUrlKeyStrategy);
                if ($existingUrlKey !== false) {

                    $product->url_key = $existingUrlKey;
                    $urlKey2Id[$product->store_view_id][$product->url_key] = $product->id;

                } else {

                    // generate a key. this may cause product to error

                    $product->url_key = $this->generateUrlKey($product, $urlKey2Id, $urlKeyScheme, $duplicateUrlKeyStrategy);

                    // add the new key to the local map
                    if ($product->url_key !== null) {
                        $urlKey2Id[$product->store_view_id][$product->url_key] = $product->id;
                    }
                }
            }
        }
    }

    /**
     * Checks if the product's existing url key is valid
     * @return string|false
     */
    protected function checkExistingUrlKey(Product $product, array $urlKey2Id, string $urlKeyScheme, string $duplicateUrlKeyStrategy)
    {
        if (!array_key_exists($product->store_view_id, $urlKey2Id)) {
            return false;
        }

        $existingUrlKey = array_search($product->id, $urlKey2Id[$product->store_view_id]);
        if ($existingUrlKey === false) {
            return false;
        }

        $suggestedUrlKey = $this->getStandardUrlKey($product, $urlKeyScheme);

        if ($duplicateUrlKeyStrategy === ImportConfig::DUPLICATE_KEY_STRATEGY_ADD_SKU) {
            if ($existingUrlKey === $suggestedUrlKey) {
                return $existingUrlKey;
            } elseif ($existingUrlKey === ($suggestedUrlKey . '-' . $this->nameToUrlKeyConverter->createUrlKeyFromName($product->sku))) {
                return $existingUrlKey;
            };
        } elseif ($duplicateUrlKeyStrategy === ImportConfig::DUPLICATE_KEY_STRATEGY_ADD_SERIAL) {
            if (preg_match("/^{$suggestedUrlKey}(-\d+)?$/", $existingUrlKey)) {
                return $existingUrlKey;
            }
        }

        return false;
    }

    /**
     * @param Product $product
     * @param array $urlKey2Id
     * @param string $duplicateUrlKeyStrategy
     * @return string
     */
    protected function generateUrlKey(Product $product, array $urlKey2Id, string $urlKeyScheme, string $duplicateUrlKeyStrategy)
    {
        $suggestedUrlKey = $this->getStandardUrlKey($product, $urlKeyScheme);

        if (array_key_exists($product->store_view_id, $urlKey2Id) && array_key_exists($suggestedUrlKey, $urlKey2Id[$product->store_view_id])) {

            $suggestedUrlKey = $this->getAlternativeUrlKey($product, $suggestedUrlKey, $duplicateUrlKeyStrategy);

            // we still need to check if that key has not been used before
            if (array_key_exists($product->store_view_id, $urlKey2Id) && array_key_exists($suggestedUrlKey, $urlKey2Id[$product->store_view_id])) {

                // check if this generated url key belongs to the product
                if (is_null($product->id) || $urlKey2Id[$product->store_view_id][$suggestedUrlKey] != $product->id) {

                    $product->errors[] = "Generated url key already exists: " . $suggestedUrlKey;
                    $product->ok = false;

                    $suggestedUrlKey = null;
                }
            }
        }

        return $suggestedUrlKey;
    }

    /**
     * @param array $products
     * @param string $duplicateUrlKeyStrategy
     * @return array
     */
    protected function collectExistingUrlKeys(array $products, string $urlKeyScheme, string $duplicateUrlKeyStrategy)
    {
        $suggestedUrlKeys = [];

        // prepare the lookup of keys to be checked
        foreach ($products as $product) {

            $suggestedUrlKey = $this->getStandardUrlKey($product, $urlKeyScheme);

            if ($suggestedUrlKey !== "") {
                $suggestedUrlKeys[] = $suggestedUrlKey;
                $suggestedUrlKeys[] = $this->getAlternativeUrlKeyProductionRule($product, $suggestedUrlKey, $duplicateUrlKeyStrategy);
            }
        }

        $urlKey2Id = $this->getUrlKey2Id($suggestedUrlKeys, $duplicateUrlKeyStrategy);

        return $urlKey2Id;
    }

    protected function getStandardUrlKey(Product $product, string $urlKeyScheme): string
    {
        if (($product->sku === null) || ($product->name === null)) {
            $suggestedUrlKey = "";
        } elseif (is_string($product->url_key)) {
            $suggestedUrlKey = $product->url_key;
        } elseif ($urlKeyScheme == ImportConfig::URL_KEY_SCHEME_FROM_SKU) {
            $suggestedUrlKey = $this->nameToUrlKeyConverter->createUrlKeyFromName($product->sku);
        } else {
            $suggestedUrlKey = $this->nameToUrlKeyConverter->createUrlKeyFromName($product->name);
        }

        return $suggestedUrlKey;
    }

    protected function getAlternativeUrlKey(Product $product, string $suggestedUrlKey, string $duplicateUrlKeyStrategy): string
    {
        if ($suggestedUrlKey === "") {
            return "";
        }

        if ($duplicateUrlKeyStrategy == ImportConfig::DUPLICATE_KEY_STRATEGY_ADD_SKU) {
            $suggestedUrlKey = $suggestedUrlKey . '-' . $this->nameToUrlKeyConverter->createUrlKeyFromName($product->sku);
        } elseif ($duplicateUrlKeyStrategy == ImportConfig::DUPLICATE_KEY_STRATEGY_ADD_SERIAL) {
            $suggestedUrlKey = $suggestedUrlKey . '-' . $this->getNextSerial($suggestedUrlKey, $product->store_view_id);
        }

        // the database only allows this length
        $suggestedUrlKey = substr($suggestedUrlKey, 0, 255);

        return $suggestedUrlKey;
    }

    protected function getAlternativeUrlKeyProductionRule(Product $product, string $suggestedUrlKey, string $duplicateUrlKeyStrategy): string
    {
        if ($suggestedUrlKey === "") {
            return "";
        }

        if ($duplicateUrlKeyStrategy == ImportConfig::DUPLICATE_KEY_STRATEGY_ADD_SKU) {
            $suggestedUrlKey = $suggestedUrlKey . '-' . $this->nameToUrlKeyConverter->createUrlKeyFromName($product->sku);
        } elseif ($duplicateUrlKeyStrategy == ImportConfig::DUPLICATE_KEY_STRATEGY_ADD_SERIAL) {
            $suggestedUrlKey = $suggestedUrlKey . '-%';
        }

        // the database only allows this length
        $suggestedUrlKey = substr($suggestedUrlKey, 0, 255);

        return $suggestedUrlKey;
    }

    protected function getUrlKey2Id(array $urlKeys, $duplicateUrlKeyStrategy)
    {
        if (empty($urlKeys)) {
            return [];
        }

        $attributeId = $this->metaData->productEavAttributeInfo['url_key']->attributeId;

        if ($duplicateUrlKeyStrategy === ImportConfig::DUPLICATE_KEY_STRATEGY_ADD_SERIAL) {
            $entries = [];
            foreach (array_unique($urlKeys) as $urlKey) {
                $entries[] = "(`value` LIKE " . $this->db->quote($urlKey) . ")";
            }
            $keyClause = implode(" OR ", $entries);
        } else {
            $keyClause = "`value` IN (" . $this->db->quoteSet($urlKeys) . ")";
        }

        $results = $this->db->fetchAllAssoc("
            SELECT `entity_id`, `store_id`, `value`
            FROM `{$this->metaData->productEntityTable}_varchar`
            WHERE 
                `attribute_id` = $attributeId AND
                {$keyClause}
        ");

        $map = [];

        foreach ($results as $result) {
            $map[$result['store_id']][$result['value']] = $result['entity_id'];
        }

        return $map;
    }

    protected function getNextSerial($urlKey, $storeViewId)
    {
        $attributeId = $this->metaData->productEavAttributeInfo['url_key']->attributeId;

        $results = $this->db->fetchSingleColumn("
            SELECT `value`
            FROM `{$this->metaData->productEntityTable}_varchar`
            WHERE 
                `attribute_id` = $attributeId AND
                `store_id` = $storeViewId AND
                `value` LIKE " . $this->db->quote($urlKey . '-%') . "
        ");

        $max = 0;
        $exp = '/^' . $urlKey . '-(\d+)$/';

        foreach ($results as $result) {
            if (preg_match($exp, $result, $matches)) {
                $max = max($max, (int)$matches[1]);
            }
        }

        return $max + 1;
    }
}