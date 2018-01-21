<?php

namespace BigBridge\ProductImport\Model\Resource;

use BigBridge\ProductImport\Api\DownloadableProduct;

/**
 * @author Patrick van Bergen
 */
class DownloadableStorage extends ProductStorage
{

    /**
     * @param DownloadableProduct[] $insertProducts
     * @param DownloadableProduct[] $updateProducts
     */
    public function performTypeSpecificStorage(array $insertProducts, array $updateProducts)
    {
        $this->insertLinksAndSamples($insertProducts);

        $this->removeLinksAndSamples($updateProducts);
        $this->insertLinksAndSamples($updateProducts);
    }

    protected function removeLinksAndSamples(array $products)
    {
        $productIds = array_column($products, 'id');

        if (empty($productIds)) {
            return;
        }

        $this->db->execute("
            DELETE FROM `" . $this->metaData->downloadableLinkTable . "`
            WHERE `product_id  IN (" . implode(', ', $productIds) . ")`
        ");

        $this->db->execute("
            DELETE FROM `" . $this->metaData->downloadableSampleTable . "`
            WHERE `product_id  IN (" . implode(', ', $productIds) . ")`
        ");
    }

    /**
     * @param DownloadableProduct[] $products
     */
    protected function insertLinksAndSamples(array $products)
    {

    }
}