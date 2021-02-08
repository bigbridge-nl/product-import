<?php

namespace BigBridge\ProductImport\Model\Resource\Resolver;

use BigBridge\ProductImport\Api\Data\BundleProduct;
use BigBridge\ProductImport\Api\Data\ConfigurableProduct;
use BigBridge\ProductImport\Api\Data\GroupedProduct;
use BigBridge\ProductImport\Api\Data\Product;
use BigBridge\ProductImport\Api\Data\ProductStoreView;
use BigBridge\ProductImport\Api\ImportConfig;

/**
 * Replaces all reference(s) values of a product with database ids.
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

    /** @var SourceResolver */
    protected $sourceResolver;

    /** @var WebsiteResolver */
    protected $websiteResolver;

    /** @var WeeeResolver */
    protected $weeeResolver;

    /** @var OptionResolver */
    protected $optionResolver;

    /** @var LinkedProductReferenceResolver */
    protected $linkedProductReferenceResolver;

    /** @var TierPriceResolver */
    protected $tierPriceResolver;

    /** @var BundleProductReferenceResolver */
    protected $bundleProductReferenceResolver;

    /** @var GroupedProductReferenceResolver */
    protected $groupedProductReferenceResolver;

    /** @var ConfigurableProductReferenceResolver */
    protected $configurableProductReferenceResolver;

    public function __construct(
        CategoryImporter $categoryImporter,
        TaxClassResolver $taxClassResolver,
        AttributeSetResolver $attributeSetResolver,
        StoreViewResolver $storeViewResolver,
        SourceResolver $sourceResolver,
        WebsiteResolver $websiteResolver,
        WeeeResolver $weeeResolver,
        OptionResolver $optionResolver,
        LinkedProductReferenceResolver $linkedProductReferenceResolver,
        TierPriceResolver $tierPriceResolver,
        BundleProductReferenceResolver $bundleProductReferenceResolver,
        GroupedProductReferenceResolver $groupedProductReferenceResolver,
        ConfigurableProductReferenceResolver $configurableProductReferenceResolver
    )
    {
        $this->categoryImporter = $categoryImporter;
        $this->taxClassResolver = $taxClassResolver;
        $this->attributeSetResolver = $attributeSetResolver;
        $this->storeViewResolver = $storeViewResolver;
        $this->sourceResolver = $sourceResolver;
        $this->websiteResolver = $websiteResolver;
        $this->weeeResolver = $weeeResolver;
        $this->optionResolver = $optionResolver;
        $this->linkedProductReferenceResolver = $linkedProductReferenceResolver;
        $this->tierPriceResolver = $tierPriceResolver;
        $this->bundleProductReferenceResolver = $bundleProductReferenceResolver;
        $this->groupedProductReferenceResolver = $groupedProductReferenceResolver;
        $this->configurableProductReferenceResolver = $configurableProductReferenceResolver;
    }

    /**
     * @param Product[] $products
     * @param ImportConfig $config
     * @throws \Exception
     */
    public function resolveExternalReferences(array $products, ImportConfig $config)
    {
        // resolve customer groups and websites in tier prices
        $this->tierPriceResolver->resolveReferences($products);

        $productsByType = [];

        foreach ($products as $product) {

            $productsByType[$product->getType()][] = $product;

            foreach ($product->getUnresolvedAttributes() as $attribute => $value) {
                switch ($attribute) {
                    case Product::ATTRIBUTE_SET_ID:
                        list($id, $error) = $this->attributeSetResolver->resolveName($value);
                        if ($error === "") {
                            $product->setAttributeSetId($id);
                        } else {
                            $product->addError($error);
                            $product->removeAttributeSetId();
                        }
                        break;
                    case Product::CATEGORY_IDS:
                        list($ids, $error) = $this->categoryImporter->importCategoryPaths($value,
                            $config->autoCreateCategories, $config->categoryNamePathSeparator, $config->categoryUrlType);
                        $product->addCategoryIds($ids);
                        if ($error !== "") {
                            $product->addError($error);
                            $product->addCategoryIds([]);
                        }
                        break;
                    case Product::WEBSITE_IDS:
                        list($ids, $error) = $this->websiteResolver->resolveCodes($value);
                        if ($error === "") {
                            $product->setWebsitesIds($ids);
                        } else {
                            $product->addError($error);
                            $product->removeWebsiteIds();
                        }
                        break;
                    case Product::WEEE_ATTRIBUTE:
                        $error = $this->weeeResolver->resolveWeeeAttributeId();
                        if ($error !== "") {
                            $product->addError($error);
                        }
                        break;
                }
            }

            foreach ($product->getSourceItems() as $sourceCode => $sourceItem) {
                $error = $this->sourceResolver->resolveName($sourceCode);
                if ($error !== "") {
                    $product->addError($error);
                }
            }

            foreach ($product->getStoreViews() as $storeViewCode => $storeView) {

                list($id, $error) = $this->storeViewResolver->resolveName($storeViewCode);
                if ($error === "") {
                    $storeView->setStoreViewId($id);
                } else {
                    $product->addError($error);
                    $storeView->removeStoreViewId();
                }

                foreach ($storeView->getUnresolvedAttributes() as $attribute => $value) {
                    switch ($attribute) {
                        case ProductStoreView::ATTR_TAX_CLASS_ID:
                            list($id, $error) = $this->taxClassResolver->resolveName($value);
                            if ($error === "") {
                                $storeView->setTaxClassId($id);
                            } else {
                                $product->addError($error);
                                $storeView->removeAttribute(ProductStoreView::ATTR_TAX_CLASS_ID);
                            }
                            break;
                        default:
                            throw new \Exception("Unknown unresolved attribute: " . $attribute);
                    }
                }

                foreach ($storeView->getUnresolvedSelects() as $attribute => $optionName) {
                    if ($optionName === "" || $optionName === null) {
                        $storeView->setSelectAttributeOptionId($attribute, $optionName);
                    } else {
                        list ($id, $error) = $this->optionResolver->resolveOption($attribute, $optionName, $config->autoCreateOptionAttributes);
                        if ($error === "") {
                            $storeView->setSelectAttributeOptionId($attribute, $id);
                        } else {
                            $product->addError($error);
                            $storeView->removeAttribute($attribute);
                        }
                    }
                }

                foreach ($storeView->getUnresolvedMultipleSelects() as $attribute => $optionNames) {
                    if ($optionNames === null) {
                        $storeView->setMultiSelectAttributeOptionIds($attribute, null);
                    } else {
                        $nonEmptyNames = array_filter($optionNames, function ($val) {
                            return ($val !== "") && ($val !== null);
                        });
                        list ($ids, $error) = $this->optionResolver->resolveOptions($attribute, $nonEmptyNames, $config->autoCreateOptionAttributes);
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

    /**
     * @param array $products
     * @throws \Exception
     */
    public function resolveProductReferences(array $products)
    {
        // linked product references (related, up sell, cross sell
        $this->linkedProductReferenceResolver->resolveLinkedProductReferences($products);

        $productsByType = [];

        foreach ($products as $product) {
            $productsByType[$product->getType()][] = $product;
        }

        if (!empty($productsByType[BundleProduct::TYPE_BUNDLE])) {
            $this->bundleProductReferenceResolver->resolveIds($productsByType[BundleProduct::TYPE_BUNDLE]);
        }

        if (!empty($productsByType[GroupedProduct::TYPE_GROUPED])) {
            $this->groupedProductReferenceResolver->resolveIds($productsByType[GroupedProduct::TYPE_GROUPED]);
        }

        if (!empty($productsByType[ConfigurableProduct::TYPE_CONFIGURABLE])) {
            $this->configurableProductReferenceResolver->resolveIds($productsByType[ConfigurableProduct::TYPE_CONFIGURABLE]);
        }
    }
}