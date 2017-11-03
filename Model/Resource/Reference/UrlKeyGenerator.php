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
        // collect the ids of a bunch of url keys that will be generated
        $urlKey2Id = $this->collectExistingUrlKeys($newProducts, $duplicateUrlKeyStrategy);

        foreach ($newProducts as $product) {

            if (is_string($product->url_key)) {

                // a url_key was specified, check if it exists

                if (isset($urlKey2Id[$product->store_view_id][$product->url_key])) {
                    $product->errors[] = "Url key already exists: " . $product->url_key;
                    $product->ok = false;
                }

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
        // collect the ids of a bunch of url keys that will be generated
        $urlKey2Id = $this->collectExistingUrlKeys($existingProducts, $duplicateUrlKeyStrategy);

        foreach ($existingProducts as $product) {

            if (is_string($product->url_key)) {

                // a url_key was specified, check if it exists

                if (isset($urlKey2Id[$product->store_view_id][$product->url_key])) {

                    // if so, does it belong to this product?

                    if ($urlKey2Id[$product->store_view_id][$product->url_key] != $product->id) {

                        $product->errors[] = "Url key already exists: " . $product->url_key;
                        $product->ok = false;
                    }

                }

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
     * @param Product $product
     * @param array $urlKey2Id
     * @param string $duplicateUrlKeyStrategy
     * @return string
     */
    protected function generateUrlKey(Product $product, array $urlKey2Id, string $urlKeyScheme, string $duplicateUrlKeyStrategy)
    {
        $suggestedUrlKey = $this->getStandardUrlKey($product, $urlKeyScheme);

        if (isset($urlKey2Id[$product->store_view_id][$suggestedUrlKey])) {

            $suggestedUrlKey = $this->getAlternativeUrlKey($product, $suggestedUrlKey, $duplicateUrlKeyStrategy);

            // we still need to check if that key has not been used before
            if (isset($urlKey2Id[$product->store_view_id][$suggestedUrlKey])) {

                $product->errors[] = "Generated url key already exists: " . $suggestedUrlKey;
                $product->ok = false;

                $suggestedUrlKey = null;
            }
        }

        return $suggestedUrlKey;
    }

    /**
     * @param array $products
     * @param string $duplicateUrlKeyStrategy
     * @return array
     */
    protected function collectExistingUrlKeys(array $products, string $duplicateUrlKeyStrategy)
    {
        $suggestedUrlKeys = [];

        // prepare the lookup of keys to be checked
        foreach ($products as $product) {

            $suggestedUrlKey = $this->getStandardUrlKey($product, $duplicateUrlKeyStrategy);

            $suggestedUrlKeys[] = $suggestedUrlKey;
            $suggestedUrlKeys[] = $this->getAlternativeUrlKey($product, $suggestedUrlKey, $duplicateUrlKeyStrategy);
        }

        $urlKey2Id = $this->getUrlKey2Id($suggestedUrlKeys);

        return $urlKey2Id;
    }

    protected function getStandardUrlKey(Product $product, $urlKeyScheme): string
    {
        if (($product->sku === null) || ($product->name === null)) {
            $suggestedUrlKey = "";
        } elseif ($urlKeyScheme == ImportConfig::URL_KEY_SCHEME_FROM_SKU) {
            $suggestedUrlKey = $this->nameToUrlKeyConverter->createUrlKeyFromName($product->sku);
        } else {
            $suggestedUrlKey = $this->nameToUrlKeyConverter->createUrlKeyFromName($product->name);
        }

        return $suggestedUrlKey;
    }

    protected function getAlternativeUrlKey(Product $product, $suggestedUrlKey, $duplicateUrlKeyStrategy): string
    {
        if ($suggestedUrlKey === "") {
            return "";
        }

        if ($duplicateUrlKeyStrategy == ImportConfig::DUPLICATE_KEY_STRATEGY_ADD_SKU) {
            $suggestedUrlKey = $suggestedUrlKey . '-' . $this->nameToUrlKeyConverter->createUrlKeyFromName($product->sku);
        } elseif ($duplicateUrlKeyStrategy == ImportConfig::DUPLICATE_KEY_STRATEGY_ADD_SERIAL) {
            $suggestedUrlKey = $suggestedUrlKey . '-' . substr(md5($product->sku), 0, 5);
        }

        // the database only allows this length
        $suggestedUrlKey = substr($suggestedUrlKey, 0, 255);

        return $suggestedUrlKey;
    }

    protected function getUrlKey2Id(array $urlKeys)
    {
        if (empty($urlKeys)) {
            return [];
        }

        $attributeId = $this->metaData->productEavAttributeInfo['url_key']->attributeId;

        $results = $this->db->fetchAllAssoc("
            SELECT `entity_id`, `store_id`, `value`
            FROM `{$this->metaData->productEntityTable}_varchar`
            WHERE 
                `attribute_id` = $attributeId AND
                `value` IN (" . $this->db->quoteSet($urlKeys) . ")
        ");

        $map = [];

        foreach ($results as $result) {
            $map[$result['store_id']][$result['value']] = $result['entity_id'];
        }

        return $map;
    }
}