<?php

namespace BigBridge\ProductImport\Api;

use BigBridge\ProductImport\Model\Reader\ProductImportWebApiLogger;
use BigBridge\ProductImport\Model\Reader\XmlProductReader;
use Exception;

/**
 * @author Patrick van Bergen
 */
class ProductImportWebApi implements ProductImportWebApiInterface
{
    const OPTION_DRY_RUN = 'dry-run';
    const OPTION_AUTO_CREATE_OPTION = 'auto-create-option';
    const OPTION_PRODUCT_TYPE_CHANGE = "product-type-change";
    const OPTION_IMAGE_CACHING = "image-caching";
    const OPTION_AUTO_CREATE_CATEGORIES = 'auto-create-categories';
    const OPTION_PATH_SEPARATOR = 'path-separator';
    const OPTION_IMAGE_SOURCE_DIR = 'image-source-dir';
    const OPTION_IMAGE_CACHE_DIR = 'image-cache-dir';
    const OPTION_URL_KEY_SOURCE = "url-key-source";
    const OPTION_URL_KEY_STRATEGY = "url-key-strategy";
    const OPTION_EMPTY_TEXT = "empty-text";
    const OPTION_EMPTY_NON_TEXT = "empty-non-text";
    const OPTION_SKIP_XSD = "skip-xsd";

    /** @var ImporterFactory */
    protected $importerFactory;

    /** @var XmlProductReader */
    protected $xmlProductReader;

    public function __construct(
        ImporterFactory $importerFactory,
        XmlProductReader $xmlProductReader
    )
    {
        $this->importerFactory = $importerFactory;
        $this->xmlProductReader = $xmlProductReader;
    }

    /**
     * Imports products from XML
     *
     * @api
     * @return \BigBridge\ProductImport\Api\ProductImportWebApiLoggerInterface
     * @throws Exception
     */
    public function process()
    {
        set_time_limit(0);

        $config = $this->buildConfig($_GET);

        $importer = $this->importerFactory->createImporter($config);
        $logger = new ProductImportWebApiLogger();
        $config->resultCallback = [$logger, 'productImported'];

        $skipXsdValidation = !empty($parameters[self::OPTION_AUTO_CREATE_CATEGORIES]);

        $this->xmlProductReader->import("php://input", $config, $skipXsdValidation, $logger);

        $importer->flush();

        return $logger;
    }

    protected function buildConfig(array $parameters)
    {
        $config = new ImportConfig();

        if (isset($parameters[self::OPTION_DRY_RUN])) {
            $config->dryRun = $parameters[self::OPTION_DRY_RUN];
        }

        if (isset($parameters[self::OPTION_AUTO_CREATE_OPTION])) {
            $config->autoCreateOptionAttributes = $parameters[self::OPTION_AUTO_CREATE_OPTION];
        }

        if (isset($parameters[self::OPTION_AUTO_CREATE_CATEGORIES])) {
            $config->autoCreateCategories = $parameters[self::OPTION_AUTO_CREATE_CATEGORIES];
        }

        if (isset($parameters[self::OPTION_PATH_SEPARATOR])) {
            $config->categoryNamePathSeparator = $parameters[self::OPTION_PATH_SEPARATOR];
        }

        if (isset($parameters[self::OPTION_IMAGE_SOURCE_DIR])) {
            $config->imageSourceDir = $parameters[self::OPTION_IMAGE_SOURCE_DIR];
        }

        if (isset($parameters[self::OPTION_IMAGE_CACHE_DIR])) {
            $config->imageCacheDir = $parameters[self::OPTION_IMAGE_CACHE_DIR];
        }

        if (isset($parameters[self::OPTION_PRODUCT_TYPE_CHANGE])) {
            $config->productTypeChange = $parameters[self::OPTION_PRODUCT_TYPE_CHANGE];
        }

        if (isset($parameters[self::OPTION_IMAGE_CACHING])) {
            $config->existingImageStrategy = $parameters[self::OPTION_IMAGE_CACHING];
        }

        if (isset($parameters[self::OPTION_URL_KEY_SOURCE])) {
            $config->urlKeyScheme = $parameters[self::OPTION_URL_KEY_SOURCE];
        }

        if (isset($parameters[self::OPTION_EMPTY_TEXT])) {
            $config->emptyTextValueStrategy = $parameters[self::OPTION_EMPTY_TEXT];
        }

        if (isset($parameters[self::OPTION_EMPTY_NON_TEXT])) {
            $config->emptyNonTextValueStrategy = $parameters[self::OPTION_EMPTY_NON_TEXT];
        }

        if (isset($parameters[self::OPTION_URL_KEY_STRATEGY])) {
            $config->duplicateUrlKeyStrategy = $parameters[self::OPTION_URL_KEY_STRATEGY];
        }

        return $config;
    }
}