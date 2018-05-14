<?php

namespace BigBridge\ProductImport\Model\Resource\Storage;

use BigBridge\ProductImport\Api\Data\Product;
use BigBridge\ProductImport\Api\ImportConfig;
use BigBridge\ProductImport\Model\Data\Image;
use BigBridge\ProductImport\Model\Data\ImageGalleryInformation;
use BigBridge\ProductImport\Model\Db\Magento2DbConnection;
use BigBridge\ProductImport\Model\Resource\MetaData;

/**
 * @author Patrick van Bergen
 */
class ImageStorage
{
    const TEMP_PRODUCT_IMAGE_PATH = BP . "/pub/media/import";
    const PRODUCT_IMAGE_PATH = BP . "/pub/media/catalog/product";

    /** @var  Magento2DbConnection */
    protected $db;

    /** @var MetaData */
    protected $metaData;

    public function __construct(Magento2DbConnection $db, MetaData $metaData)
    {
        $this->db = $db;
        $this->metaData = $metaData;

        if (!file_exists(self::TEMP_PRODUCT_IMAGE_PATH)) {
            mkdir(self::TEMP_PRODUCT_IMAGE_PATH, 0777, true);
        }
    }

    public function moveImagesToTemporaryLocation(Product $product, ImportConfig $config)
    {
        foreach ($product->getImages() as $image) {

            $imagePath = $image->getImagePath();

            if (!preg_match('/\.(png|jpg|jpeg|gif)$/i', $imagePath)) {
                $product->addError("Filetype not allowed (use .jpg, .png or .gif): " . $imagePath);
            }

            $temporaryStoragePath = self::TEMP_PRODUCT_IMAGE_PATH . '/' . md5($image->getImagePath()) . '-' . basename($image->getImagePath());


            // temporary file exists?
            if (file_exists($temporaryStoragePath)) {
                if ($config->existingImageStrategy === ImportConfig::EXISTING_IMAGE_STRATEGY_CHECK_IMPORT_DIR) {
                    goto end;
                } else {
                    // contents of new file may be different, remove old file
                    unlink($temporaryStoragePath);
                }
            }

            if (preg_match('#(https?:)?//#i', $imagePath)) {
                $error = $this->downloadFromUrl($imagePath, $temporaryStoragePath);
                if ($error !== '') {
                    $product->addError($error);
                    continue;
                }
            } elseif (!is_file($imagePath)) {
                $product->addError("File not found: " . $imagePath);
                continue;
            } elseif (stat($imagePath)['dev'] !== stat(__FILE__)['dev']) {
                // file is on different device
                copy($imagePath, $temporaryStoragePath);
            } elseif (!file_exists($temporaryStoragePath)) {
                // file is on same device
                link($imagePath, $temporaryStoragePath);
            }

            if (!file_exists($temporaryStoragePath)) {
                $product->addError("File was not copied to temporary storage: " . $imagePath);
                continue;
            }

            if (filesize($temporaryStoragePath) === 0) {
                $product->addError("File is empty: " . $imagePath);
                unlink($temporaryStoragePath);
                continue;
            }

            end:

            $image->setTemporaryStoragePath($temporaryStoragePath);
        }
    }

    protected function downloadFromUrl(string $url, string $localTargetFile)
    {
        $fp = fopen ($localTargetFile, 'w+');
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_exec($ch);

        $error = curl_error($ch);
        $httpResponseCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);
        fclose($fp);

        if ($error) {
            $error .= ' for url ' . $url;
        } else {
            if ($httpResponseCode == 404) {
                $error = "Image url returned 404 (Not Found): " . $url;
            } elseif ($httpResponseCode != 200) {
                $error = "Image url returned " . $httpResponseCode . ' (' . $this->getHttpResponseDescription($httpResponseCode) . '): ' . $url;
            }
        }

        return $error;
    }

    /**
     * From http://php.net/manual/en/function.http-response-code.php
     *
     * @param $responseCode
     * @return string
     */
    protected function getHttpResponseDescription(int $responseCode)
    {
        switch ($responseCode) {
            case 100:
                $text = 'Continue';
                break;
            case 101:
                $text = 'Switching Protocols';
                break;
            case 200:
                $text = 'OK';
                break;
            case 201:
                $text = 'Created';
                break;
            case 202:
                $text = 'Accepted';
                break;
            case 203:
                $text = 'Non-Authoritative Information';
                break;
            case 204:
                $text = 'No Content';
                break;
            case 205:
                $text = 'Reset Content';
                break;
            case 206:
                $text = 'Partial Content';
                break;
            case 300:
                $text = 'Multiple Choices';
                break;
            case 301:
                $text = 'Moved Permanently';
                break;
            case 302:
                $text = 'Moved Temporarily';
                break;
            case 303:
                $text = 'See Other';
                break;
            case 304:
                $text = 'Not Modified';
                break;
            case 305:
                $text = 'Use Proxy';
                break;
            case 400:
                $text = 'Bad Request';
                break;
            case 401:
                $text = 'Unauthorized';
                break;
            case 402:
                $text = 'Payment Required';
                break;
            case 403:
                $text = 'Forbidden';
                break;
            case 404:
                $text = 'Not Found';
                break;
            case 405:
                $text = 'Method Not Allowed';
                break;
            case 406:
                $text = 'Not Acceptable';
                break;
            case 407:
                $text = 'Proxy Authentication Required';
                break;
            case 408:
                $text = 'Request Time-out';
                break;
            case 409:
                $text = 'Conflict';
                break;
            case 410:
                $text = 'Gone';
                break;
            case 411:
                $text = 'Length Required';
                break;
            case 412:
                $text = 'Precondition Failed';
                break;
            case 413:
                $text = 'Request Entity Too Large';
                break;
            case 414:
                $text = 'Request-URI Too Large';
                break;
            case 415:
                $text = 'Unsupported Media Type';
                break;
            case 500:
                $text = 'Internal Server Error';
                break;
            case 501:
                $text = 'Not Implemented';
                break;
            case 502:
                $text = 'Bad Gateway';
                break;
            case 503:
                $text = 'Service Unavailable';
                break;
            case 504:
                $text = 'Gateway Time-out';
                break;
            case 505:
                $text = 'HTTP Version not supported';
                break;
            default:
                $text = '';
                break;
        }

        return $text;
    }

    /**
     * @param Product[] $products
     */
    public function storeProductImages(array $products)
    {
        foreach ($products as $product) {

            if (empty($product->getImages())) {
                continue;
            }

            $this->storeImages($product);
        }

        $this->insertImageRoles($products);
    }

    protected function storeImages(Product $product)
    {
        // separates new from existing images
        // add valueId and actualStoragePath to existing images
        list($existingImages, $newImages) = $this->splitNewAndExistingImages($product);

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
    }

    public function splitNewAndExistingImages(Product $product)
    {
        // get data from existing product images
        $imageData = $this->db->fetchAllAssoc("
            SELECT M.`value_id`, M.`value`, M.`disabled` 
            FROM {$this->metaData->mediaGalleryTable} M
            INNER JOIN {$this->metaData->mediaGalleryValueToEntityTable} E ON E.`value_id` = M.`value_id`
            WHERE E.`entity_id` = ? AND M.`attribute_id` = ? 
        ", [
            $product->id,
            $this->metaData->mediaGalleryAttributeId
        ]);

        $existingImages = [];
        $newImages = [];

        foreach ($product->getImages() as $image) {

            $found = false;

            foreach ($imageData as $imageDatum) {

                $storagePath = $imageDatum['value'];
                $simpleStoragePath = preg_replace('/_\d+\./', '.', $storagePath);

                if ($simpleStoragePath === $image->getDefaultStoragePath()) {
                    $found = true;
                    $image->valueId = $imageDatum['value_id'];
                    $image->setActualStoragePath($imageDatum['value']);
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

        // only if the file is different in content will the old file be removed
        if (!$this->filesAreEqual($targetPath, $image->getTemporaryStoragePath())) {

            unlink($targetPath);

            // link image from its temporary position to its final position
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