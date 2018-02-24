<?php

namespace BigBridge\ProductImport\Model\Resource\Storage;

use BigBridge\ProductImport\Api\Data\Product;
use BigBridge\ProductImport\Model\Data\Image;
use BigBridge\ProductImport\Model\Data\ImageGalleryInformation;
use BigBridge\ProductImport\Model\Db\Magento2DbConnection;
use BigBridge\ProductImport\Model\Resource\MetaData;

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

        // move image from its temporary position to its final position
        rename($temporaryStoragePath, self::PRODUCT_IMAGE_PATH . $actualStoragePath);

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
        if (!$this->filesAreEqual($targetPath, $image->getImagePath())) {

            unlink($targetPath);

            // move image from its temporary position to its final position
            rename($image->getTemporaryStoragePath(), $targetPath);

        } else {

            // the old file is the same as the new file, no move is needed
            // clean up temporary file
            unlink($image->getTemporaryStoragePath());
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

        $this->db->insertMultipleWithUpdate($tableName, ['entity_id', 'attribute_id', 'store_id', 'value'], $values, "`value` = VALUES(`value`)");
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