<?php

namespace BigBridge\ProductImport\Model\Resource;

use BigBridge\ProductImport\Model\Data\Product;
use BigBridge\ProductImport\Model\Id;
use BigBridge\ProductImport\Model\Ids;
use BigBridge\ProductImport\Model\Resource\Id\AttributeSetResolver;
use BigBridge\ProductImport\Model\Resource\Id\CategoryImporter;
use BigBridge\ProductImport\Model\Resource\Id\StoreViewResolver;
use BigBridge\ProductImport\Model\Resource\Id\TaxClassResolver;

/**
 * @author Patrick van Bergen
 */
class IdResolver
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

    public function resolveIds(Product $product)
    {
        if ($product->category_ids instanceof Ids) {
            list($ids, $error) = $this->categoryImporter->importCategoryPaths($product->category_ids->names);
            if ($error !== "") {
                $product->ok = false;
                $product->error[]= $error;
                $product->category_ids = null;
            } else {
                $product->category_ids = $ids;
            }
        }

        if ($product->tax_class_id instanceof Id) {
            list($id, $error) = $this->taxClassResolver->resolveName($product->tax_class_id->name);
            if ($error !== "") {
                $product->ok = false;
                $product->error[]= $error;
                $product->tax_class_id = null;
            } else {
                $product->tax_class_id = $id;
            }
        }

        if ($product->store_view_id instanceof Id) {
            list($id, $error) = $this->storeViewResolver->resolveName($product->store_view_id->name);
            if ($error !== "") {
                $product->ok = false;
                $product->error[]= $error;
                $product->store_view_id = null;
            } else {
                $product->store_view_id = $id;
            }
        }

        if ($product->attribute_set_id instanceof Id) {
            list($id, $error) = $this->attributeSetResolver->resolveName($product->attribute_set_id->name);
            if ($error !== "") {
                $product->ok = false;
                $product->error[]= $error;
                $product->attribute_set_id = null;
            } else {
                $product->attribute_set_id = $id;
            }
        }
    }
}