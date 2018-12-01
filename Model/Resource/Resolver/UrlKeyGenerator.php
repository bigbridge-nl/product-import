<?php

namespace BigBridge\ProductImport\Model\Resource\Resolver;

use BigBridge\ProductImport\Api\Data\Product;
use BigBridge\ProductImport\Api\Data\ProductStoreView;
use BigBridge\ProductImport\Model\Persistence\Magento2DbConnection;
use BigBridge\ProductImport\Model\Data\GeneratedUrlKey;
use BigBridge\ProductImport\Api\ImportConfig;
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
     * @param Product[] $products
     * @param string $urlKeyScheme
     * @param string $duplicateUrlKeyStrategy
     */
    public function resolveAndValidateUrlKeys(array $products, string $urlKeyScheme, string $duplicateUrlKeyStrategy)
    {
        $newUrlKeys = [];

        foreach ($products as $product) {
            foreach ($product->getStoreViews() as $code => $storeView) {

                $storeViewId = $storeView->getStoreViewId();

                if ($storeViewId === null) {
                    $storeView->setUrlKey(null);
                    continue;
                }

                $urlKey = $storeView->getUrlKey();

                if (is_string($urlKey)) {

                    // check if url_key already belongs to another product
                    list($found, $productId) = $this->findUrlKeyProductId($urlKey, $storeViewId, $newUrlKeys);

                    // if found and
                    //  - url key was used in this bunch, or
                    //  - is located in the database, and is from a different product
                    if ($found && (($productId === null) || ($productId !== $product->id))) {
                        $product->addError("url key already exists: " . $urlKey . " in store view " . $code);
                    }

                } elseif ($urlKey instanceof GeneratedUrlKey) {

                    // generated key for existing product? just take the existing value!
                    if ($urlKey = $this->getAcceptableExistingUrlKey($product->id, $storeView, $urlKeyScheme)) {

                        $storeView->setUrlKey($urlKey);

                    } else {
                        // generate a key
                        $urlKey = $this->generateUrlKey($storeView, $urlKeyScheme, $duplicateUrlKeyStrategy, $newUrlKeys);
                        $storeView->setUrlKey($urlKey);

                    }
                }

                if ($urlKey !== null) {
                    $newUrlKeys[$storeViewId][$urlKey] = true;
                }
            }
        }
    }

    /**
     * Checks if the product already has a url key and if its basic part matches the url key schema
     *
     * @param int|null $productId
     * @param ProductStoreView $storeView
     * @param $urlKeyScheme
     * @return false|string
     */
    protected function getAcceptableExistingUrlKey($productId, ProductStoreView $storeView, $urlKeyScheme)
    {
        $storeViewId = $storeView->getStoreViewId();

        if ($productId !== null) {
            $urlKey = $this->getProductUrlKey($productId, $storeViewId);
            if ($urlKey !== null) {

                $pattern = "/^" . $this->getBasicGeneratedUrlKey($storeView, $urlKeyScheme) . "/";

                // check if the existing key has the right base name
                if (preg_match($pattern, $urlKey)) {
                    return $urlKey;
                }
            }
        }

        return false;
    }

    /**
     * @param ProductStoreView $storeView
     * @param string $urlKeyScheme
     * @param string $duplicateUrlKeyStrategy
     * @param array $newUrlKeys
     * @return string|null
     */
    protected function generateUrlKey(ProductStoreView $storeView, string $urlKeyScheme, string $duplicateUrlKeyStrategy, array $newUrlKeys)
    {
        $urlKey = $this->getBasicGeneratedUrlKey($storeView, $urlKeyScheme);

        if ($urlKey !== null) {
            $storeViewId = $storeView->getStoreViewId();

            if ($this->urlKeyExists($urlKey, $storeViewId, $newUrlKeys)) {
                $urlKey = $this->getExtendedGeneratedUrlKey($urlKey, $storeView, $duplicateUrlKeyStrategy, $newUrlKeys);
            }
        }

        return $urlKey;
    }

    /**
     * @param ProductStoreView $storeView
     * @param string $urlKeyScheme
     * @return null|string
     */
    protected function getBasicGeneratedUrlKey(ProductStoreView $storeView, string $urlKeyScheme)
    {
        if ($urlKeyScheme == ImportConfig::URL_KEY_SCHEME_FROM_SKU) {

            $suggestedUrlKey = $this->nameToUrlKeyConverter->createUrlKeyFromName($storeView->parent->getSku());

        } else {

            $name = $storeView->getName();
            if ($name === null || $name === "") {

                $storeView->parent->addError("url key is based on name and product has no name in store view");
                $suggestedUrlKey = null;

            } else {
                $suggestedUrlKey = $this->nameToUrlKeyConverter->createUrlKeyFromName($name);
            }
        }

        return $suggestedUrlKey;
    }

    protected function getExtendedGeneratedUrlKey(string $urlKey, ProductStoreView $storeView, string $duplicateUrlKeyStrategy, array $newUrlKeys)
    {
        if ($duplicateUrlKeyStrategy === ImportConfig::DUPLICATE_KEY_STRATEGY_ADD_SKU) {

            $urlKey .= '-' . $this->nameToUrlKeyConverter->createUrlKeyFromName($storeView->parent->getSku());

            // url key may still not be unique
            if ($this->urlKeyExists($urlKey, $storeView->getStoreViewId(), $newUrlKeys)) {
                $storeView->parent->addError("generated url key already exists, even when the sku is added: " . $urlKey);
                $urlKey = null;
            }

        } elseif ($duplicateUrlKeyStrategy === ImportConfig::DUPLICATE_KEY_STRATEGY_ADD_SERIAL) {

            $postfix = 1;

            do {
                $postfixedUrlKey = $urlKey . '-' . $postfix;
                $postfix++;
            } while ($this->urlKeyExists($postfixedUrlKey, $storeView->getStoreViewId(), $newUrlKeys));

            $urlKey = $postfixedUrlKey;

        } elseif ($duplicateUrlKeyStrategy === ImportConfig::DUPLICATE_KEY_STRATEGY_ALLOW) {

            // no change

        } else {

            $storeView->parent->addError("generated url key already exists: " . $urlKey);
            $urlKey = null;

        }

        return $urlKey;
    }

    /**
     * @param string $urlKey
     * @param int $storeViewId
     * @param array $newUrlKeys
     * @return bool
     */
    protected function urlKeyExists(string $urlKey, int $storeViewId, array $newUrlKeys)
    {
        list($found,) = $this->findUrlKeyProductId($urlKey, $storeViewId, $newUrlKeys);

        return $found;
    }

    /**
     * @param string $urlKey
     * @param int $storeViewId
     * @param array $newUrlKeys
     * @return array
     */
    protected function findUrlKeyProductId(string $urlKey, int $storeViewId, array $newUrlKeys)
    {
        if (array_key_exists($storeViewId, $newUrlKeys) && array_key_exists($urlKey, $newUrlKeys[$storeViewId])) {
            return [true, null];
        }

        $productId = $this->getUrlKeyProductId($urlKey, $storeViewId);
        if ($productId !== null) {
            return [true, $productId];
        }

        return [false, null];
    }

    /**
     * @param string $urlKey
     * @param int $storeViewId
     * @return null|string
     */
    protected function getUrlKeyProductId(string $urlKey, int $storeViewId)
    {
        $attributeId = $this->metaData->productEavAttributeInfo['url_key']->attributeId;

        $result = $this->db->fetchSingleCell("
            SELECT `entity_id`
            FROM `{$this->metaData->productEntityTable}_varchar`
            WHERE 
                `attribute_id` = ? AND
                `store_id` = ? AND
                `value` = ?
        ", [
            $attributeId,
            $storeViewId,
            $urlKey
        ]);

        return $result;
    }

    /**
     * @param string $productId
     * @param int $storeViewId
     * @return null|string
     */
    protected function getProductUrlKey(string $productId, int $storeViewId)
    {
        $attributeId = $this->metaData->productEavAttributeInfo['url_key']->attributeId;

        $result = $this->db->fetchSingleCell("
            SELECT `value`
            FROM `{$this->metaData->productEntityTable}_varchar`
            WHERE 
                `attribute_id` = ? AND
                `store_id` = ? AND
                `entity_id` = ?
        ", [
            $attributeId,
            $storeViewId,
            $productId
        ]);

        return $result;
    }
}