<?php

namespace BigBridge\ProductImport\Model\Resource\Validation;

use BigBridge\ProductImport\Api\Data\DownloadableProduct;

/**
 * @author Patrick van Bergen
 */
class DownloadableValidator
{
    /**
     * @param DownloadableProduct $product
     */
    public function validate(DownloadableProduct $product)
    {
        $this->validateLinkImages($product);
    }

    protected function validateLinkImages(DownloadableProduct $product)
    {
        if (($links = $product->getDownloadLinks()) !== null) {
            foreach ($links as $downloadLink) {
                $fileOrUrl = $downloadLink->getFileOrUrl();
                $downloadLink->setTemporaryStoragePathLink($this->getTemporaryStoragePath($product, $fileOrUrl));

                $sampleFileOrUrl = $downloadLink->getSampleFileOrUrl();
                $downloadLink->setTemporaryStoragePathSample($this->getTemporaryStoragePath($product, $sampleFileOrUrl));
            }
        }

        if (($samples = $product->getDownloadSamples()) !== null) {
            foreach ($samples as $downloadSample) {
                $sampleFileOrUrl = $downloadSample->getFileOrUrl();
                $downloadSample->setTemporaryStoragePathSample($this->getTemporaryStoragePath($product, $sampleFileOrUrl));
            }
        }
    }

    protected function getTemporaryStoragePath(DownloadableProduct $product, $fileOrUrl)
    {
        if ($fileOrUrl === '') {
            return null;
        } elseif (preg_match('#^(http://|https://|://)#i', $fileOrUrl)) {
            return null;
        } else {

            $temporaryStoragePath = sys_get_temp_dir() . '/' . uniqid() . basename($fileOrUrl);

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
}