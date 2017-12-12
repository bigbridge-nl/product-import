<?php

namespace BigBridge\ProductImport\Model\Resource;

use BigBridge\ProductImport\Api\Product;
use BigBridge\ProductImport\Model\Data\Image;
use BigBridge\ProductImport\Model\Data\ImageGalleryInformation;
use BigBridge\ProductImport\Model\Db\Magento2DbConnection;

/**
 * @author Patrick van Bergen
 */
class ImageStorage
{
    const PRODUCT_IMAGE_PATH = BP . "/pub/media/catalog/product";

    /** @var  Magento2DbConnection */
    protected $db;

    /** @var MetaData */
    protected $metaData;

    public function __construct(Magento2DbConnection $db, MetaData $metaData)
    {
        $this->db = $db;
        $this->metaData = $metaData;
    }

    /**
     * @param Product[] $products
     */
    public function storeProductImages(array $products)
    {
        foreach ($products as $product) {
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
            $this->storeImage($product, $image);
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
        $attributeId = $this->metaData->mediaGalleryAttributeId;

        // get data from existing product images
        $imageData = $this->db->fetchAllAssoc("
            SELECT M.`value_id`, M.`value`, M.`disabled` 
            FROM {$this->metaData->mediaGalleryTable} M
            INNER JOIN {$this->metaData->mediaGalleryValueToEntityTable} E ON E.`value_id` = M.`value_id`
            WHERE E.`entity_id` = {$product->id} AND M.`attribute_id` = {$attributeId} 
        ");

        $existingImages = [];
        $newImages = [];

        foreach ($product->getImages() as $image) {

            $found = false;

            foreach ($imageData as $imageDatum) {

                $storagePath = $imageDatum['value'];
                $simpleStoragePath = preg_replace('/_\d+\./', '.', $storagePath);

                if ($simpleStoragePath === $image->getDefaultStoragePath()) {
                    if ($this->filesAreEqual(self::PRODUCT_IMAGE_PATH . $storagePath, $image->getImagePath())) {
                        $found = true;
                        $image->valueId = $imageDatum['value_id'];
                        $image->setActualStoragePath($imageDatum['value']);
                        break;
                    }
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

    public function storeImage(Product $product, Image $image)
    {
        $defaultStoragePath = $image->getDefaultStoragePath();
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

        // move image to its final destination
        link($image->getTemporaryStoragePath(), self::PRODUCT_IMAGE_PATH . $actualStoragePath);

        // first link the image (important to do this before storing the record)
        // then create the database record
        $imageValueId = $this->createImageValue($product->id, $actualStoragePath, $image->isEnabled());

        $image->valueId = $imageValueId;
        $image->setActualStoragePath($actualStoragePath);
    }

    protected function updateImage(Image $image)
    {
        $dbDisabled = $image->isEnabled() ? '0' : '1';

        $this->db->execute("
            UPDATE {$this->metaData->mediaGalleryTable}
            SET `disabled` = {$dbDisabled}
            WHERE `value_id` = {$image->valueId} 
        ");
    }

    protected function createImageValue($productId, string $storedPath, bool $enabled)
    {
        $attributeId = $this->metaData->mediaGalleryAttributeId;

        $dbStoredPath = $this->db->quote($storedPath);
        $dbDisabled = $enabled ? '0' : '1';

        $this->db->execute("
            INSERT INTO {$this->metaData->mediaGalleryTable}
            SET `attribute_id` = {$attributeId}, `value` = {$dbStoredPath}, `media_type` = 'image', `disabled` = {$dbDisabled} 
        ");

        $valueId = $this->db->getLastInsertId();

        $this->db->execute("
            INSERT INTO {$this->metaData->mediaGalleryValueToEntityTable}
            SET `value_id` = {$valueId}, `entity_id` = {$productId} 
        ");

        return $valueId;
    }

    protected function upsertImageGalleryInformation($productId, $storeViewId, ImageGalleryInformation $imageGalleryInformation)
    {
        $image = $imageGalleryInformation->getImage();

        $recordId = $this->db->fetchSingleCell("
            SELECT `record_id`
            FROM {$this->metaData->mediaGalleryValueTable}
            WHERE `value_id` = {$image->valueId} AND `entity_id` = {$productId} AND `store_id` = {$storeViewId}
        ");

        $dbLabel = $this->db->quote($imageGalleryInformation->getLabel());
        $dbPosition = $imageGalleryInformation->getPosition();
        $dbDisabled = $imageGalleryInformation->isEnabled() ? '0' : '1';

        if ($recordId !== null) {

            $this->db->execute("
                UPDATE {$this->metaData->mediaGalleryValueTable}
                SET `label` = {$dbLabel}, `position` = {$dbPosition}, `disabled` = {$dbDisabled}   
                WHERE `record_id` = $recordId
            ");

        } else {

            $this->db->execute("
                INSERT INTO {$this->metaData->mediaGalleryValueTable}
                SET `value_id` = {$image->valueId}, `store_id` = {$storeViewId}, `entity_id` = {$productId}, `label` = {$dbLabel}, `position` = {$dbPosition}, `disabled` = {$dbDisabled}
            ");
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

                    $attributeId = $this->metaData->productEavAttributeInfo[$attributeCode]->attributeId;
                    $storeViewId = $storeView->getStoreViewId();
                    $value = $this->db->quote($image->getActualStoragePath());
                    $values[] = "({$product->id}, {$attributeId}, {$storeViewId}, {$value})";
                }
            }
        }

        if (!empty($values)) {

            $sql = "INSERT INTO `{$tableName}` (`entity_id`, `attribute_id`, `store_id`, `value`)" .
                " VALUES " . implode(', ', $values) .
                " ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)";

            $this->db->execute($sql);
        }
    }

    /**
     * From https://stackoverflow.com/questions/3060125/can-i-use-file-get-contents-to-compare-two-files
     *
     * @param $a
     * @param $b
     * @return bool
     */
    protected function filesAreEqual($a, $b)
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