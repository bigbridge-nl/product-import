<?php

namespace BigBridge\ProductImport\Model\Storage;

use BigBridge\ProductImport\Model\Data\SimpleProduct;


// https://dev.mysql.com/doc/refman/5.6/en/optimizing-innodb-bulk-data-loading.html


/**
 * @author Patrick van Bergen
 */
class Simples
{
    const ATT_SKU = 'sku';

    public function prepare()
    {

    }

    /**
     * @param SimpleProduct[] $simpleProducts
     */
    public function storeSimpleProducts(array $simpleProducts)
    {
        if (empty($simpleProducts)) {
            return;
        }

        // collect skus
        $skus = array_column($simpleProducts, self::ATT_SKU);

        // collect inserts and updates
        $updateSkus = $this->getExistingSkus($skus);
        $insertSkus = array_diff($skus, $updateSkus);

        // main table attributes

        // for each attribute

            // store all values in one insert

            // store all values in one update

        // update flat table
    }

    private function getExistingSkus(array $skus)
    {
        // SELECT `entity_id` FROM catalog_product_entity WHERE `sku` in ()
        return [];
    }
}