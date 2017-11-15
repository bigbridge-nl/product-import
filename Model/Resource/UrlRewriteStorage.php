<?php

namespace BigBridge\ProductImport\Model\Resource;

use BigBridge\ProductImport\Model\Data\Product;
use BigBridge\ProductImport\Model\Db\Magento2DbConnection;
use BigBridge\ProductImport\Model\Resource\Serialize\ValueSerializer;

/**
 * @author Patrick van Bergen
 */
class UrlRewriteStorage
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

    public function insertRewrites(array $products, ValueSerializer $valueSerializer)
    {
        $newRewriteValues = $this->getNewRewriteValues($products);

        $this->writeUrlRewrites($newRewriteValues, $valueSerializer, true);
    }

    public function updateRewrites(array $products, array $existingValues, ValueSerializer $valueSerializer)
    {
        $changedProducts = $this->getChangedProducts($products, $existingValues);

        $newRewriteValues = $this->getNewRewriteValues($changedProducts);

        $this->rewriteExistingRewrites($newRewriteValues, $valueSerializer);
    }

    /**
     * @param Product[] $products
     * @param array $existingValues
     * @return array
     */
    protected function getChangedProducts(array $products, array $existingValues)
    {
        if (empty($products)) {
            return [];
        }

        $changedProducts = [];
        foreach ($products as $product) {
            if (array_key_exists($product->store_view_id, $existingValues) && array_key_exists($product->id, $existingValues[$product->store_view_id])) {

                $existingDatum = $existingValues[$product->store_view_id][$product->id];

                if ($product->url_key != $existingDatum['url_key']) {
                    $changedProducts[] = $product;
                } elseif (array_diff($product->category_ids, $existingDatum['category_ids']) || array_diff($existingDatum['category_ids'], $product->category_ids)) {
                    $changedProducts[] = $product;
                }

            } else {
                $changedProducts[] = $product;
            }
        }

        return $changedProducts;
    }

    /**
     * @param UrlRewriteInfo[] $urlRewrites
     * @param ValueSerializer $valueSerializer
     * @return array
     */
    protected function getExistingUrlRewriteData(array $urlRewrites, ValueSerializer $valueSerializer)
    {
        // prepare information of existing rewrites
        $productIds = [];
        foreach ($urlRewrites as $urlRewriteInfo) {
            $productIds[$urlRewriteInfo->storeId][$urlRewriteInfo->productId] = $urlRewriteInfo->productId;
        }

        $data = [];
        foreach ($productIds as $storeId => $ids) {

            $oldUrlRewrites = $this->db->fetchAllAssoc("
                SELECT `url_rewrite_id`, `entity_id`, `request_path`, `target_path`, `redirect_type`, `metadata`
                FROM `{$this->metaData->urlRewriteTable}`
                WHERE
                    store_id = $storeId AND `entity_id` IN (" . implode(',', $ids) . ")
            ");

            foreach ($oldUrlRewrites as $oldUrlRewrite) {

                $categoryId = $valueSerializer->extract($oldUrlRewrite['metadata'], 'category_id');
                $key = $oldUrlRewrite['entity_id'] . '/' . $categoryId;

                $data[$storeId][$key][] = $oldUrlRewrite;
            }
        }

        return $data;
    }

    protected function getNewRewriteValues(array $products): array
    {
        if (empty($products)) {
            return [];
        }

        // all store view ids, without 0
        $allStoreIds = array_diff($this->metaData->storeViewMap, ['0']);

        $productIds = array_column($products, 'id');
        $attributeId = $this->metaData->productEavAttributeInfo['url_key']->attributeId;

        $results = $this->db->fetchAllAssoc("
            SELECT `entity_id`, `store_id`, `value` AS `url_key`
            FROM `{$this->metaData->productEntityTable}_varchar`
            WHERE
                `attribute_id` = {$attributeId} AND
                `entity_id` IN (" . implode(',', $productIds) . ")
        ");

        $urlKeys = [];
        foreach ($results as $result) {
            $productId = $result['entity_id'];
            $storeId = $result['store_id'];
            $urlKey = $result['url_key'];

            if ($storeId == 0) {
                // insert url key to all store views
                foreach ($allStoreIds as $aStoreId) {
                    // but do not overwrite explicit assignments
                    if (!array_key_exists($productId, $urlKeys) || !array_key_exists($aStoreId, $urlKeys[$productId])) {
                        $urlKeys[$productId][$aStoreId] = $urlKey;
                    }
                }
            } else {
                $urlKeys[$productId][$storeId] = $urlKey;
            }
        }

        // category ids per product
        $categoryIds = [];
        $results = $this->db->fetchAllAssoc("
            SELECT `product_id`, `category_id`
            FROM `{$this->metaData->categoryProductTable}`
            WHERE
                `product_id` IN (" . implode(',', $productIds) .")
        ");

        $rewriteValues = [];
        foreach ($results as $result) {
            $categoryIds[$result['product_id']][$result['category_id']] = $result['category_id'];
        }

        foreach ($urlKeys as $productId => $urlKeyData) {
            foreach ($urlKeyData as $storeId => $urlKey) {

                $shortUrl = $urlKey . $this->metaData->productUrlSuffix;

                // url keys without categories
                $requestPath = $shortUrl;
                $targetPath = 'catalog/product/view/id/' . $productId;
                $rewriteValues[] = new UrlRewriteInfo($productId, $requestPath, $targetPath, 0, $storeId, null, 1);

                if (!array_key_exists($productId, $categoryIds)) {
                    continue;
                }

                // url keys with categories
                foreach ($categoryIds[$productId] as $directCategoryId) {

                    // here we check if the category id supplied actually exists
                    if (!array_key_exists($directCategoryId, $this->metaData->allCategoryInfo)) {
                        continue;
                    }

                    $path = "";
                    foreach ($this->metaData->allCategoryInfo[$directCategoryId]->path as $i => $parentCategoryId) {

                        // the root category is not used for the url path
                        if ($i === 0) {
                            continue;
                        }

                        $categoryInfo = $this->metaData->allCategoryInfo[$parentCategoryId];

                        // take the url_key from the store view, or default to the global url_key
                        $urlKey = array_key_exists($storeId, $categoryInfo->urlKeys) ? $categoryInfo->urlKeys[$storeId] : $categoryInfo->urlKeys[0];

                        $path .= $urlKey . "/";

                        $requestPath = $path . $shortUrl;
                        $targetPath = 'catalog/product/view/id/' . $productId . '/category/' . $parentCategoryId;
                        $metadata = ['category_id' => (string)$parentCategoryId];

                        $rewriteValues[] = new UrlRewriteInfo($productId, $requestPath, $targetPath, 0, $storeId, $metadata, 1);
                    }
                }
            }
        }

        return $rewriteValues;
    }

    /**
     * @param UrlRewriteInfo[] $urlRewrites
     * @param ValueSerializer $valueSerializer
     */
    protected function rewriteExistingRewrites(array $urlRewrites, ValueSerializer $valueSerializer)
    {
        if (empty($urlRewrites)) {
            return;
        }

        $existingUrlRewriteData = $this->getExistingUrlRewriteData($urlRewrites, $valueSerializer);

        $updatedRewrites = [];
        $oldRewriteIds = [];
        foreach ($urlRewrites as $urlRewriteInfo) {

            // distinct store_id, product_id, metadata

            $categoryId = empty($urlRewriteInfo->metadata) ? null : $urlRewriteInfo->metadata['category_id'];
            $key = $urlRewriteInfo->productId . '/' . $categoryId;

            if (!array_key_exists($urlRewriteInfo->storeId, $existingUrlRewriteData) || ! array_key_exists($key, $existingUrlRewriteData[$urlRewriteInfo->storeId])) {
                continue;
            }

            $oldUrlRewrites = $existingUrlRewriteData[$urlRewriteInfo->storeId][$key];

            // multiple old rewrites with matching store_id, product_id, metadata

            foreach ($oldUrlRewrites as $oldRewrite) {

                $oldRewriteId = $oldRewrite['url_rewrite_id'];
                $oldRequestPath = $oldRewrite['request_path'];
                $oldRedirectType = $oldRewrite['redirect_type'];

                $oldRewriteIds[] = $oldRewriteId;

                $updatedRedirectType = '301';

                if ($oldRedirectType == '0') {

                    if (!$this->metaData->saveRewritesHistory) {
                        // no history: ignore the existing entry
                        continue;
                    }
                }

                $updatedTargetPath = $urlRewriteInfo->requestPath;

                // when a url rewrite changes to a redirect, its metadata always is a structure
                $metadata = $urlRewriteInfo->metadata;
                if ($metadata === null) {
                    $metadata = [];
                }

                // autogenerated changes to 0 for redirects
                $autogenerated = 0;

                $updatedRewrites[] =
                    new UrlRewriteInfo($urlRewriteInfo->productId, $oldRequestPath, $updatedTargetPath, $updatedRedirectType, $urlRewriteInfo->storeId, $metadata, $autogenerated);
            }
        }

        if (!empty($oldRewriteIds)) {
            // delete old rewrites
            $sql = "
                DELETE FROM `{$this->metaData->urlRewriteTable}`
                WHERE `url_rewrite_id` IN (" . implode(',', $oldRewriteIds) . ")
            ";

            $this->db->execute($sql);
        }

        $this->writeUrlRewrites($urlRewrites, $valueSerializer, true);

        $this->writeUrlRewrites($updatedRewrites, $valueSerializer, false);
    }

    /**
     * @param UrlRewriteInfo[] $urlRewrites
     * @param ValueSerializer $valueSerializer
     * @param $buildIndex
     */
    protected function writeUrlRewrites(array $urlRewrites, ValueSerializer $valueSerializer, $buildIndex)
    {
        if (empty($urlRewrites)) {
            return;
        }

        $newRewriteValues = [];
        foreach ($urlRewrites as $urlRewrite) {
            $metadata = $urlRewrite->metadata === null ? "null" : $this->db->quote($valueSerializer->serialize($urlRewrite->metadata));
            $requestPath = $this->db->quote($urlRewrite->requestPath);
            $targetPath = $this->db->quote($urlRewrite->targetPath);
            $newRewriteValues[] = "('product', {$urlRewrite->productId}, {$requestPath}, {$targetPath}, {$urlRewrite->redirectType}, {$urlRewrite->storeId}, {$urlRewrite->autogenerated}, {$metadata})";
        }

        // add new values
        // IGNORE works on the key request_path, store_id
        // when this combination already exists, it is ignored
        // this may happen if a main product is followed by one of its store views
        $sql = "
            INSERT IGNORE INTO `{$this->metaData->urlRewriteTable}`
            (`entity_type`, `entity_id`, `request_path`, `target_path`, `redirect_type`, `store_id`, `is_autogenerated`, `metadata`)
            VALUES " . implode(', ', $newRewriteValues) . "
        ";

        $this->db->execute($sql);

        if ($buildIndex) {

            // the last insert id is guaranteed to be the first id generated
            $insertId = $this->db->getLastInsertId();

            if ($insertId != 0) {

                // the SUBSTRING_INDEX extracts the category id from the target_path
                $sql = "
                    INSERT INTO `{$this->metaData->urlRewriteProductCategoryTable}` (`url_rewrite_id`, `category_id`, `product_id`)
                    SELECT `url_rewrite_id`, SUBSTRING_INDEX(`target_path`, '/', -1), `entity_id`
                    FROM `{$this->metaData->urlRewriteTable}`
                    WHERE 
                        `url_rewrite_id` >= {$insertId} AND
                        `target_path` LIKE '%/category/%' 
                ";

                $this->db->execute($sql);
            }
        }
    }
}

