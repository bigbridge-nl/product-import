<?php

namespace BigBridge\ProductImport\Api;

use BigBridge\ProductImport\Model\Reader\ProductImportWebApiLogger;
use BigBridge\ProductImport\Model\Reader\XmlProductReader;
use BigBridge\ProductImport\Model\Resource\Storage\ImageStorage;
use BigBridge\ProductImport\Model\Resource\Storage\LocalPathGuard;
use Exception;
use Magento\Framework\Exception\InputException;

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
    const OPTION_CATEGORY_URL_TYPE = "category-url-type";
    const OPTION_CATEGORY_STRATEGY = "category-strategy";
    const OPTION_WEBSITE_STRATEGY = "website-strategy";
    const OPTION_PATH_SEPARATOR = 'path-separator';
    const OPTION_IMAGE_STRATEGY = 'image';
    const OPTION_IMAGE_SOURCE_DIR = 'image-source-dir';
    const OPTION_IMAGE_CACHE_DIR = 'image-cache-dir';
    const OPTION_URL_KEY_SOURCE = "url-key-source";
    const OPTION_URL_KEY_STRATEGY = "url-key-strategy";
    const OPTION_EMPTY_TEXT = "empty-text";
    const OPTION_EMPTY_NON_TEXT = "empty-non-text";
    const OPTION_SKIP_XSD = "skip-xsd";
    const OPTION_REDIRECTS = 'redirects';
    const OPTION_CATEGORY_PATH_URLS = "category-path-urls";
    const OPTION_M2EPRO = "m2epro";

    /** @var ImporterFactory */
    protected $importerFactory;

    /** @var XmlProductReader */
    protected $xmlProductReader;

    /**
     * Directories (absolute, or relative to the Magento root) that local image and download files,
     * image-source-dir and image-cache-dir must be inside. Configured in etc/di.xml.
     *
     * @var string[]
     */
    protected $localFileRoots;

    public function __construct(
        ImporterFactory $importerFactory,
        XmlProductReader $xmlProductReader,
        array $localFileRoots = []
    )
    {
        $this->importerFactory = $importerFactory;
        $this->xmlProductReader = $xmlProductReader;
        $this->localFileRoots = [];
        foreach ($localFileRoots as $root) {
            if (is_string($root) && $root !== '') {
                $this->localFileRoots[] = $root[0] === '/' ? $root : BP . '/' . $root;
            }
        }
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

        $skipXsdValidation = !empty($parameters[self::OPTION_SKIP_XSD]);

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

        if (isset($parameters[self::OPTION_CATEGORY_URL_TYPE])) {
            $config->categoryUrlType = $parameters[self::OPTION_CATEGORY_URL_TYPE];
        }

        if (isset($parameters[self::OPTION_CATEGORY_STRATEGY])) {
            $config->categoryStrategy = $parameters[self::OPTION_CATEGORY_STRATEGY];
        }

        if (isset($parameters[self::OPTION_WEBSITE_STRATEGY])) {
            $config->websiteStrategy = $parameters[self::OPTION_WEBSITE_STRATEGY];
        }

        if (isset($parameters[self::OPTION_EMPTY_TEXT])) {
            $config->emptyTextValueStrategy = $parameters[self::OPTION_EMPTY_TEXT];
        }

        if (isset($parameters[self::OPTION_EMPTY_NON_TEXT])) {
            $config->emptyNonTextValueStrategy = $parameters[self::OPTION_EMPTY_NON_TEXT];
        }

        if (isset($parameters[self::OPTION_URL_KEY_SOURCE])) {
            $config->urlKeyScheme = $parameters[self::OPTION_URL_KEY_SOURCE];
        }

        if (isset($parameters[self::OPTION_URL_KEY_STRATEGY])) {
            $config->duplicateUrlKeyStrategy = $parameters[self::OPTION_URL_KEY_STRATEGY];
        }

        $config->localFileRoots = $this->localFileRoots;

        if (isset($parameters[self::OPTION_IMAGE_SOURCE_DIR])) {
            $imageSourceDir = (string)$parameters[self::OPTION_IMAGE_SOURCE_DIR];
            if (preg_match(ImageStorage::URL_PATTERN, $imageSourceDir)) {
                $config->imageSourceDir = $imageSourceDir;
            } else {
                $config->imageSourceDir = LocalPathGuard::resolveDirectory($imageSourceDir, $this->localFileRoots, BP, false);
                if ($config->imageSourceDir === null) {
                    throw new InputException(__('%1 must be an existing directory inside one of the allowed directories', self::OPTION_IMAGE_SOURCE_DIR));
                }
            }
        }

        if (isset($parameters[self::OPTION_IMAGE_CACHE_DIR])) {
            $config->imageCacheDir = LocalPathGuard::resolveDirectory((string)$parameters[self::OPTION_IMAGE_CACHE_DIR], $this->localFileRoots, BP, true);
            if ($config->imageCacheDir === null) {
                throw new InputException(__('%1 must be inside one of the allowed directories', self::OPTION_IMAGE_CACHE_DIR));
            }
        }

        if (isset($parameters[self::OPTION_IMAGE_CACHING])) {
            $config->existingImageStrategy = $parameters[self::OPTION_IMAGE_CACHING];
        }

        if (isset($parameters[self::OPTION_IMAGE_STRATEGY])) {
            $config->imageStrategy = $parameters[self::OPTION_IMAGE_STRATEGY];
        }

        if (isset($parameters[self::OPTION_PRODUCT_TYPE_CHANGE])) {
            $config->productTypeChange = $parameters[self::OPTION_PRODUCT_TYPE_CHANGE];
        }

        if (isset($parameters[self::OPTION_REDIRECTS])) {
            $config->handleRedirects = $parameters[self::OPTION_REDIRECTS];
        }

        if (isset($parameters[self::OPTION_CATEGORY_PATH_URLS])) {
            $config->handleCategoryRewrites = $parameters[self::OPTION_CATEGORY_PATH_URLS];
        }

        if (isset($parameters[self::OPTION_M2EPRO])) {
            $config->M2EPro = $parameters[self::OPTION_M2EPRO];
        }

        return $config;
    }
}
