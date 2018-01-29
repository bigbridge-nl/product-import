<?php

namespace BigBridge\ProductImport\Api\Data;

/**
 * @author Patrick van Bergen
 */
class BundleProduct extends Product
{
    const INPUT_TYPE_DROP_DOWN = 'select';
    const INPUT_TYPE_RADIO_BUTTONS = 'radio';
    const INPUT_TYPE_CHECKBOX = 'checkbox';
    const INPUT_TYPE_MULTIPLE_SELECT = 'multi';

    /** @var BundleProductOption[] */
    protected $options = [];

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

    /**
     * @param int $inputType Use the INPUT_TYPE constants of this class
     * @param bool $required
     * @return BundleProductOption
     */
    public function addOption(int $inputType, bool $required)
    {
        $option = new BundleProductOption($inputType, $required);
        $this->options[] = $option;
        return $option;
    }
}