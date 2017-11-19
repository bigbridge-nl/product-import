<?php

namespace BigBridge\ProductImport\Model\Resource;

use BigBridge\ProductImport\Model\Data\Product;
use BigBridge\ProductImport\Model\ImportConfig;
use BigBridge\ProductImport\Model\Reference;
use BigBridge\ProductImport\Model\References;
use BigBridge\ProductImport\Model\Resource\Reference\AttributeSetResolver;
use BigBridge\ProductImport\Model\Resource\Reference\CategoryImporter;
use BigBridge\ProductImport\Model\Resource\Reference\StoreViewResolver;
use BigBridge\ProductImport\Model\Resource\Reference\TaxClassResolver;
use BigBridge\ProductImport\Model\Resource\Reference\WebsiteResolver;

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

    /**@var WebsiteResolver */
    protected $websiteResolver;

    public function __construct(
        CategoryImporter $categoryImporter,
        TaxClassResolver $taxClassResolver,
        AttributeSetResolver $attributeSetResolver,
        StoreViewResolver $storeViewResolver,
        WebsiteResolver $websiteResolver)
    {
        $this->categoryImporter = $categoryImporter;
        $this->taxClassResolver = $taxClassResolver;
        $this->attributeSetResolver = $attributeSetResolver;
        $this->storeViewResolver = $storeViewResolver;
        $this->websiteResolver = $websiteResolver;
    }

    public function resolveIds(Product $product, ImportConfig $config)
    {
        if ($product->category_ids instanceof References) {
            list($ids, $error) = $this->categoryImporter->importCategoryPaths($product->category_ids->names, $config->autoCreateCategories);
            $product->category_ids = $ids;
            if ($error !== "") {
                $product->ok = false;
                $product->errors[] = $error;
            }
        }

        if ($product->attribute_set_id instanceof Reference) {
            list($id, $error) = $this->attributeSetResolver->resolveName($product->attribute_set_id->name);
            $product->attribute_set_id = $id;
            if ($error !== "") {
                $product->ok = false;
                $product->errors[] = $error;
            }
        }

        foreach ($product->getStoreViews() as $storeView) {

            list($id, $error) = $this->storeViewResolver->resolveName($storeView->storeViewCode);
            $storeView->store_view_id = $id;
            if ($error !== "") {
                $product->ok = false;
                $product->errors[] = $error;
            }

            if ($storeView->tax_class_id instanceof Reference) {
                list($id, $error) = $this->taxClassResolver->resolveName($storeView->tax_class_id->name);
                $storeView->tax_class_id = $id;
                if ($error !== "") {
                    $product->ok = false;
                    $product->errors[] = $error;
                }
            }

            if ($storeView->website_ids instanceof References) {
                list($ids, $error) = $this->websiteResolver->resolveNames($storeView->website_ids->names);
                $storeView->website_ids = $ids;
                if ($error !== "") {
                    $product->ok = false;
                    $product->errors[] = $error;
                }
            }
        }
    }
}