<?php

namespace BigBridge\ProductImport\Model\Resource;

use BigBridge\ProductImport\Model\ImportConfig;

/**
 * This class converts human readable attribute value names to their database ids.
 *
 * @author Patrick van Bergen
 */
class NameConverter
{
    const NOT_FOUND = '-- not found --';

    /** @var MetaData */
    protected $metaData;

    /** @var  ImportConfig */
    protected $config;

    /** @var array  */
    protected $map;

    /** @var CategoryImporter */
    protected $categoryImporter;

    /** @var  array */
    protected $categoryCache = [];

    public function __construct(ImportConfig $config, MetaData $metaData, CategoryImporter $categoryImporter)
    {
        $this->config = $config;
        $this->metaData = $metaData;

        $this->map = $this->loadNameToIdMap();
        $this->categoryImporter = $categoryImporter;
    }

    /**
     * @param string $attributeCode
     * @param string $attributeValue
     * @return int|string
     */
    public function convertNameToId(string $attributeCode, string $attributeName)
    {
        if (array_key_exists($attributeCode, $this->map)) {
            $names = $this->map[$attributeCode];
            if (array_key_exists($attributeName, $names)) {
                return $names[$attributeName];
            }
        }

        return self::NOT_FOUND;
    }

    /**
     * Returns the names of the categories.
     * Category names may be paths separated with /
     *
     * @param array $categoryPaths
     * @return int[]
     */
    public function convertCategoryNamesToIds(array $categoryPaths)
    {
        $ids = [];

        foreach ($categoryPaths as $path) {
            if (array_key_exists($path, $this->categoryCache)) {
                $id = $this->categoryCache[$path];
                $ids[] = $id;
            } else {
                $id = $this->categoryImporter->importCategoryPath($path);
                $this->categoryCache[$path] = $id;
                $ids[] = $id;
            }
        }

        return $ids;
    }

    protected function loadNameToIdMap()
    {
        $map = [];

        $map['attribute_set_id'] = $this->metaData->productAttributeSetMap;
        $map['store_view_id'] = $this->metaData->storeViewMap;
        $map['tax_class_id'] = $this->metaData->taxClassMap;

        return $map;
    }
}