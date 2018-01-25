<?php

namespace BigBridge\ProductImport\Model\Resource\Resolver;

use BigBridge\ProductImport\Api\Data\Product;

/**
 * @author Patrick van Bergen
 */
class TierPriceResolver
{
    /** @var WebsiteResolver */
    protected $websiteResolver;

    /** @var CustomerGroupResolver */
    protected $customerGroupResolver;

    public function __construct(
        WebsiteResolver $websiteResolver,
        CustomerGroupResolver $customerGroupResolver)
    {
        $this->websiteResolver = $websiteResolver;
        $this->customerGroupResolver = $customerGroupResolver;
    }

    /**
     * @param Product[] $products
     */
    public function resolveReferences(array $products)
    {
        foreach ($products as $product) {

            $tierPrices = $product->getTierPrices();
            if ($tierPrices === null) {
                continue;
            }

            foreach ($tierPrices as $tierPrice) {

                $websiteCode = $tierPrice->getWebsiteCode();

                if ($websiteCode === null) {
                    // 0 means all websites
                    $tierPrice->setWebsiteId(0);
                } else {
                    list($id, $error) = $this->websiteResolver->resolveCode($websiteCode);
                    if ($error === "") {
                        $tierPrice->setWebsiteId($id);
                    } else {
                        $product->addError("in tier price: " . $error);
                    }
                }

                $customerGroupName = $tierPrice->getCustomerGroupName();

                if ($customerGroupName === null) {
                    // null defaults to all customer groups
                } else {
                    list($id, $error) = $this->customerGroupResolver->resolveCustomerGroupName($customerGroupName);
                    if ($error === "") {
                        $tierPrice->setCustomerGroupId($id);
                    } else {
                        $product->addError("in tier price: " . $error);
                    }
                }

            }
        }
    }
}