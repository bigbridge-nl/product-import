<?php

namespace BigBridge\ProductImport\Api\Data;

use BigBridge\ProductImport\Model\Data\BundleOptionInformation;

/**
 * @author Patrick van Bergen
 */
class BundleProductStoreView extends ProductStoreView
{
    const ATTR_PRICE_TYPE = 'price_type';
    const ATTR_SKU_TYPE = 'sku_type';
    const ATTR_WEIGHT_TYPE = 'weight_type';
    const ATTR_PRICE_VIEW = 'price_view';
    const ATTR_SHIPMENT_TYPE = 'shipment_type';

    const PRICE_TYPE_STATIC = 1;
    const PRICE_TYPE_DYNAMIC = 0;

    const SKU_TYPE_STATIC = 1;
    const SKU_TYPE_DYNAMIC = 0;

    const WEIGHT_TYPE_STATIC = 1;
    const WEIGHT_TYPE_DYNAMIC = 0;

    const PRICE_VIEW_PRICE_RANGE = 0;
    const PRICE_VIEW_AS_LOW_AS = 1;

    const SHIPMENT_TYPE_TOGETHER = 0;
    const SHIPMENT_TYPE_SEPARATELY = 1;

    /** @var BundleOptionInformation[] */
    protected $optionInformations = [];

    /**
     * @param int $priceType Use the PRICE_TYPE constants from this class
     */
    public function setPriceType(int $priceType)
    {
        $this->attributes[self::ATTR_PRICE_TYPE] = $priceType;
    }

    /**
     * @param int $skuType Use the SKU_TYPE constants from this class
     */
    public function setSkuType(int $skuType)
    {
        $this->attributes[self::ATTR_SKU_TYPE] = $skuType;
    }

    /**
     * @param int $weightType Use the WEIGHT_TYPE constants from this class
     */
    public function setWeightType(int $weightType)
    {
        $this->attributes[self::ATTR_WEIGHT_TYPE] = $weightType;
    }

    /**
     * @param int $priceView Use the PRICE_VIEW constants from this class
     */
    public function setPriceView(int $priceView)
    {
        $this->attributes[self::ATTR_PRICE_VIEW] = $priceView;
    }

    /**
     * @param int $shipmentType Use the SHIPMENT_TYPE constants from this class
     */
    public function setShipmentType(int $shipmentType)
    {
        $this->attributes[self::ATTR_SHIPMENT_TYPE] = $shipmentType;
    }

    public function setOptionTitle(BundleProductOption $option, string $title)
    {
        $this->optionInformations[] = new BundleOptionInformation($option, $title);
    }

    /**
     * @return BundleOptionInformation[]
     */
    public function getOptionInformations(): array
    {
        return $this->optionInformations;
    }
}