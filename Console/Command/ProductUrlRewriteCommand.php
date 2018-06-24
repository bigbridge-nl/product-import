<?php

namespace BigBridge\ProductImport\Console\Command;

use BigBridge\ProductImport\Api\Information;
use BigBridge\ProductImport\Api\UrlRewriteUpdater;
use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @author Patrick van Bergen
 */
class ProductUrlRewriteCommand extends Command
{
    const ARGUMENT_STOREVIEW_CODE = 'storeview';

    /** @var UrlRewriteUpdater */
    protected $urlRewriteUpdater;

    /** @var Information */
    protected $information;

    public function __construct(
        UrlRewriteUpdater $urlRewriteUpdater,
        Information $information,
        string $name = null
    )
    {
        $this->urlRewriteUpdater = $urlRewriteUpdater;
        $this->information = $information;

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
                'storeview code',
                $this->information->getNonGlobalStoreViewCodes()
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
        $storeViewCodes = $input->getOption(self::ARGUMENT_STOREVIEW_CODE);

        try {
            $this->urlRewriteUpdater->updateUrlRewrites($storeViewCodes);
        } catch (Exception $e) {
            $output->writeln("<error>" . $e->getMessage() . "</error>");
            return \Magento\Framework\Console\Cli::RETURN_FAILURE;
        }

        return \Magento\Framework\Console\Cli::RETURN_SUCCESS;
    }
}