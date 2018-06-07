<?php

namespace BigBridge\ProductImport\Console\Command;

use BigBridge\ProductImport\Api\ImportConfig;
use BigBridge\ProductImport\Model\Reader\ProductImportCommandLogger;
use BigBridge\ProductImport\Model\Reader\XmlProductReader;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @author Patrick van Bergen
 */
class ProductImportCommand extends Command
{
    const ARGUMENT_FILENAME = 'filename';

    const OPTION_DRY_RUN = 'dry-run';
    const OPTION_AUTO_CREATE_OPTION = 'auto-create-option';

    /** @var XmlProductReader */
    protected $xmlProductReader;

    public function __construct(
        XmlProductReader $xmlProductReader,
        string $name = null)
    {
        parent::__construct($name);
        $this->xmlProductReader = $xmlProductReader;
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
                'Auto-create options for this attribute'
            )
        ]);
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null
     * @throws \Magento\Setup\Exception
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $fileName = $input->getArgument(self::ARGUMENT_FILENAME);

        $logger = new ProductImportCommandLogger($output);

        $config = new ImportConfig();
        $config->dryRun = $input->getOption(self::OPTION_DRY_RUN);
        $config->autoCreateOptionAttributes = $input->getOption(self::OPTION_AUTO_CREATE_OPTION);
        $config->resultCallbacks = [[$logger, 'productImported']];

        // import!
        $this->xmlProductReader->import($fileName, $config, $logger);

        if (!$logger->hasErrorOccurred() && $logger->getFailedProductCount() === 0) {
            return \Magento\Framework\Console\Cli::RETURN_SUCCESS;
        } else {
            return \Magento\Framework\Console\Cli::RETURN_FAILURE;
        }
    }
}