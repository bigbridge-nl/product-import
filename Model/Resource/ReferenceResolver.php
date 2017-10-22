<?php

namespace BigBridge\ProductImport\Model\Resource;

use BigBridge\ProductImport\Model\Data\Product;
use BigBridge\ProductImport\Model\ImportConfig;
use BigBridge\ProductImport\Model\Reference;
use BigBridge\ProductImport\Model\References;
use BigBridge\ProductImport\Model\Resource\Id\AttributeSetResolver;
use BigBridge\ProductImport\Model\Resource\Id\CategoryImporter;
use BigBridge\ProductImport\Model\Resource\Id\StoreViewResolver;
use BigBridge\ProductImport\Model\Resource\Id\TaxClassResolver;

/**
 * Replaces all Reference(s) values of a product with database ids.
 *
 * @author Patrick van Bergen
 */
class ReferenceResolver
{
    /** @var CategoryImporter */
    protected $categoryImporter;

    /** @var TaxClassResolver */
    protected $taxClassResolver;

    /** @var AttributeSetResolver */
    protected $attributeSetResolver;

    /**@var StoreViewResolver */
    protected $storeViewResolver;

    public function __construct(
        CategoryImporter $categoryImporter,
        TaxClassResolver $taxClassResolver,
        AttributeSetResolver $attributeSetResolver,
        StoreViewResolver $storeViewResolver)
    {
        $this->categoryImporter = $categoryImporter;
        $this->taxClassResolver = $taxClassResolver;
        $this->attributeSetResolver = $attributeSetResolver;
        $this->storeViewResolver = $storeViewResolver;
    }

    public function resolveIds(Product $product, ImportConfig $config)
    {
        if ($product->category_ids instanceof References) {
            list($ids, $error) = $this->categoryImporter->importCategoryPaths($product->category_ids->names, $config->autoCreateCategories);
            $product->category_ids = $ids;
            if ($error !== "") {
                $product->ok = false;
                $product->errors[]= $error;
            }
        }

        if ($product->tax_class_id instanceof Reference) {
            list($id, $error) = $this->taxClassResolver->resolveName($product->tax_class_id->name);
            $product->tax_class_id = $id;
            if ($error !== "") {
                $product->ok = false;
                $product->errors[]= $error;
                $product->tax_class_id = null;
            }
        }

        if ($product->store_view_id instanceof Reference) {
            list($id, $error) = $this->storeViewResolver->resolveName($product->store_view_id->name);
            $product->store_view_id = $id;
            if ($error !== "") {
                $product->ok = false;
                $product->errors[]= $error;
                $product->store_view_id = null;
            }
        }

        if ($product->attribute_set_id instanceof Reference) {
            list($id, $error) = $this->attributeSetResolver->resolveName($product->attribute_set_id->name);
            $product->attribute_set_id = $id;
            if ($error !== "") {
                $product->ok = false;
                $product->errors[]= $error;
                $product->attribute_set_id = null;
            }
        }
    }
}