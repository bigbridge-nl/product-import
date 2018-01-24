<?php

namespace BigBridge\ProductImport\Model\Resource;

use BigBridge\ProductImport\Api\DownloadableProduct;
use BigBridge\ProductImport\Model\Data\Image;
use BigBridge\ProductImport\Model\Db\Magento2DbConnection;
use BigBridge\ProductImport\Model\Resource\Resolver\ReferenceResolver;
use BigBridge\ProductImport\Model\Resource\Resolver\UrlKeyGenerator;
use BigBridge\ProductImport\Model\Resource\Storage\ImageStorage;
use BigBridge\ProductImport\Model\Resource\Storage\LinkedProductStorage;
use BigBridge\ProductImport\Model\Resource\Storage\ProductEntityStorage;
use BigBridge\ProductImport\Model\Resource\Storage\StockItemStorage;
use BigBridge\ProductImport\Model\Resource\Storage\TierPriceStorage;
use BigBridge\ProductImport\Model\Resource\Storage\UrlRewriteStorage;
use BigBridge\ProductImport\Model\Resource\Validation\DownloadableValidator;

/**
 * @author Patrick van Bergen
 */
class DownloadableStorage extends ProductStorage
{
    const LINKS_PATH = BP . "/pub/media/downloadable/files/links";
    const LINK_SAMPLES_PATH = BP . "/pub/media/downloadable/files/link_samples";
    const SAMPLES_PATH = BP . "/pub/media/downloadable/files/samples";

    public function __construct(
        Magento2DbConnection $db,
        MetaData $metaData,
        DownloadableValidator $validator,
        ReferenceResolver $referenceResolver,
        UrlKeyGenerator $urlKeyGenerator,
        UrlRewriteStorage $urlRewriteStorage,
        ProductEntityStorage $productEntityStorage,
        ImageStorage $imageStorage,
        LinkedProductStorage $linkedProductStorage,
        TierPriceStorage $tierPriceStorage,
        StockItemStorage $stockItemStorage)
    {
        parent::__construct($db, $metaData, $validator, $referenceResolver, $urlKeyGenerator, $urlRewriteStorage, $productEntityStorage, $imageStorage, $linkedProductStorage, $tierPriceStorage, $stockItemStorage);
        $this->imageStorage = $imageStorage;
    }

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
            WHERE `product_id`  IN (" . implode(', ', $productIds) . ")
        ");

        $this->db->execute("
            DELETE FROM `" . $this->metaData->downloadableSampleTable . "`
            WHERE `product_id`  IN (" . implode(', ', $productIds) . ")
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

                list($linkUrl, $linkFile, $linkType) = $this->interpretFileOrUrl(
                    $downloadLink->getFileOrUrl(), $downloadLink->getTemporaryStoragePathLink(), self::LINKS_PATH);
                list($sampleUrl, $sampleFile, $sampleType) = $this->interpretFileOrUrl(
                    $downloadLink->getSampleFileOrUrl(), $downloadLink->getTemporaryStoragePathSample(), self::LINK_SAMPLES_PATH);

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

                list($sampleUrl, $sampleFile, $sampleType) = $this->interpretFileOrUrl(
                    $downloadSample->getFileOrUrl(), $downloadSample->getTemporaryStoragePathSample(), self::SAMPLES_PATH);

                $this->db->execute("
                    INSERT INTO `" . $this->metaData->downloadableSampleTable . "`
                    SET 
                        `product_id` = {$product->id}, 
                        `sort_order` = {$order}, 
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

                        // find the website that belongs to the store view (the website of store view "default" is "admin")
                        $websiteId = $this->metaData->storeViewWebsiteMap[$storeView->getStoreViewId()];

                        $this->db->execute("
                            INSERT INTO `" . $this->metaData->downloadableLinkPriceTable . "`
                            SET 
                                `link_id` = {$downloadLinkId}, 
                                `website_id` = {$websiteId}, 
                                `price` = {$price}
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
                                `sample_id` = {$downloadSampleId}, 
                                `store_id` = {$storeView->getStoreViewId()}, 
                                `title` = {$title}
                        ");
                    }
                }
            }
        }
    }

    protected function interpretFileOrUrl(string $fileOrUrl, $temporaryStoragePath, $targetBaseDir)
    {
        if ($fileOrUrl === '') {
            $type = 'null';
            $file = 'null';
            $url = 'null';
        } elseif (preg_match('#^(http://|https://|://)#i', $fileOrUrl)) {
            $type = "'url'";
            $file = 'null';
            $url = $this->db->quote($fileOrUrl);
        } else {
            $type = "'file'";
            $file = "'" . $this->getActualStoragePath($fileOrUrl, $temporaryStoragePath, $targetBaseDir) . "'";
            $url = 'null';
        }

        return [$url, $file, $type];
    }

    protected function getActualStoragePath($filePath, $temporaryStoragePath, $targetBaseDir)
    {
        $image = new Image($filePath);
        $defaultStoragePath = $image->getDefaultStoragePath();

        $actualStoragePath = $this->move($temporaryStoragePath, $defaultStoragePath, $targetBaseDir);
        return $actualStoragePath;
    }

    public function move(string $temporaryStoragePath, $defaultStoragePath, $targetBaseDir)
    {
        $actualStoragePath = $defaultStoragePath;

        if (file_exists($targetBaseDir . $actualStoragePath)) {
            if (!$this->imageStorage->filesAreEqual($temporaryStoragePath, $targetBaseDir . $actualStoragePath)) {

                preg_match('/^(.*)\.([^.]+)$/', $actualStoragePath, $matches);
                $rest = $matches[1];
                $ext = $matches[2];

                $i = 0;
                do {
                    $i++;
                    $actualStoragePath = "{$rest}_{$i}.{$ext}";

                    if (!file_exists($targetBaseDir . $actualStoragePath)) {
                        break;
                    }
                    if ($this->imageStorage->filesAreEqual($temporaryStoragePath, $targetBaseDir . $actualStoragePath)) {
                        break;
                    }

                } while (true);
            }
        }

        $targetDir = dirname($targetBaseDir . $actualStoragePath);
        if (!file_exists($targetDir)) {
            mkdir($targetDir, 0777, true);
        }

        // move image from its temporary position to its final position
        rename($temporaryStoragePath, $targetBaseDir . $actualStoragePath);

        return $actualStoragePath;
    }

}