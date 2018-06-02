<?php

namespace BigBridge\ProductImport\Model\Reader;

use Exception;
use BigBridge\ProductImport\Api\Data\Product;
use BigBridge\ProductImport\Api\Data\ProductStoreView;
use BigBridge\ProductImport\Api\Data\SimpleProduct;
use BigBridge\ProductImport\Api\Importer;

/**
 * @author Patrick van Bergen
 */
class ElementHandler
{
    /** @var Importer */
    protected $importer;

    /** @var Product */
    protected $product = null;

    /** @var ProductStoreView */
    protected $storeView = null;

    protected $tag = null;

    protected $characterData = "";

    const TYPE = 'type';
    const SKU = 'sku';
    const ATTRIBUTE_SET = "attribute_set";
    const CODE = "code";

    const PRODUCT = 'product';
    const GLOBAL = "global";
    const STORE_VIEW = "store_view";
    const NAME = "name";
    const PRICE = "price";
    const IMPORT = "import";

    public function __construct(Importer $importer)
    {
        $this->importer = $importer;
    }

    /**
     * @param $parser
     * @param $element
     * @param $attributes
     * @throws Exception
     */
    public function elementStart($parser, $element, $attributes)
    {
        $this->tag = $element;
        $this->characterData = "";

        if ($this->storeView) {

            switch ($element) {
                case self::NAME:
                case self::PRICE:
                    break;
                default:
                    $line = xml_get_current_line_number($parser);
                    throw new Exception("Unknown element '{$element}' at store view level in line {$line}");
            }

        } elseif ($this->product) {

            switch ($element) {
                case self::GLOBAL:
                    $this->storeView = $this->product->global();
                    break;
                case self::STORE_VIEW:
                    $this->storeView = $this->product->storeView($attributes[self::CODE]);
                    break;
                default:
                    $line = xml_get_current_line_number($parser);
                    throw new Exception("Unknown element '{$element}' at product level in line {$line}");
            }

        } else {

            switch ($element) {
                case self::IMPORT:
                    break;
                case self::PRODUCT:

                    switch ($attributes[self::TYPE]) {
                        case SimpleProduct::TYPE_SIMPLE:
                            $this->product = new SimpleProduct($attributes[self::SKU]);
                            $this->product->setAttributeSetByName($attributes[self::ATTRIBUTE_SET]);
                            $this->product->lineNumber = xml_get_current_line_number($parser);
                            break;
                    }

                    break;
                default:
                    $line = xml_get_current_line_number($parser);
                    throw new Exception("Unknown global element '{$element}' in line {$line}");
            }
        }
    }

    public function characterData($parser, $data)
    {
        $this->characterData .= $data;
    }

    /**
     * @param $parser
     * @param $element
     * @throws \Exception
     */
    public function elementEnd($parser, $element)
    {
        if ($this->storeView) {
            switch ($element) {
                case self::NAME:
                    $this->storeView->setName($this->characterData);
                    break;
                case self::PRICE:
                    $this->storeView->setPrice($this->characterData);
                    break;
                case self::GLOBAL:
                case self::STORE_VIEW:
                    $this->storeView = null;
                    break;
            }

        } elseif ($this->product) {
            switch ($element) {
                case self::PRODUCT:
                    $this->importer->importAnyProduct($this->product);
                    $this->product = null;
                    break;
            }

        }

        $this->characterData = "";
    }
}