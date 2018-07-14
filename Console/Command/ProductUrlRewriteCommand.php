<?php

namespace BigBridge\ProductImport\Console\Command;

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

    /** @var ObjectManagerInterface */
    protected $objectManager;

    public function __construct(
        ObjectManagerInterface $objectManager,
        string $name = null
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
            )
        ]);
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /** @var UrlRewriteUpdater $urlRewriteUpdater */
        $urlRewriteUpdater = $this->objectManager->create(UrlRewriteUpdater::class);

        /** @var Information $information */
        $information = $this->objectManager->create(Information::class);

        $storeViewCodes = $input->getOption(self::ARGUMENT_STOREVIEW_CODE);

        if (empty($storeViewCodes)) {
            $storeViewCodes =  $information->getNonGlobalStoreViewCodes();
        }

        $logger = new UrlRewriteUpdateCommandLogger($output);

        try {
            $urlRewriteUpdater->updateUrlRewrites($storeViewCodes, $logger);
        } catch (Exception $e) {
            $output->writeln("<error>" . $e->getMessage() . "</error>");
            return \Magento\Framework\Console\Cli::RETURN_FAILURE;
        }

        return \Magento\Framework\Console\Cli::RETURN_SUCCESS;
    }
}