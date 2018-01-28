<?php

namespace BigBridge\ProductImport\Api\Data;

/**
 * @author Patrick van Bergen
 */
class BundleProduct extends Product
{
    public function getType()
    {
        return 'bundle';
    }

    public function getHasOptions()
    {
        return '1';
    }

    public function getRequiredOptions()
    {
        return '1';
    }

    /**
     * @param string $sku
     */
    public function __construct(string $sku)
    {
        parent::__construct($sku);

        $this->storeViews[self::GLOBAL_STORE_VIEW_CODE] = new BundleProductStoreView();
    }

    /**
     * @param string $storeViewCode
     * @return BundleProductStoreView
     */
    public function storeView(string $storeViewCode) {
        $storeViewCode = trim($storeViewCode);
        if (!array_key_exists($storeViewCode, $this->storeViews)) {
            $storeView = new BundleProductStoreView();
            $this->storeViews[$storeViewCode] = $storeView;
        } else {
            $storeView = $this->storeViews[$storeViewCode];
        }
        return $storeView;
    }

    /**
     * @return BundleProductStoreView
     */
    public function global() {
        return $this->storeViews[self::GLOBAL_STORE_VIEW_CODE];
    }

    /**
     * @return BundleProductStoreView[]|ProductStoreView[]
     */
    public function getStoreViews()
    {
        return $this->storeViews;
    }
}