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
        if (empty($products)) {
            return;
        }

        $productIds = array_column($products, 'id');

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
        if (empty($products)) {
            return;
        }

        foreach ($products as $product) {
            foreach ($product->getDownloadLinks() as $i => $downloadLink) {

                $order = $i + 1;
                $isShareable = (int)$downloadLink->isShareable();
                $numberOfDownloads = $downloadLink->getNumberOfDownloads();

                list($linkUrl, $linkFile, $linkType) = $this->interpretFileOrUrl($downloadLink->getFileOrUrl());
                list($sampleUrl, $sampleFile, $sampleType) = $this->interpretFileOrUrl($downloadLink->getSampleFileOrUrl());

                $this->db->execute("
                    INSERT INTO `" . $this->metaData->downloadableLinkTable . "`
                    SET 
                        `product_id` = {$product->id}, 
                        `sort_order` = {$order}, 
                        `number_of_downloads` = {$numberOfDownloads}, 
                        `is_shareable` = {$isShareable}, 
                        `link_url` = {$linkUrl}, 
                        `link_file` = {$linkFile},
                        `link_type` = {$linkType}, 
                        `sample_url` = {$sampleUrl}, 
                        `sample_file` = {$sampleFile},
                        `sample_type` = {$sampleType}
                ");

                $downloadLink->setId($this->db->getLastInsertId());
            }

            foreach ($product->getDownloadSamples() as $i => $downloadSample) {

                $order = $i + 1;

                list($sampleUrl, $sampleFile, $sampleType) = $this->interpretFileOrUrl($downloadSample->getFileOrUrl());

                $this->db->execute("
                    INSERT INTO `" . $this->metaData->downloadableSampleTable . "`
                    SET 
                        `product_id` = {$product->id}, 
                        `sort_order` = {$order}, 
                        `link_url` = {$linkUrl}, 
                        `link_type` = {$linkType}, 
                        `sample_url` = {$sampleUrl}, 
                        `sample_file` = {$sampleFile},
                        `sample_type` = {$sampleType}
                ");

                $downloadSample->setId($this->db->getLastInsertId());
            }
        }

        foreach ($products as $product) {
            foreach ($product->getStoreViews() as $storeView) {
                foreach ($storeView->getDownloadLinkInformations() as $downloadLinkInformation) {

                    $downloadLinkId = $downloadLinkInformation->getDownloadLink()->getId();
                    if ($downloadLinkId !== null) {
                        $title = $this->db->quote($downloadLinkInformation->getTitle());
                        $price = $this->db->quote($downloadLinkInformation->getPrice());

                        $this->db->execute("
                            INSERT INTO `" . $this->metaData->downloadableLinkTitleTable . "`
                            SET 
                                `link_id` = {$downloadLinkId}, 
                                `store_id` = {$storeView->getStoreViewId()}, 
                                `title` = {$title}
                        ");

                        $this->db->execute("
                            INSERT INTO `" . $this->metaData->downloadableLinkPriceTable . "`
                            SET 
                                `link_id` = {$downloadLinkId}, 
                                `store_id` = {$storeView->getStoreViewId()}, 
                                `title` = {$price}
                        ");
                    }
                }

                foreach ($storeView->getDownloadSampleInformations() as $downloadSampleInformation) {

                    $downloadSampleId = $downloadSampleInformation->getDownloadSample()->getId();
                    if ($downloadSampleId !== null) {

                        $title = $this->db->quote($downloadSampleInformation->getTitle());

                        $this->db->execute("
                            INSERT INTO `" . $this->metaData->downloadableSampleTitleTable . "`
                            SET 
                                `link_id` = {$downloadLinkId}, 
                                `store_id` = {$storeView->getStoreViewId()}, 
                                `title` = {$title}
                        ");
                    }
                }
            }
        }
    }

    protected function interpretFileOrUrl(string $fileOrUrl)
    {
        if ($fileOrUrl === '') {
            $type = 'null';
            $file = 'null';
            $url = 'null';
        } elseif (preg_match('#(http://|https://|://)#i', $fileOrUrl)) {
            $type = "'url'";
            $file = 'null';
            $url = $this->db->quote($fileOrUrl);
        } else {
            $type = "'file'";
            $file = $this->db->quote($fileOrUrl);
            $url = 'null';
        }

        return [$url, $file, $type];
    }
}