<?php

namespace BigBridge\ProductImport\Model\Resource\Storage;

use BigBridge\ProductImport\Api\Data\DownloadableProduct;
use BigBridge\ProductImport\Api\Data\Product;
use BigBridge\ProductImport\Api\ImportConfig;
use BigBridge\ProductImport\Model\Data\Image;
use BigBridge\ProductImport\Model\Data\ImageGalleryInformation;
use BigBridge\ProductImport\Model\Persistence\HttpCache;
use BigBridge\ProductImport\Model\Persistence\Magento2DbConnection;
use BigBridge\ProductImport\Model\Resource\MetaData;

/**
 * @author Patrick van Bergen
 */
class ImageStorage
{
    const PRODUCT_IMAGE_PATH = BP . "/pub/media/catalog/product";
    const PRODUCT_CACHE_PATH = BP . "/pub/media/catalog/product/cache";

    const URL_PATTERN = '#^(http://|https://|//)#i';

    /** @var  Magento2DbConnection */
    protected $db;

    /** @var MetaData */
    protected $metaData;

    /** @var HttpCache */
    protected $httpCache;

    public function __construct(
        Magento2DbConnection $db,
        MetaData $metaData,
        HttpCache $httpCache)
    {
        $this->db = $db;
        $this->metaData = $metaData;
        $this->httpCache = $httpCache;
    }

    /**
     * @param Product[] $products
     * @param ImportConfig $config
     */
    public function moveImagesToTemporaryLocation(array $products, ImportConfig $config)
    {
        if (!file_exists($config->imageCacheDir)) {
            mkdir($config->imageCacheDir, 0777, true);
        }

        foreach ($products as $product) {
            foreach ($product->getImages() as $image) {

                $imagePath = $image->getImagePath();

                $temporaryStoragePath = $this->getTemporaryStoragePath($product, $imagePath, $config);

                if ($temporaryStoragePath !== null) {
                    $image->setTemporaryStoragePath($temporaryStoragePath);
                }
            }

            if ($product instanceof DownloadableProduct) {
                $this->validateLinkImages($product, $config);
            }
        }
    }

    protected function validateLinkImages(DownloadableProduct $product, ImportConfig $config)
    {
        if (($links = $product->getDownloadLinks()) !== null) {
            foreach ($links as $downloadLink) {
                $fileOrUrl = $downloadLink->getFileOrUrl();
                $downloadLink->setTemporaryStoragePathLink($this->getDownloadableTemporaryStoragePath($product, $fileOrUrl, $config));

                $sampleFileOrUrl = $downloadLink->getSampleFileOrUrl();
                $downloadLink->setTemporaryStoragePathSample($this->getDownloadableTemporaryStoragePath($product, $sampleFileOrUrl, $config));
            }
        }

        if (($samples = $product->getDownloadSamples()) !== null) {
            foreach ($samples as $downloadSample) {
                $sampleFileOrUrl = $downloadSample->getFileOrUrl();
                $downloadSample->setTemporaryStoragePathSample($this->getDownloadableTemporaryStoragePath($product, $sampleFileOrUrl, $config));
            }
        }
    }

    protected function getTemporaryStoragePath(Product $product, string $imagePath, ImportConfig $config)
    {
        if ($imagePath && !preg_match(self::URL_PATTERN, $imagePath) && $imagePath[0] !== DIRECTORY_SEPARATOR) {
            $imagePath = $config->imageSourceDir . DIRECTORY_SEPARATOR . $imagePath;
        }

        if (!preg_match('/\.(png|jpg|jpeg|gif)$/i', $imagePath)) {
            $product->addError("Filetype not allowed (use .jpg, .png or .gif): " . $imagePath);
        }

        $temporaryStoragePath = $config->imageCacheDir . '/' . md5($imagePath) . '-' . basename($imagePath);

        // keep temporary file?
        if (file_exists($temporaryStoragePath)) {
            if ($config->existingImageStrategy === ImportConfig::EXISTING_IMAGE_STRATEGY_CHECK_IMPORT_DIR) {
                // yes: use it!
                return $temporaryStoragePath;
            } elseif ($config->existingImageStrategy === ImportConfig::EXISTING_IMAGE_STRATEGY_HTTP_CACHING) {
                // do nothing; it serves as http cache
            } else {
                // no cache: remove it
                unlink($temporaryStoragePath);
            }
        }

        if (preg_match(self::URL_PATTERN, $imagePath)) {

            $error = $this->httpCache->fetchFromUrl($imagePath, $temporaryStoragePath, $config);
            if ($error !== '') {
                $product->addError($error);
                return null;
            }
        } elseif (!is_file($imagePath)) {
            $product->addError("File not found: " . $imagePath);
            return null;
        } elseif (stat($imagePath)['dev'] !== stat(__FILE__)['dev']) {
            // file is on different device
            copy($imagePath, $temporaryStoragePath);
        } elseif (!file_exists($temporaryStoragePath)) {
            // file is on same device
            link($imagePath, $temporaryStoragePath);
        }

        if (!file_exists($temporaryStoragePath)) {
            $product->addError("File was not copied to temporary storage: " . $imagePath);
            return null;
        }

        if (filesize($temporaryStoragePath) === 0) {
            $product->addError("File is empty: " . $imagePath);
            unlink($temporaryStoragePath);
            return null;
        }

        return $temporaryStoragePath;
    }

    protected function getDownloadableTemporaryStoragePath(DownloadableProduct $product, $fileOrUrl, ImportConfig $config)
    {
        if ($fileOrUrl === '') {
            return null;
        } elseif (preg_match(ImageStorage::URL_PATTERN, $fileOrUrl)) {
            return null;
        } else {

            $temporaryStoragePath = $config->imageCacheDir . '/' . uniqid() . basename($fileOrUrl);

            if ($fileOrUrl && $fileOrUrl[0] !== DIRECTORY_SEPARATOR) {
                $fileOrUrl = $config->imageSourceDir . DIRECTORY_SEPARATOR . $fileOrUrl;
            }

            if (!is_file($fileOrUrl)) {
                $product->addError("File not found: " . $fileOrUrl);
                return null;
            } elseif (stat($fileOrUrl)['dev'] !== stat(__FILE__)['dev']) {
                // file is on different device
                copy($fileOrUrl, $temporaryStoragePath);
            } else {
                // file is on same device
                link($fileOrUrl, $temporaryStoragePath);
            }

            if (!file_exists($temporaryStoragePath)) {
                $product->addError("File was not copied to temporary storage: " . $fileOrUrl);
                return null;
            } else if (filesize($temporaryStoragePath) === 0) {
                $product->addError("File is empty: " . $fileOrUrl);
                unlink($temporaryStoragePath);
                return null;
            }

            return $temporaryStoragePath;
        }
    }

    /**
     * @param Product[] $products
     * @param bool $removeObsoleteImages
     * @param bool $removeTemporaryImages
     */
    public function storeProductImages(array $products, bool $removeObsoleteImages, bool $removeTemporaryImages)
    {
        foreach ($products as $product) {
            $this->storeImages($product, $removeObsoleteImages, $removeTemporaryImages);
        }

        $this->insertImageRoles($products);
    }

    protected function storeImages(Product $product, bool $removeObsoleteImages, bool $removeTemporaryImages)
    {
        // important! if no images are specified, do not remove all images
        if (empty($product->getImages())) {
            return;
        }

        $imageData = $this->loadExistingImageData($product);

        // separates new from existing images
        // add valueId and actualStoragePath to existing images
        list($existingImages, $newImages) = $this->splitNewAndExistingImages($product, $imageData);

        // if specified in the config, remove obsolete images
        if ($removeObsoleteImages) {
            $this->removeObsoleteImages($product, $existingImages, $imageData);
        }

        // stores images and metadata
        // add valueId and actualStoragePath to new images
        foreach ($newImages as $image) {
            $this->insertImage($product, $image);
        }

        // updates image metadata
        foreach ($existingImages as $image) {
            $this->updateImage($image);
        }

        foreach ($product->getStoreViews() as $storeView) {
            foreach ($storeView->getImageGalleryInformation() as $imageGalleryInformation) {
                $this->upsertImageGalleryInformation($product->id, $storeView->getStoreViewId(), $imageGalleryInformation);
            }
        }

        // remove temporary images
        if ($removeTemporaryImages) {
            $this->removeTemporaryImages($product);
        }
    }

    protected function removeTemporaryImages(Product $product)
    {
        foreach ($product->getImages() as $image) {
            @unlink($image->getTemporaryStoragePath());
        }
    }

    protected function emptyCache(string $storagePath)
    {
        foreach (glob(self::PRODUCT_CACHE_PATH . "/*/" . $storagePath) as $filePath) {
            @unlink($filePath);
        }
    }

    /**
     * Removes all images in $imageData (raw database information) that are not found in $existingImages (new import values)
     * from gallery tables, product attributes, and file system.
     *
     * @param Product $product
     * @param array $existingImages
     * @param array $imageData
     */
    protected function removeObsoleteImages(Product $product, array $existingImages, array $imageData)
    {
        $obsoleteValueIds = [];

        // walk through existing raw database information
        foreach ($imageData as $imageDatum) {

            $storagePath = $imageDatum['value'];

            // check if available in current import (new or update)
            $found = false;
            foreach ($existingImages as $image) {
                if ($image->getActualStoragePath() === $storagePath) {
                    $found = true;
                }
            }

            if (!$found) {

                // entry from gallery tables
                $obsoleteValueIds[] = $imageDatum['value_id'];

                // remove from all image role attributes
                $this->db->execute("
                    DELETE FROM `{$this->metaData->productEntityTable}_varchar`
                    WHERE 
                        entity_id = ? AND
                        attribute_id IN (" . $this->db->getMarks($this->metaData->imageAttributeIds) . ") AND
                        value = ?
                ", array_merge(
                    [$product->id],
                    $this->metaData->imageAttributeIds,
                    [$storagePath]
                ));

                // check if the image is used by other products
                // (this cannot be the case in standard Magento)
                $usageCount = $this->db->fetchSingleCell("
                    SELECT COUNT(*)
                    FROM `{$this->metaData->productEntityTable}_varchar`
                    WHERE 
                        attribute_id IN (" . $this->db->getMarks($this->metaData->imageAttributeIds) . ") AND
                        value = ?
                ", array_merge(
                    $this->metaData->imageAttributeIds,
                    [$storagePath]
                ));

                // only remove image from filesystem if it is not used by other products
                // note! this only checks if the image has a role in a product, not if it is used in a gallery
                // the real check would be too slow
                if ($usageCount == 0) {
                    // remove original
                    @unlink(self::PRODUCT_IMAGE_PATH . $storagePath);
                    // removed resized caches
                    $this->emptyCache($storagePath);
                }

            }
        }

        $this->db->deleteMultiple($this->metaData->mediaGalleryTable, 'value_id', $obsoleteValueIds);
    }

    /**
     * Load data from existing product images
     * Returns image.jpg before image_1.jpg, image_2.jpg, ...
     *
     * @param Product $product
     * @return array
     */
    protected function loadExistingImageData(Product $product)
    {
        return $this->db->fetchAllAssoc("
            SELECT M.`value_id`, M.`value`, M.`disabled` 
            FROM {$this->metaData->mediaGalleryTable} M
            INNER JOIN {$this->metaData->mediaGalleryValueToEntityTable} E ON E.`value_id` = M.`value_id`
            WHERE E.`entity_id` = ? AND M.`attribute_id` = ?
            ORDER BY M.`value_id`
        ", [
            $product->id,
            $this->metaData->mediaGalleryAttributeId
        ]);
    }

    /**
     * An image "exists" if its path is stored in the database gallery for this product (perhaps with a suffix)
     * otherwise it is "new".
     *
     * @param Product $product
     * @param array $imageData
     * @return array
     */
    public function splitNewAndExistingImages(Product $product, array $imageData)
    {
        $existingImages = [];
        $newImages = [];

        foreach ($product->getImages() as $image) {

            $found = false;

            foreach ($imageData as $imageDatum) {

                $storagePath = $imageDatum['value'];
                $simpleStoragePath = preg_replace('/_\d+\.([^\.]+)$/', '.$1', $storagePath);

                if ($simpleStoragePath === $image->getDefaultStoragePath()) {
                    $found = true;
                    $image->valueId = $imageDatum['value_id'];
                    $image->setActualStoragePath($storagePath);
                    break;
                }
            }

            if (!$found) {
                $newImages[] = $image;
            } else {
                $existingImages[] = $image;
            }
        }

        return [$existingImages, $newImages];
    }

    public function insertImage(Product $product, Image $image)
    {
        $actualStoragePath = $this->move($image->getTemporaryStoragePath(), $image->getDefaultStoragePath());

        // first link the image (important to do this before storing the record)
        // then create the database record
        $imageValueId = $this->createImageValue($product->id, $actualStoragePath, $image->isEnabled());

        $image->valueId = $imageValueId;
        $image->setActualStoragePath($actualStoragePath);
    }

    public function move(string $temporaryStoragePath, $defaultStoragePath)
    {
        $actualStoragePath = $defaultStoragePath;

        if (file_exists(self::PRODUCT_IMAGE_PATH . $actualStoragePath)) {

            preg_match('/^(.*)\.([^.]+)$/', $actualStoragePath, $matches);
            $rest = $matches[1];
            $ext = $matches[2];

            $i = 0;
            do {
                $i++;
                $actualStoragePath = "{$rest}_{$i}.{$ext}";
            } while (file_exists(self::PRODUCT_IMAGE_PATH . $actualStoragePath));
        }

        $targetDir = dirname(self::PRODUCT_IMAGE_PATH . $actualStoragePath);
        if (!file_exists($targetDir)) {
            mkdir($targetDir, 0777, true);
        }

        // link image from its temporary position to its final position
        link($temporaryStoragePath, self::PRODUCT_IMAGE_PATH . $actualStoragePath);

        return $actualStoragePath;
    }

    protected function updateImage(Image $image)
    {
        $this->db->execute("
            UPDATE {$this->metaData->mediaGalleryTable}
            SET `disabled` = ?
            WHERE `value_id` = ? 
        ", [
            $image->isEnabled() ? '0' : '1',
            $image->valueId
        ]);

        $targetPath = self::PRODUCT_IMAGE_PATH . $image->getActualStoragePath();

        if (file_exists($targetPath)) {

            // only if the file is different in content will the old file be removed
            if (!$this->filesAreEqual($targetPath, $image->getTemporaryStoragePath())) {

                // remove original
                unlink($targetPath);
                // removed resized caches
                $this->emptyCache($image->getActualStoragePath());

                // link image from its temporary position to its final position
                link($image->getTemporaryStoragePath(), $targetPath);

            }

        } else {

            // the file that should have been there was removed
            $targetDir = dirname($targetPath);
            if (!file_exists($targetDir)) {
                mkdir($targetDir, 0777, true);
            }

            link($image->getTemporaryStoragePath(), $targetPath);
        }
    }

    protected function createImageValue($productId, string $storedPath, bool $enabled)
    {
        $attributeId = $this->metaData->mediaGalleryAttributeId;

        $this->db->execute("
            INSERT INTO {$this->metaData->mediaGalleryTable}
            SET `attribute_id` = ?, `value` = ?, `media_type` = 'image', `disabled` = ? 
        ", [
            $attributeId,
            $storedPath,
            $enabled ? '0' : '1'
        ]);

        $valueId = $this->db->getLastInsertId();

        $this->db->execute("
            INSERT INTO {$this->metaData->mediaGalleryValueToEntityTable}
            SET `value_id` = ?, `entity_id` = ? 
        ", [
            $valueId,
            $productId
        ]);

        return $valueId;
    }

    protected function upsertImageGalleryInformation($productId, $storeViewId, ImageGalleryInformation $imageGalleryInformation)
    {
        $image = $imageGalleryInformation->getImage();

        $recordId = $this->db->fetchSingleCell("
            SELECT `record_id`
            FROM {$this->metaData->mediaGalleryValueTable}
            WHERE `value_id` = ? AND `entity_id` = ? AND `store_id` = ?
        ", [
            $image->valueId,
            $productId,
            $storeViewId
        ]);

        $label = $imageGalleryInformation->getLabel();
        $position = $imageGalleryInformation->getPosition();
        $disabled = $imageGalleryInformation->isEnabled() ? '0' : '1';

        if ($recordId !== null) {

            $this->db->execute("
                UPDATE {$this->metaData->mediaGalleryValueTable}
                SET `label` = ?, `position` = ?, `disabled` = ?   
                WHERE `record_id` = ?
            ", [
                $label,
                $position,
                $disabled,
                $recordId
            ]);

        } else {

            $this->db->execute("
                INSERT INTO {$this->metaData->mediaGalleryValueTable}
                SET 
                    `value_id` = ?, 
                    `store_id` = ?, 
                    `entity_id` = ?, 
                    `label` = ?, 
                    `position` = ?, 
                    `disabled` = ?
            ", [
                $image->valueId,
                $storeViewId,
                $productId,
                $label,
                $position,
                $disabled
            ]);
        }
    }

    /**
     * @param Product[] $products
     */
    protected function insertImageRoles(array $products)
    {
        $attributeInfo = $this->metaData->productEavAttributeInfo['image'];
        $tableName = $attributeInfo->tableName;

        $values = [];

        foreach ($products as $product) {
            foreach ($product->getStoreViews() as $storeView) {
                foreach ($storeView->getImageRoles() as $attributeCode => $image) {

                    $values[] = $product->id;
                    $values[] = $this->metaData->productEavAttributeInfo[$attributeCode]->attributeId;
                    $values[] = $storeView->getStoreViewId();
                    $values[] = $image->getActualStoragePath();
                }
            }
        }

        $this->db->insertMultipleWithUpdate($tableName, ['entity_id', 'attribute_id', 'store_id', 'value'], $values,
            Magento2DbConnection::_1_KB, "`value` = VALUES(`value`)");
    }

    /**
     * From https://stackoverflow.com/questions/3060125/can-i-use-file-get-contents-to-compare-two-files
     *
     * @param $a
     * @param $b
     * @return bool
     */
    public function filesAreEqual($a, $b)
    {
        // Check if filesize is different
        if (filesize($a) !== filesize($b)) {
            return false;
        }

        // Check if content is different
        $ah = fopen($a, 'rb');
        $bh = fopen($b, 'rb');

        $result = true;
        while (!feof($ah)) {
            if (fread($ah, 65536) != fread($bh, 65536)) {
                $result = false;
                break;
            }
        }

        fclose($ah);
        fclose($bh);

        return $result;
    }
}