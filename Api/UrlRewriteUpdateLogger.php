<?php

namespace BigBridge\ProductImport\Api;


/**
 * @author Patrick van Bergen
 */
interface UrlRewriteUpdateLogger
{
    /**
     * @param string $info
     */
    public function info(string $info);
}