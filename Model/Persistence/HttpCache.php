<?php

namespace BigBridge\ProductImport\Model\Persistence;

use BigBridge\ProductImport\Api\ImportConfig;

/**
 * See
 *  https://tools.ietf.org/html/rfc7234
 *  https://developer.mozilla.org/en-US/docs/Web/HTTP/Caching
 *  https://serverfault.com/questions/130466/http-caching-headers-how-should-must-revalidate-work
 *
 * @author Patrick van Bergen
 */
class HttpCache
{
    const CACHE_CONTROL = 'cache-control';
    const EXPIRES = 'expires';
    const E_TAG = 'etag';
    const LAST_MODIFIED = 'last-modified';

    const IF_NONE_MATCH = 'if-none-match';
    const IF_MODIFIED_SINCE = 'if-modified-since';

    const NO_CACHE = '/no-cache/';
    const MUST_REVALIDATE = '/must-revalidate/';
    const NO_STORE = '/no-store/';
    const MAX_AGE = '/max-age=(\d+)/';

    const UNIX_TIME = 'unix-time';

    public function fetchFromUrl(string $imagePath, string $temporaryStoragePath, ImportConfig $config): string
    {
        $useHttpCache = $config->existingImageStrategy === ImportConfig::EXISTING_IMAGE_STRATEGY_HTTP_CACHING;
        $headerFile = $temporaryStoragePath . ".json";
        $conditions = [];

        if ($useHttpCache) {

            // cache must exist
            if (file_exists($temporaryStoragePath)) {

                // stored headers must exist
                if (file_exists($headerFile)) {

                    $fileContent = file_get_contents($headerFile);
                    if (!empty($fileContent)) {

                        $headers = json_decode($fileContent, true);
                        $now = time();
                        $useCache = $this->useCache($headers, $now);

                        if ($useCache === true) {
                            // $temporaryStoragePath still holds fresh data
                            return "";
                        } elseif ($useCache !== false) {
                            // use an e-tag while fetching image
                            $conditions = $useCache;
                        }
                    }
                }
            }
        }

        list($error, $responseHeaders) = $this->downloadFromUrl($imagePath, $temporaryStoragePath, $conditions);

        if ($useHttpCache) {
            if ($error === "") {
                file_put_contents($headerFile, json_encode($responseHeaders, JSON_PRETTY_PRINT));
            }
        }

        return $error;
    }

    /**
     * Can we use the cache?
     *
     * Returns true or false,
     * or an array of extra conditions
     *
     * @param array $headers
     * @param int $now
     * @return true|false|array
     */
    public function useCache(array $headers, int $now)
    {
        $requestUnixTime = $headers[self::UNIX_TIME];
        $cacheControl = $headers[self::CACHE_CONTROL];
        $expires = $headers[self::EXPIRES] ? strtotime($headers[self::EXPIRES]) : 0;
        $eTag = $headers[self::E_TAG];
        $lastModified = $headers[self::LAST_MODIFIED];

        $conditions = [];

        if (preg_match(self::NO_STORE, $cacheControl)) {

            // cache should not be used; the image should not have been downloaded in the first place (but we do it because it is easier to treat all images alike)
            return false;

        } else if (preg_match(self::MUST_REVALIDATE, $cacheControl)) {
            // cache is stale right away
        } else if (preg_match(self::NO_CACHE, $cacheControl)) {
            // cache is stale right away
        } else {

            // max-age
            if (preg_match(self::MAX_AGE, $cacheControl, $matches)) {
                $maxAge = $matches[1];
                if ($requestUnixTime + $maxAge > $now) {
                    return true;
                }
            }

            // expires
            if ($expires && $expires > $now) {
                return true;
            }
        }

        // e-tag / if-none-match
        if ($eTag) {
            $conditions[] = self::IF_NONE_MATCH . ':' . $eTag;
        }

        // last-modified / if-modified-since
        if ($lastModified) {
            $conditions[] = self::IF_MODIFIED_SINCE . ':' . $lastModified;
        }

        if ($conditions) {
            return $conditions;
        }

        return false;
    }

    public function downloadFromUrl(string $url, string $localTargetFile, ?array $conditions = null)
    {
        $responseHeaders = [
            self::UNIX_TIME => time(),
            self::CACHE_CONTROL => '',
            self::EXPIRES => null,
            self::E_TAG => null,
            self::LAST_MODIFIED => null,
        ];

        $tempFile = $localTargetFile . '.tmp';

        // temp file: if "if-none-match" or "if-modified-since" don't match, the file used will become empty during the request
        // this would create an empty cache file
        if ($conditions) {
            $fp = fopen($tempFile, 'w+');
        } else {
            $fp = fopen($localTargetFile, 'w+');
        }

        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_HEADERFUNCTION, function($curlResource, $header) use (&$responseHeaders) {
            if (preg_match('/^([^:]+):(.*)/', $header, $matches)) {
                $key = trim(strtolower($matches[1]));
                $value = trim($matches[2]);
                if (array_key_exists($key, $responseHeaders)) {
                    $responseHeaders[$key] = $value;
                }
            }
            return strlen($header);
        });

        if ($conditions) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $conditions);
        }

        // the actual request
        curl_exec($ch);

        $error = curl_error($ch);
        $httpResponseCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        fclose($fp);

        if ($conditions) {
            // file changed
            if (filesize($tempFile) > 0) {
                if (file_exists($localTargetFile)) {
                    unlink($localTargetFile);
                }
                rename($tempFile, $localTargetFile);
            } else {
                unlink($tempFile);
            }
        }

        if ($error) {
            $error .= ' for url ' . $url;
        } else {
            if ($httpResponseCode == 304) {
                // not modified (response returned from an "if-none-match" request)
            } elseif ($httpResponseCode == 404) {
                // special treatment for common case: not found
                $error = "Image url returned 404 (Not Found): " . $url;
            } elseif ($httpResponseCode != 200) {
                $error = "Image url returned " . $httpResponseCode . ' (' . $this->getHttpResponseDescription($httpResponseCode) . '): ' . $url;
            }
        }

        return [$error, $responseHeaders];
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
}
