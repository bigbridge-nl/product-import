<?php

namespace BigBridge\ProductImport\Api;

/**
 * Configuration settings that affect the working of the importer.
 *
 * @author Patrick van Bergen
 */
class ImportConfig
{
    const DEFAULT_CATEGORY_PATH_SEPARATOR = '/';
    const TEMP_PRODUCT_IMAGE_PATH = BP . "/pub/media/import";

    /**
     * When set to true, no products are saved to the database
     *
     * @var bool
     */
    public $dryRun = false;

    /**
     * The number of products sent to the database at once
     * The number is a tested optimal balance between speed and database load.
     * Making the number larger will speed up import only marginally, and will create very large queries.
     *
     * @var int
     */
    public $batchSize = 1000;

    /**
     * @var callable
     *
     * This function will be called with the result of the import.
     *
     * Function signature:
     *
     * function(\BigBridge\ProductImport\Api\Data\Product);
     */
    public $resultCallback = null;

    /**
     * An array of attribute codes of select or multiple select attributes whose options should be created by the import if they did not exist.
     *
     * @var array
     */
    public $autoCreateOptionAttributes = [];

    /**
     * Create categories if they do not exist.
     *
     * true: creates categories
     * false: does not create categories, adds an error to the product
     *
     * @var bool
     */
    public $autoCreateCategories = true;

    /**
     * Categories are imported by paths of category-names, like this "Doors/Wooden Doors/Specials"
     * When your import set contains categories with a / in the name, like "Summer / Winter collection",
     * you may want to change the category name separator into something else, like "$"
     * Make sure to update the imported category paths when you do.
     *
     * @var string
     */
    public $categoryNamePathSeparator = self::DEFAULT_CATEGORY_PATH_SEPARATOR;

    const CATEGORY_URL_FLAT = 'flat';
    const CATEGORY_URL_SEGMENTED = 'segmented';

    /**
     * A category url_path of generated categories is segmented by default (i.e. 'furniture/tables/corner-chairs')
     * To create simple a url_path ('corner-chairs'), change it to 'flat'.
     *
     * @var string
     */
    public $categoryUrlType = self::CATEGORY_URL_SEGMENTED;

    /**
     * How to deal with the imported categories?
     * - add: link products to categories named in the import
     * - set: like add, and delete links too
     *
     * Important!
     * The 'set' option compares existing product-to-category links with the ones mentioned in the import.
     * Existing links that are not named in the import are removed.
     * Consider the possibility that a shop administrator manually adds products to categories that are not part of the import,
     * such as "New" or "Sale". The importer will remove these links and undo the work of a shop administrator.
     * So, use this option only if you are certain that the shop administrator does not add products to categories manually.
     *
     * @var string
     */
    public $categoryStrategy = self::CATEGORY_STRATEGY_ADD;

    const CATEGORY_STRATEGY_ADD = 'add'; // Only add and update category links
    const CATEGORY_STRATEGY_SET = 'set'; // Add and update category links; and also remove existing category links not named in the import

    /**
     * How to deal with the imported websites?
     * - add: link products to websites named in the import
     * - set: like add, and delete links too
     *
     * Important!
     * The 'set' option compares existing product-to-website links with the ones mentioned in the import.
     * Existing links that are not named in the import are removed.
     *
     * @var string
     */
    public $websiteStrategy = self::WEBSITE_STRATEGY_ADD;

    const WEBSITE_STRATEGY_ADD = 'add'; // Only add and update website links
    const WEBSITE_STRATEGY_SET = 'set'; // Add and update website links; and also remove existing website links not named in the import

    /**
     * How to handle varchar and text fields with value ""?
     *
     * @var string
     */
    public $emptyTextValueStrategy = self::EMPTY_TEXTUAL_VALUE_STRATEGY_IGNORE;

    const EMPTY_TEXTUAL_VALUE_STRATEGY_IGNORE = "ignore"; // skip it in the import
    const EMPTY_TEXTUAL_VALUE_STRATEGY_REMOVE = "remove"; // remove the attribute value from the product

    /**
     * How to handle datetime, decimal and integer fields with value ""?
     *
     * @var string
     */
    public $emptyNonTextValueStrategy = self::EMPTY_NONTEXTUAL_VALUE_STRATEGY_IGNORE;

    const EMPTY_NONTEXTUAL_VALUE_STRATEGY_IGNORE = "ignore"; // skip it in the import
    const EMPTY_NONTEXTUAL_VALUE_STRATEGY_REMOVE = "remove"; // remove the attribute value from the product

    /**
     * Create url keys based on name or sku?
     *
     * @var string
     */
    public $urlKeyScheme = self::URL_KEY_SCHEME_FROM_NAME;

    const URL_KEY_SCHEME_FROM_NAME = 'from-name';
    const URL_KEY_SCHEME_FROM_SKU = 'from-sku';

    /**
     * If a url key is generated, what should happen if that url key is already used by another product?
     *
     * - create an error
     * - add the sku to the url_key: 'white-dwarf-with-mask' becomes 'white-dwarf-with-mask-white-dwarf-11'
     * - add increasing serial number: 'white-dwarf-with-mask' becomes 'white-dwarf-with-mask-1'
     *
     * @var string
     */
    public $duplicateUrlKeyStrategy = self::DUPLICATE_KEY_STRATEGY_ERROR;

    const DUPLICATE_KEY_STRATEGY_ERROR = 'error';
    const DUPLICATE_KEY_STRATEGY_ADD_SKU = 'add-sku';
    const DUPLICATE_KEY_STRATEGY_ADD_SERIAL = 'add-serial';
    const DUPLICATE_KEY_STRATEGY_ALLOW = 'allow';

    /**
     * Base directory the source images with relative paths
     * By default: relative to the location of the
     * @var string|null
     */
    public $imageSourceDir = null;

    /**
     * Base directory where images will be cached during import.
     * @var string
     */
    public $imageCacheDir = self::TEMP_PRODUCT_IMAGE_PATH;

    /**
     * Downloading images can be slow. Choose your image strategy:
     * - force download: (default), images are downloaded over and over again
     * - check import dir: checks the directory where images are cached, pub/media/import first
     *
     * @var string
     */
    public $existingImageStrategy = self::EXISTING_IMAGE_STRATEGY_FORCE_DOWNLOAD;

    const EXISTING_IMAGE_STRATEGY_FORCE_DOWNLOAD = 'force-download';
    const EXISTING_IMAGE_STRATEGY_CHECK_IMPORT_DIR = 'check-import-dir';
    const EXISTING_IMAGE_STRATEGY_HTTP_CACHING = 'http-caching';

    /**
     * How to deal with the imported images?
     * - add: only add new images and replace existing images with the same name
     * - set: like add, but delete existing images that are not named in the import
     *
     * @var string
     */
    public $imageStrategy = self::IMAGE_STRATEGY_ADD;

    const IMAGE_STRATEGY_ADD = 'add'; // Only add and update images
    const IMAGE_STRATEGY_SET = 'set'; // Add and update images; and also remove existing product images not named in the import

    /**
     * How to handle products that change type?
     *
     * @var string
     */
    public $productTypeChange = self::PRODUCT_TYPE_CHANGE_NON_DESTRUCTIVE;

    const PRODUCT_TYPE_CHANGE_ALLOWED = 'allowed'; // allow all product type changes
    const PRODUCT_TYPE_CHANGE_FORBIDDEN = 'forbidden'; // allow no product type changes
    const PRODUCT_TYPE_CHANGE_NON_DESTRUCTIVE = 'non-destructive'; // allow only product type changes that do not delete data

    /**
     * How to handle url_rewrite 301 redirects?
     *
     * @var string
     */
    public $handleRedirects = self::KEEP_REDIRECTS;

    const KEEP_REDIRECTS = "keep"; // keep existing redirects, create new ones if the Magento settings is thus set
    const DELETE_REDIRECTS = "delete"; // remove any existing redirects, and do not create new ones

    /**
     * How to handle product url_rewrites with category paths?
     *
     * @string
     */
    public $handleCategoryRewrites = self::KEEP_CATEGORY_REWRITES;

    const KEEP_CATEGORY_REWRITES = "keep"; // keep url_rewrites with category paths, create new ones
    const DELETE_CATEGORY_REWRITES = "delete"; // remove any existing redirects, and do not create new ones

    /**
     * Support for M2EPro
     */
    public $M2EPro = self::M2EPRO_NO;

    const M2EPRO_NO = "no";
    const M2EPRO_YES = "yes";

}
