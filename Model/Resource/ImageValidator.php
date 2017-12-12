<?php

namespace BigBridge\ProductImport\Model\Resource;

use BigBridge\ProductImport\Api\Product;

/**
 * @author Patrick van Bergen
 */
class ImageValidator
{
    public function validateImages(Product $product)
    {
        foreach ($product->getImages() as $image) {

            $imagePath = $image->getImagePath();

            if (!preg_match('/\.(png|jpg|jpeg|gif)$/i', $imagePath)) {
                $product->addError("Filetype not allowed (use .jpg, .png or .gif): " . $imagePath);
            }

            $temporaryStoragePath = sys_get_temp_dir() . '/' . uniqid() . basename($image->getImagePath());

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
            } else {
                // file is on same device
                link($imagePath, $temporaryStoragePath);
            }

            if (!file_exists($temporaryStoragePath)) {
                $product->addError("File was not copied to temporary storage: " . $imagePath);
                continue;
            }

            if (filesize($temporaryStoragePath) === 0) {
                $product->addError("File is empty: " . $imagePath);
                continue;
            }

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
     */
    protected function getHttpResponseDescription($responseCode)
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
}