<?php

namespace BigBridge\ProductImport\Model\Resource;

use BigBridge\ProductImport\Api\Data\DownloadableProduct;
use BigBridge\ProductImport\Model\Data\Image;
use BigBridge\ProductImport\Model\Db\Magento2DbConnection;
use BigBridge\ProductImport\Model\Resource\Resolver\ReferenceResolver;
use BigBridge\ProductImport\Model\Resource\Resolver\UrlKeyGenerator;
use BigBridge\ProductImport\Model\Resource\Storage\CustomOptionStorage;
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
        StockItemStorage $stockItemStorage,
        CustomOptionStorage $customOptionStorage)
    {
        parent::__construct($db, $metaData, $validator, $referenceResolver, $urlKeyGenerator, $urlRewriteStorage, $productEntityStorage, $imageStorage, $linkedProductStorage, $tierPriceStorage, $stockItemStorage, $customOptionStorage);
        $this->imageStorage = $imageStorage;
    }

    /**
     * @param DownloadableProduct[] $insertProducts
     * @param DownloadableProduct[] $updateProducts
     */
    public function performTypeSpecificStorage(array $insertProducts, array $updateProducts)
    {
        // new products: insert
        $this->insertLinksAndSamples($insertProducts);

        // updated products: remove and reinsert links and samples
        $this->removeLinksAndSamples($updateProducts);
        $this->insertLinksAndSamples($updateProducts);
    }

    /**
     * @param DownloadableProduct[] $products
     */
    protected function removeLinksAndSamples(array $products)
    {
        $linkProductIds = [];
        $sampleProductIds = [];
        foreach ($products as $product) {
            if ($product->getDownloadLinks() !== null) {
                $linkProductIds[] = $product->id;
            }
            if ($product->getDownloadSamples() !== null) {
                $sampleProductIds[] = $product->id;
            }
        }

        $this->db->deleteMultiple($this->metaData->downloadableLinkTable, 'product_id', $linkProductIds);
        $this->db->deleteMultiple($this->metaData->downloadableSampleTable, 'product_id', $sampleProductIds);
    }

    /**
     * @param DownloadableProduct[] $products
     */
    protected function insertLinksAndSamples(array $products)
    {
        if (empty($products)) {
            return;
        }

        // insert links and samples
        foreach ($products as $product) {

            if (($links = $product->getDownloadLinks()) !== null) {

                foreach ($links as $i => $downloadLink) {

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
                            `product_id` = ?, 
                            `sort_order` = ?, 
                            `number_of_downloads` = ?, 
                            `is_shareable` = ?, 
                            `link_url` = ?, 
                            `link_file` = ?,
                            `link_type` = ?, 
                            `sample_url` = ?, 
                            `sample_file` = ?,
                            `sample_type` = ?
                    ", [
                        $product->id,
                        $order,
                        $numberOfDownloads,
                        $isShareable,
                        $linkUrl,
                        $linkFile,
                        $linkType,
                        $sampleUrl,
                        $sampleFile,
                        $sampleType
                    ]);

                    $downloadLink->setId($this->db->getLastInsertId());
                }
            }

            if (($samples = $product->getDownloadSamples()) !== null) {

                foreach ($samples as $i => $downloadSample) {

                    list($sampleUrl, $sampleFile, $sampleType) = $this->interpretFileOrUrl(
                        $downloadSample->getFileOrUrl(), $downloadSample->getTemporaryStoragePathSample(), self::SAMPLES_PATH);

                    $this->db->execute("
                        INSERT INTO `" . $this->metaData->downloadableSampleTable . "`
                        SET 
                            `product_id` = ?, 
                            `sort_order` = ?, 
                            `sample_url` = ?, 
                            `sample_file` = ?,
                            `sample_type` = ?
                    ", [
                        $product->id,
                        $i + 1,
                        $sampleUrl,
                        $sampleFile,
                        $sampleType
                    ]);

                    $downloadSample->setId($this->db->getLastInsertId());
                }
            }
        }

        // insert titles and prices, per store view
        foreach ($products as $product) {
            foreach ($product->getStoreViews() as $storeView) {

                foreach ($storeView->getDownloadLinkInformations() as $downloadLinkInformation) {

                    $downloadLinkId = $downloadLinkInformation->getDownloadLink()->getId();
                    if ($downloadLinkId !== null) {

                        $this->db->execute("
                            INSERT INTO `" . $this->metaData->downloadableLinkTitleTable . "`
                            SET 
                                `link_id` = ?, 
                                `store_id` = ?, 
                                `title` = ?
                        ", [
                            $downloadLinkId,
                            $storeView->getStoreViewId(),
                            $downloadLinkInformation->getTitle()
                        ]);

                        // find the website that belongs to the store view (the website of store view "default" is "admin")
                        $websiteId = $this->metaData->storeViewWebsiteMap[$storeView->getStoreViewId()];

                        $this->db->execute("
                            INSERT INTO `" . $this->metaData->downloadableLinkPriceTable . "`
                            SET 
                                `link_id` = ?, 
                                `website_id` = ?, 
                                `price` = ?
                        ", [
                            $downloadLinkId,
                            $websiteId,
                            $downloadLinkInformation->getPrice()
                        ]);
                    }
                }

                foreach ($storeView->getDownloadSampleInformations() as $downloadSampleInformation) {

                    $downloadSampleId = $downloadSampleInformation->getDownloadSample()->getId();
                    if ($downloadSampleId !== null) {

                        $this->db->execute("
                            INSERT INTO `" . $this->metaData->downloadableSampleTitleTable . "`
                            SET 
                                `sample_id` = ?, 
                                `store_id` = ?, 
                                `title` = ?
                        ", [
                            $downloadSampleId,
                            $storeView->getStoreViewId(),
                            $downloadSampleInformation->getTitle()
                        ]);
                    }
                }
            }
        }
    }

    protected function interpretFileOrUrl(string $fileOrUrl, $temporaryStoragePath, $targetBaseDir)
    {
        if ($fileOrUrl === '') {
            $type = null;
            $file = null;
            $url = null;
        } elseif (preg_match('#^(http://|https://|://)#i', $fileOrUrl)) {
            $type = "url";
            $file = null;
            $url = $fileOrUrl;
        } else {
            $type = "file";
            $file = $this->getActualStoragePath($fileOrUrl, $temporaryStoragePath, $targetBaseDir);
            $url = null;
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