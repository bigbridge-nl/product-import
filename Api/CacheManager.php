<?php

namespace BigBridge\ProductImport\Api;

use BigBridge\ProductImport\Model\Resource\MetaData;
use BigBridge\ProductImport\Model\Resource\Resolver\CategoryImporter;
use BigBridge\ProductImport\Model\Resource\Resolver\OptionResolver;

/**
 * This class has access to all caches of the importer.
 * Use it (sparingly) to flush caches when you know that other processes have modified the same data.
 *
 * @author Patrick van Bergen
 */
class CacheManager
{
    /** @var MetaData */
    protected $metaData;

    /** @var CategoryImporter */
    protected $categoryImporter;

    /** @var OptionResolver */
    protected $optionResolver;

    public function __construct(
        MetaData $metaData,
        CategoryImporter $categoryImporter,
        OptionResolver $optionResolver
    )
    {
        $this->categoryImporter = $categoryImporter;
        $this->optionResolver = $optionResolver;
        $this->metaData = $metaData;
    }

    /**
     * Clears and reloads all caches.
     * Uses one of the more restricted methods of this class if you can.
     */
    public function resetAll()
    {
        $this->categoryImporter->clearCache();
        $this->optionResolver->clearCache();
        $this->metaData->reloadCache();
    }

    /**
     * Removes all cached attribute option values. They will be reloaded from the database when needed.
     */
    public function clearOptionResolverCache()
    {
        $this->optionResolver->clearCache();
    }

    /**
     * Removes all cached categories. They will be reloaded from the database when needed.
     */
    public function clearCategoryCache()
    {
        $this->categoryImporter->clearCache();
    }

    /**
     * Reloads all data that is necessary for (but not part of) importing products.
     */
    public function reloadMetaData()
    {
        $this->metaData->reloadCache();
    }
}