<?php

namespace BigBridge\ProductImport\Model\Resource\Resolver;

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
                            $config->autoCreateCategories, $config->categoryNamePathSeparator);
                        $product->setCategoryIds($ids);
                        if ($error !== "") {
                            $product->addError($error);
                            $product->setCategoryIds([]);
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
                    if ($optionName === "") {
                        continue;
                    }
                    list ($id, $error) = $this->optionResolver->resolveOption($attribute, $optionName, $config->autoCreateOptionAttributes);
                    if ($error === "") {
                        $storeView->setSelectAttributeOptionId($attribute, $id);
                    } else {
                        $product->addError($error);
                        $storeView->removeAttribute($attribute);
                    }
                }

                foreach ($storeView->getUnresolvedMultipleSelects() as $attribute => $optionNames) {
                    $nonEmptyNames = array_filter($optionNames, function($val) { return $val !== ""; });
                    if (empty($nonEmptyNames)) {
                        continue;
                    }
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