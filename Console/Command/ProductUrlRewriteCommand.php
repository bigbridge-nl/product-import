<?php

namespace BigBridge\ProductImport\Console\Command;

use BigBridge\ProductImport\Api\ImportConfig;
use Exception;
use Magento\Framework\ObjectManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use BigBridge\ProductImport\Model\Updater\UrlRewriteUpdateCommandLogger;
use BigBridge\ProductImport\Api\Information;
use BigBridge\ProductImport\Api\UrlRewriteUpdater;

/**
 * @author Patrick van Bergen
 */
class ProductUrlRewriteCommand extends Command
{
    const ARGUMENT_STOREVIEW_CODE = 'storeview';

    const OPTION_REDIRECTS = 'redirects';
    const OPTION_CATEGORY_PATH_URLS = "category-path-urls";

    /** @var ObjectManagerInterface */
    protected $objectManager;

    public function __construct(
        ObjectManagerInterface $objectManager,
        ?string $name = null
    )
    {
        $this->objectManager = $objectManager;

        parent::__construct($name);
    }

    protected function configure()
    {
        $this->setName('bigbridge:product:urlrewrite');
        $this->setDescription('Updates url_rewrite to reflect the current state of the products.');
        $this->setDefinition([
            new InputOption(
                self::ARGUMENT_STOREVIEW_CODE,
                's',
                InputOption::VALUE_IS_ARRAY | InputOption::VALUE_OPTIONAL,
                'Storeview code (default: all store views)',
                []
            ),
            new InputOption(
                self::OPTION_REDIRECTS,
                'r',
                InputOption::VALUE_OPTIONAL,
                'Handle 301 redirects (delete: delete all existing and new url-rewrite redirects)',
                ImportConfig::KEEP_REDIRECTS
            ),
            new InputOption(
                self::OPTION_CATEGORY_PATH_URLS,
                'c',
                InputOption::VALUE_OPTIONAL,
                'Handle category paths (delete: delete all existing and new category url-rewrites)',
                ImportConfig::KEEP_CATEGORY_REWRITES

            ),
        ]);
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /** @var UrlRewriteUpdater $urlRewriteUpdater */
        $urlRewriteUpdater = $this->objectManager->create(UrlRewriteUpdater::class);

        /** @var Information $information */
        $information = $this->objectManager->create(Information::class);

        $storeViewCodes = $input->getOption(self::ARGUMENT_STOREVIEW_CODE);
        $handleRedirects = $input->getOption(self::OPTION_REDIRECTS);
        $handleCategories = $input->getOption(self::OPTION_CATEGORY_PATH_URLS);

        if (empty($storeViewCodes)) {
            $storeViewCodes = $information->getNonGlobalStoreViewCodes();
        }

        if (!in_array($handleRedirects, [ImportConfig::KEEP_REDIRECTS, ImportConfig::DELETE_REDIRECTS])) {
            $output->writeln("<error>" . "Unknown redirect option: " . $handleRedirects . "</error>");
            return \Magento\Framework\Console\Cli::RETURN_FAILURE;
        }

        $logger = new UrlRewriteUpdateCommandLogger($output);

        try {
            $urlRewriteUpdater->updateUrlRewrites(
                $storeViewCodes,
                $logger,
                $handleRedirects === ImportConfig::KEEP_REDIRECTS,
                $handleCategories === ImportConfig::KEEP_CATEGORY_REWRITES);
        } catch (Exception $e) {
            $output->writeln("<error>" . $e->getMessage() . "</error>");
            return \Magento\Framework\Console\Cli::RETURN_FAILURE;
        }

        return \Magento\Framework\Console\Cli::RETURN_SUCCESS;
    }
}
