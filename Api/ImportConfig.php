<?php

namespace BigBridge\ProductImport\Api;

/**
 * @author Patrick van Bergen
 */
class ImportConfig
{
    /**
     * When set to true, no products are saved to the database
     *
     * @var bool
     */
    public $dryRun = false;

    /**
     * @var int The number of products sent to the database at once
     *      The number is a tested optimal balance between speed and database load.
     *      Making the number larger will speed up import only marginally, and will create large transactions.
     */
    public $batchSize = 1000;

    /**
     * @var callable[]
     *
     * These functions will be called with the result of the import.
     *
     * Function signature:
     *
     * function(BigBridge\ProductImport\Model\Data\Product $product, $ok, $error);
     */
    public $resultCallbacks = [];

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
     * An array of attribute codes of select or multiple select attributes whose options should be created by the import if they did not exist.
     *
     * @var array
     */
    public $autoCreateOptionAttributes = [];

    /**
     * How to handle varchar and text fields with value ""?
     *
     * @var string
     */
    public $emptyTextValueStrategy = self::EMPTY_TEXTUAL_VALUE_STRATEGY_IGNORE;

    const EMPTY_TEXTUAL_VALUE_STRATEGY_IGNORE = "ignore"; // skip it in the import
    const EMPTY_TEXTUAL_VALUE_STRATEGY_IMPORT = "import"; // import as is, as ""
    const EMPTY_TEXTUAL_VALUE_STRATEGY_REMOVE = "remove"; // remove the value from the product

    /**
     * How to handle datetime, decimal and integer fields with value ""?
     *
     * @var string
     */
    public $emptyNonTextValueStrategy = self::EMPTY_NONTEXTUAL_VALUE_STRATEGY_IGNORE;

    const EMPTY_NONTEXTUAL_VALUE_STRATEGY_IGNORE = "ignore"; // skip it in the import
    const EMPTY_NONTEXTUAL_VALUE_STRATEGY_REMOVE = "remove"; // remove the value from the product

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

    /**
     * The importer will use this version whether to use serialization or JSON for url_rewrite metadata.
     * If left null, it will be auto-detected.
     *
     * @var null
     */
    public $magentoVersion = null;

    /**
     * Categories are imported by paths of category-names, like this "Doors/Wooden Doors/Specials"
     * When your import set contains categories with a / in the name, like "Summer / Winter collection",
     * you may want to change the category name separator into something else, like "$"
     * Make sure to update the imported category paths when you do.
     *
     * @var string
     */
    public $categoryNamePathSeparator = '/';

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
}