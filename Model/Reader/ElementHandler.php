<?php

namespace BigBridge\ProductImport\Model\Reader;

use BigBridge\ProductImport\Api\Data\DownloadableProduct;
use BigBridge\ProductImport\Api\Data\VirtualProduct;
use Exception;
use BigBridge\ProductImport\Api\Data\Product;
use BigBridge\ProductImport\Api\Data\ProductStoreView;
use BigBridge\ProductImport\Api\Data\SimpleProduct;
use BigBridge\ProductImport\Api\Importer;
use Magento\Paypal\Model\Payflow\Pro;

/**
 * @author Patrick van Bergen
 */
class ElementHandler
{
    const TYPE = 'type';
    const SKU = 'sku';
    const ATTRIBUTE_SET_NAME = "attribute_set_name";
    const CODE = "code";

    /** @var Importer */
    protected $importer;

    /** @var Product */
    protected $product = null;

    /** @var ProductStoreView */
    protected $storeView = null;

    /** @var string|null */
    protected $tag = null;

    /** @var string  */
    protected $characterData = "";

    /** @var string[] */
    protected $items = [];

    /** @var string[] */
    protected $path = ['root'];

    public function __construct(Importer $importer)
    {
        $this->importer = $importer;
    }

    /**
     * Check if element exists in current scope.
     * Initialize data structures.
     *
     * @param $parser
     * @param $element
     * @param $attributes
     * @throws Exception
     */
    public function elementStart($parser, $element, $attributes)
    {
        $this->characterData = "";

        $scope = $this->path[count($this->path) - 1];
        $this->path[] = $element;
        $unknown = false;

        if ($scope === "root") {
            if ($element === "import") {
            } else {
                $unknown = true;
            }
        } elseif ($scope === "import") {
            if ($element === "product") {
                $this->product = $this->createProduct($parser, $attributes);
            } else {
                $unknown = true;
            }
        } elseif ($scope === "product") {
            if ($element === "global") {
                $this->storeView = $this->product->global();
            } elseif ($element === "store_view") {
                $this->storeView = $this->createStoreView($parser, $attributes, $this->product);
            } elseif ($element === "category_global_names") {
                $this->items = [];
            } elseif ($element === "category_ids") {
                $this->items = [];
            } elseif ($element === "website_codes") {
                $this->items = [];
            } elseif ($element === "website_ids") {
                $this->items = [];
            } else {
                $unknown = true;
            }
        } elseif ($scope === "global" || $scope === "store_view") {
            if (!in_array($element, [
                ProductStoreView::ATTR_NAME,
                ProductStoreView::ATTR_PRICE,
                ProductStoreView::ATTR_GIFT_MESSAGE_AVAILABLE,
            ])) {
                $unknown = true;
            }
        } elseif ($scope === "category_global_names" || $scope === "category_ids" || $scope === "website_codes" || $scope === "website_ids") {
            if ($element === "item") {
            } else {
                $unknown = true;
            }
        } else {
            $unknown = true;
        }

        if ($unknown) {
            $line = xml_get_current_line_number($parser);
            throw new Exception("Unknown element '{$element}' in line {$line}");
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
        $scope = $this->path[count($this->path) - 2];

        if ($scope === "import") {
            if ($element === "product") {
                $this->importer->importAnyProduct($this->product);
            }
        } elseif ($scope === "product") {
            if ($element === "category_global_names") {
                $this->product->addCategoriesByGlobalName($this->items);
            } elseif ($element === "category_ids") {
                $this->product->addCategoryIds($this->items);
            } elseif ($element === "website_codes") {
                $this->product->setWebsitesByCode($this->items);
            } elseif ($element === "website_ids") {
                $this->product->setWebsitesIds($this->items);
            }
        } elseif ($scope === "global" || $scope === "store_view") {
            if ($element === ProductStoreView::ATTR_NAME) {
                $this->storeView->setName($this->characterData);
            } elseif ($element === ProductStoreView::ATTR_PRICE) {
                $this->storeView->setPrice($this->characterData);
            } elseif ($element === ProductStoreView::ATTR_GIFT_MESSAGE_AVAILABLE) {
                $this->storeView->setGiftMessageAvailable($this->characterData);
            }
        } elseif ($scope === "category_global_name" || $scope === "category_ids" || $scope === "website_codes") {
            if ($element === "item") {
                $this->items[] = $this->characterData;
            }
        }

        array_pop($this->path);

        $this->characterData = "";
    }

    /**
     * @param $parser
     * @param array $attributes
     * @return Product
     * @throws Exception
     */
    protected function createProduct($parser, array $attributes)
    {
        if (!isset($attributes[self::TYPE])) {
            throw new Exception("Missing type in line " . xml_get_current_line_number($parser));
        } elseif (!isset($attributes[self::SKU])) {
            throw new Exception("Missing sku in line " . xml_get_current_line_number($parser));
        } else {

            $type = $attributes[self::TYPE];
            $sku = $attributes[self::SKU];

            if ($type === SimpleProduct::TYPE_SIMPLE) {
                $product = new SimpleProduct($sku);
            } elseif ($type === VirtualProduct::TYPE_VIRTUAL) {
                $product = new VirtualProduct($sku);
            } elseif ($type === DownloadableProduct::TYPE_DOWNLOADABLE) {
                $product = new VirtualProduct($sku);
            } else {
                throw new Exception("Unknown type: " . $attributes[self::TYPE] . " in line " . xml_get_current_line_number($parser));
            }

            $product->lineNumber = xml_get_current_line_number($parser);

            if (isset($attributes[self::ATTRIBUTE_SET_NAME])) {
                $attributeSetName = $attributes[self::ATTRIBUTE_SET_NAME];
                $product->setAttributeSetByName($attributeSetName);
            }

            return $product;
        }
    }

    /**
     * @param $parser
     * @param $attributes
     * @param Product $product
     * @return ProductStoreView
     * @throws Exception
     */
    protected function createStoreView($parser, $attributes, Product $product)
    {
        if (!isset($attributes[self::CODE])) {
            throw new Exception("Missing code in line " . xml_get_current_line_number($parser));
        } else {
            $storeView = $product->storeView($attributes[self::CODE]);
            return $storeView;
        }
    }
}