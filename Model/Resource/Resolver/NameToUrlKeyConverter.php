<?php

namespace BigBridge\ProductImport\Model\Resource\Resolver;

/**
 * @author Patrick van Bergen
 */
class NameToUrlKeyConverter
{
    public function createUrlKeyFromName(string $name)
    {
        $key = $name;
        $key = strtolower($key);
        $key = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $key);
        $key = preg_replace("/[^a-z0-9]/", "-", $key);
        $key = preg_replace("/-{2,}/", "-", $key);
        $key = trim($key, '-');

        return $key;
    }

    public function createUniqueUrlKeyFromName(string $name, array $excluded)
    {
        $urlKey = $this->createUrlKeyFromName($name);

        if (in_array($urlKey, $excluded)) {

            $suffix = 0;

            do {
                $suffix++;
                $uniqueUrlKey = $urlKey . '_' . $suffix;
            } while (in_array($uniqueUrlKey, $excluded));

            $urlKey = $uniqueUrlKey;
        }

        return $urlKey;
    }
}