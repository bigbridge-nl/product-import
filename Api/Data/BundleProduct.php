<?php

namespace BigBridge\ProductImport\Api\Data;

/**
 * @author Patrick van Bergen
 */
class BundleProduct extends Product
{
    const TYPE_BUNDLE = 'bundle';

    const INPUT_TYPE_DROP_DOWN = 'select';
    const INPUT_TYPE_RADIO_BUTTONS = 'radio';
    const INPUT_TYPE_CHECKBOX = 'checkbox';
    const INPUT_TYPE_MULTIPLE_SELECT = 'multi';

    /** @var BundleProductOption[]|null */
    protected $options = null;

    public function getType()
    {
        return self::TYPE_BUNDLE;
    }

    public function getHasOptions()
    {
        return true;
    }

    public function getRequiredOptions()
    {
        return false;
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
    public function storeView(string $storeViewCode)
    {
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
    public function global()
    {
        return $this->storeViews[self::GLOBAL_STORE_VIEW_CODE];
    }

    /**
     * @return BundleProductStoreView[]|ProductStoreView[]
     */
    public function getStoreViews()
    {
        return $this->storeViews;
    }

    /**
     * @param BundleProductOption[] $options
     */
    public function setOptions(array $options)
    {
        $this->options = $options;
    }

    /**
     * @return BundleProductOption[]|null
     */
    public function getOptions()
    {
        return $this->options;
    }
}