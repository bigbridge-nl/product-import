<?php

namespace BigBridge\ProductImport\Model\Resource\Resolver;

use BigBridge\ProductImport\Api\Product;
use BigBridge\ProductImport\Api\ImportConfig;
use BigBridge\ProductImport\Model\Resource\Reference\Reference;
use BigBridge\ProductImport\Model\Resource\Reference\References;

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

    /** @var StoreViewResolver */
    protected $storeViewResolver;

    /** @var WebsiteResolver */
    protected $websiteResolver;

    /** @var OptionResolver */
    protected $optionResolver;

    /** @var LinkedProductReferenceResolver */
    protected $linkedProductReferenceResolver;

    /** @var TierPriceResolver */
    protected $tierPriceResolver;

    public function __construct(
        CategoryImporter $categoryImporter,
        TaxClassResolver $taxClassResolver,
        AttributeSetResolver $attributeSetResolver,
        StoreViewResolver $storeViewResolver,
        WebsiteResolver $websiteResolver,
        OptionResolver $optionResolver,
        LinkedProductReferenceResolver $linkedProductReferenceResolver,
        TierPriceResolver $tierPriceResolver)
    {
        $this->categoryImporter = $categoryImporter;
        $this->taxClassResolver = $taxClassResolver;
        $this->attributeSetResolver = $attributeSetResolver;
        $this->storeViewResolver = $storeViewResolver;
        $this->websiteResolver = $websiteResolver;
        $this->optionResolver = $optionResolver;
        $this->linkedProductReferenceResolver = $linkedProductReferenceResolver;
        $this->tierPriceResolver = $tierPriceResolver;
    }

    /**
     * @param Product[] $products
     * @param ImportConfig $config
     * @throws \Exception
     */
    public function resolveIds(array $products, ImportConfig $config)
    {
        // linked product references (related, up sell, cross sell
        $this->linkedProductReferenceResolver->resolveLinkedProductReferences($products);

        // resolve customer groups and websites in tier prices
        $this->tierPriceResolver->resolveReferences($products);

        foreach ($products as $product) {
            if ($product->getCategoryIds() instanceof References) {
                list($ids, $error) = $this->categoryImporter->importCategoryPaths($product->getCategoryIds()->names,
                    $config->autoCreateCategories, $config->categoryNamePathSeparator);
                $product->setCategoryIds($ids);
                if ($error !== "") {
                    $product->addError($error);
                    $product->setCategoryIds([]);
                }
            }

            if ($product->getWebsiteIds() instanceof References) {
                list($ids, $error) = $this->websiteResolver->resolveCodes($product->getWebsiteIds()->names);
                if ($error === "") {
                    $product->setWebsitesIds($ids);
                } else {
                    $product->addError($error);
                    $product->removeWebsiteIds();
                }
            }

            if ($product->getAttributeSetId() instanceof Reference) {
                list($id, $error) = $this->attributeSetResolver->resolveName($product->getAttributeSetId()->name);
                if ($error === "") {
                    $product->setAttributeSetId($id);
                } else {
                    $product->addError($error);
                    $product->removeAttributeSetId();
                }
            }

            foreach ($product->getStoreViews() as $storeViewCode => $storeView) {

                $attributes = $storeView->getAttributes();

                list($id, $error) = $this->storeViewResolver->resolveName($storeViewCode);
                if ($error === "") {
                    $storeView->setStoreViewId($id);
                } else {
                    $product->addError($error);
                    $storeView->removeStoreViewId();
                }

                if (array_key_exists('tax_class_id', $attributes) && $attributes['tax_class_id'] instanceof Reference) {
                    list($id, $error) = $this->taxClassResolver->resolveName($attributes['tax_class_id']->name);
                    if ($error === "") {
                        $storeView->setTaxClassId($id);
                    } else {
                        $product->addError($error);
                        $storeView->removeAttribute('tax_class_id');
                    }
                }

                foreach ($storeView->getUnresolvedSelects() as $attribute => $optionName) {
                    list ($id, $error) = $this->optionResolver->resolveOption($attribute, $optionName, $config->autoCreateOptionAttributes);
                    if ($error === "") {
                        $storeView->setSelectAttributeOptionId($attribute, $id);
                    } else {
                        $product->addError($error);
                        $storeView->removeAttribute($attribute);
                    }
                }

                foreach ($storeView->getUnresolvedMultipleSelects() as $attribute => $optionNames) {
                    list ($ids, $error) = $this->optionResolver->resolveOptions($attribute, $optionNames, $config->autoCreateOptionAttributes);
                    if ($error === "") {
                        $storeView->setMultiSelectAttributeOptionIds($attribute, $ids);
                    } else {
                        $product->addError($error);
                        $storeView->removeAttribute($attribute);
                    }
                }
            }
        }
    }
}