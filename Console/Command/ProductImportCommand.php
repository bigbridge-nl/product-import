<?php

namespace BigBridge\ProductImport\Console\Command;

use BigBridge\ProductImport\Api\ImportConfig;
use BigBridge\ProductImport\Model\Reader\ProductImportCommandLogger;
use BigBridge\ProductImport\Model\Reader\XmlProductReader;
use Magento\Framework\ObjectManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Exception;

/**
 * @author Patrick van Bergen
 */
class ProductImportCommand extends Command
{
    const ARGUMENT_FILENAME = 'filename';

    const OPTION_DRY_RUN = 'dry-run';
    const OPTION_AUTO_CREATE_OPTION = 'auto-create-option';
    const OPTION_PRODUCT_TYPE_CHANGE = "product-type-change";
    const OPTION_IMAGE_CACHING = "image-caching";
    const OPTION_AUTO_CREATE_CATEGORIES = 'auto-create-categories';
    const OPTION_CATEGORY_STRATEGY = "category-strategy";
    const OPTION_WEBSITE_STRATEGY = "website-strategy";
    const OPTION_PATH_SEPARATOR = 'path-separator';
    const OPTION_CATEGORY_URL_TYPE = 'category-url-type';
    const OPTION_IMAGE_STRATEGY = "image";
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

    /** @var ObjectManagerInterface */
    protected $objectManager;

    public function __construct(
        ObjectManagerInterface $objectManager,
        ?string $name = null)
    {
        $this->objectManager = $objectManager;

        parent::__construct($name);
    }

    protected function configure()
    {
        $this->setName('bigbridge:product:import');
        $this->setDescription('Import products from file.');
        $this->setDefinition([
            new InputArgument(
                self::ARGUMENT_FILENAME,
                InputArgument::REQUIRED,
                '.xml file with products'
            ),
            new InputOption(
                self::OPTION_DRY_RUN,
                null,
                InputOption::VALUE_NONE,
                'Prepares and validates products, but does not import'
            ),
            new InputOption(
                self::OPTION_AUTO_CREATE_OPTION,
                null,
                InputOption::VALUE_IS_ARRAY | InputOption::VALUE_OPTIONAL,
                'Auto-create options for this attribute',
                []
            ),
            new InputOption(
                self::OPTION_AUTO_CREATE_CATEGORIES,
                null,
                InputOption::VALUE_NONE,
                'Auto-create categories'
            ),
            new InputOption(
                self::OPTION_PATH_SEPARATOR,
                null,
                InputOption::VALUE_OPTIONAL,
                'Category path separator',
                ImportConfig::DEFAULT_CATEGORY_PATH_SEPARATOR
            ),
            new InputOption(
                self::OPTION_CATEGORY_URL_TYPE,
                null,
                InputOption::VALUE_OPTIONAL,
                'Category url path type',
                ImportConfig::CATEGORY_URL_SEGMENTED
            ),
            new InputOption(
                self::OPTION_CATEGORY_STRATEGY,
                null,
                InputOption::VALUE_OPTIONAL,
                'category strategy: How to handle product-category links that are not in the import (set: delete these links)',
                ImportConfig::CATEGORY_STRATEGY_ADD
            ),
            new InputOption(
                self::OPTION_WEBSITE_STRATEGY,
                null,
                InputOption::VALUE_OPTIONAL,
                'website strategy: How to handle product-website links that are not in the import (set: delete these links)',
                ImportConfig::WEBSITE_STRATEGY_ADD
            ),
            new InputOption(
                self::OPTION_EMPTY_TEXT,
                null,
                InputOption::VALUE_OPTIONAL,
                'Handle empty textual values: ignore, remove',
                ImportConfig::EMPTY_TEXTUAL_VALUE_STRATEGY_IGNORE
            ),
            new InputOption(
                self::OPTION_EMPTY_NON_TEXT,
                null,
                InputOption::VALUE_OPTIONAL,
                'Handle empty non-textual values: ignore, remove',
                ImportConfig::EMPTY_NONTEXTUAL_VALUE_STRATEGY_IGNORE
            ),
            new InputOption(
                self::OPTION_URL_KEY_SOURCE,
                null,
                InputOption::VALUE_OPTIONAL,
                "Derive generated url keys from: from-name, from-sku",
                ImportConfig::URL_KEY_SCHEME_FROM_NAME
            ),
            new InputOption(
                self::OPTION_URL_KEY_STRATEGY,
                null,
                InputOption::VALUE_OPTIONAL,
                "Action for duplicate url key: error, add-sku, add-serial",
                ImportConfig::DUPLICATE_KEY_STRATEGY_ERROR
            ),
            new InputOption(
                self::OPTION_IMAGE_SOURCE_DIR,
                null,
                InputOption::VALUE_OPTIONAL,
                'Base directory for source images with relative paths'
            ),
            new InputOption(
                self::OPTION_IMAGE_CACHE_DIR,
                null,
                InputOption::VALUE_OPTIONAL,
                'Base directory where images will be cached during import',
                ImportConfig::TEMP_PRODUCT_IMAGE_PATH
            ),
            new InputOption(
                self::OPTION_IMAGE_CACHING,
                null,
                InputOption::VALUE_OPTIONAL,
                'Image caching: force-download, check-import-dir, http-caching',
                ImportConfig::EXISTING_IMAGE_STRATEGY_FORCE_DOWNLOAD
            ),
            new InputOption(
                self::OPTION_IMAGE_STRATEGY,
                null,
                InputOption::VALUE_OPTIONAL,
                'Image handling: add (add or update), set (add, update and delete)',
                ImportConfig::IMAGE_STRATEGY_ADD
            ),
            new InputOption(
                self::OPTION_PRODUCT_TYPE_CHANGE,
                null,
                InputOption::VALUE_OPTIONAL,
                'Changing product type: allowed, forbidden, non-destructive',
                ImportConfig::PRODUCT_TYPE_CHANGE_NON_DESTRUCTIVE
            ),
            new InputOption(
                self::OPTION_REDIRECTS,
                null,
                InputOption::VALUE_OPTIONAL,
                'url_rewrite: Handle 301 redirects (delete: delete all existing and new url-rewrite redirects)',
                ImportConfig::KEEP_REDIRECTS
            ),
            new InputOption(
                self::OPTION_CATEGORY_PATH_URLS,
                null,
                InputOption::VALUE_OPTIONAL,
                'url_rewrite: Handle category paths (delete: delete all existing and new category url-rewrites)',
                ImportConfig::KEEP_CATEGORY_REWRITES
            ),
            new InputOption(
                self::OPTION_M2EPRO,
                null,
                InputOption::VALUE_OPTIONAL,
                'Inform M2Pro of product changes (yes: changes are communicated to M2EPro)',
                ImportConfig::M2EPRO_NO
            ),
            new InputOption(
                self::OPTION_SKIP_XSD,
                null,
                InputOption::VALUE_NONE,
                "Skip XSD validation of the XML file"
            ),
        ]);
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /** @var XmlProductReader $xmlProductReader */
        $xmlProductReader = $this->objectManager->create(XmlProductReader::class);
        $fileName = $input->getArgument(self::ARGUMENT_FILENAME);

        $logger = new ProductImportCommandLogger($output);

        $config = new ImportConfig();
        $config->resultCallback = [$logger, 'productImported'];

        $config->dryRun = $input->getOption(self::OPTION_DRY_RUN);
        $config->autoCreateCategories = $input->getOption(self::OPTION_AUTO_CREATE_CATEGORIES);
        $config->categoryNamePathSeparator = $input->getOption(self::OPTION_PATH_SEPARATOR);
        $config->categoryUrlType = $input->getOption(self::OPTION_CATEGORY_URL_TYPE);
        $config->categoryStrategy = $input->getOption(self::OPTION_CATEGORY_STRATEGY);
        $config->websiteStrategy = $input->getOption(self::OPTION_WEBSITE_STRATEGY);
        $config->productTypeChange = $input->getOption(self::OPTION_PRODUCT_TYPE_CHANGE);
        $config->imageStrategy = $input->getOption(self::OPTION_IMAGE_STRATEGY);
        $config->existingImageStrategy = $input->getOption(self::OPTION_IMAGE_CACHING);
        $config->autoCreateOptionAttributes = $input->getOption(self::OPTION_AUTO_CREATE_OPTION);
        $config->urlKeyScheme = $input->getOption(self::OPTION_URL_KEY_SOURCE);
        $config->duplicateUrlKeyStrategy = $input->getOption(self::OPTION_URL_KEY_STRATEGY);
        $config->emptyTextValueStrategy = $input->getOption(self::OPTION_EMPTY_TEXT);
        $config->emptyNonTextValueStrategy = $input->getOption(self::OPTION_EMPTY_NON_TEXT);
        $config->handleRedirects = $input->getOption(self::OPTION_REDIRECTS);
        $config->handleCategoryRewrites = $input->getOption(self::OPTION_CATEGORY_PATH_URLS);
        $config->imageSourceDir = $this->guessImageSourceDir($fileName, $input->getOption(self::OPTION_IMAGE_SOURCE_DIR));
        $config->M2EPro = $input->getOption(self::OPTION_M2EPRO);

        $skipXsdValidation = $input->getOption(self::OPTION_SKIP_XSD);

        if (!preg_match('/.xml$/i', $fileName)) {
            throw new Exception("Input file '{$fileName}' should be an .xml file");
        }

        if (!file_exists($fileName)) {
            throw new Exception("Input file '{$fileName}' does not exist");
        }

        // import!
        $xmlProductReader->import($fileName, $config, $skipXsdValidation, $logger);

        if (!$logger->hasErrorOccurred() && $logger->getFailedProductCount() === 0) {
            return \Magento\Framework\Console\Cli::RETURN_SUCCESS;
        } else {
            return \Magento\Framework\Console\Cli::RETURN_FAILURE;
        }
    }

    protected function guessImageSourceDir(string $fileName, ?string $imageSourceDirOption = null)
    {
        // select specified dir
        $dirName = $imageSourceDirOption;

        // none specified?
        if (!$dirName) {

            // select dirname from xml file
            $dirName = dirname($fileName);
        }

        if ($dirName) {
            // prepend relative paths with the working dir
            if ($dirName[0] !== DIRECTORY_SEPARATOR) {
                $dirName = getcwd() . DIRECTORY_SEPARATOR . $dirName;
            }
        } else {
            // take the working directory
            $dirName = getcwd();
        }

        return $dirName;
    }
}
