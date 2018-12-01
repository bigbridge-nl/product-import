<?php

namespace BigBridge\ProductImport\Model\Resource\Storage;

use BigBridge\ProductImport\Api\Data\DownloadableProduct;
use BigBridge\ProductImport\Model\Data\Image;
use BigBridge\ProductImport\Model\Persistence\Magento2DbConnection;
use BigBridge\ProductImport\Model\Resource\MetaData;

/**
 * @author Patrick van Bergen
 */
class DownloadableStorage
{
    const LINKS_PATH = BP . "/pub/media/downloadable/files/links";
    const LINK_SAMPLES_PATH = BP . "/pub/media/downloadable/files/link_samples";
    const SAMPLES_PATH = BP . "/pub/media/downloadable/files/samples";

    /** @var  Magento2DbConnection */
    protected $db;

    /** @var  MetaData */
    protected $metaData;

    /** @var ImageStorage */
    protected $imageStorage;

    public function __construct(
        Magento2DbConnection $db,
        MetaData $metaData,
        ImageStorage $imageStorage)
    {
        $this->db = $db;
        $this->metaData = $metaData;
        $this->imageStorage = $imageStorage;
    }

    /**
     * @param DownloadableProduct[] $products
     */
    public function performTypeSpecificStorage(array $products)
    {
        $affectedLinkProducts = [];
        $affectedSampleProducts = [];

        foreach ($products as $product) {
            if ($product->getDownloadLinks() !== null) {
                $affectedLinkProducts[] = $product;
            }
            if ($product->getDownloadSamples() !== null) {
                $affectedSampleProducts[] = $product;
            }
        }

        // updated products: remove and reinsert links and samples
        $this->removeLinks($affectedLinkProducts);
        $this->insertLinks($affectedLinkProducts);
        $this->removeSamples($affectedSampleProducts);
        $this->insertSamples($affectedSampleProducts);
    }

    /**
     * @param DownloadableProduct[] $products
     */
    protected function removeLinks(array $products)
    {
        $productIds = array_column($products, 'id');

        $this->db->deleteMultiple($this->metaData->downloadableLinkTable, 'product_id', $productIds);
    }

    /**
     * @param DownloadableProduct[] $products
     */
    protected function removeSamples(array $products)
    {
        $productIds = array_column($products, 'id');

        $this->db->deleteMultiple($this->metaData->downloadableSampleTable, 'product_id', $productIds);
    }

    /**
     * @param DownloadableProduct[] $products
     */
    protected function insertLinks(array $products)
    {
        // insert links
        foreach ($products as $product) {

            foreach ($links = $product->getDownloadLinks() as $i => $downloadLink) {

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
            }
        }
    }


    /**
     * @param DownloadableProduct[] $products
     */
    protected function insertSamples(array $products)
    {
        // insert samples
        foreach ($products as $product) {

            foreach ($samples = $product->getDownloadSamples() as $i => $downloadSample) {

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

        // insert titles and prices, per store view
        foreach ($products as $product) {
            foreach ($product->getStoreViews() as $storeView) {

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
        } elseif (preg_match(ImageStorage::URL_PATTERN, $fileOrUrl)) {
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
            } else {
                // remove temporary file
                unlink($temporaryStoragePath);
                return $actualStoragePath;
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

    public function removeLinksAndSamples(array $products)
    {
        $this->removeLinks($products);
        $this->removeSamples($products);
    }
}