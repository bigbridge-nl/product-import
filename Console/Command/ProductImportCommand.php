<?php

namespace BigBridge\ProductImport\Console\Command;

use BigBridge\ProductImport\Api\ImportConfig;
use BigBridge\ProductImport\Model\Reader\FileReaderOutput;
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
                'XML file with products'
            ),
            new InputOption(
                self::OPTION_DRY_RUN,
                null,
                InputOption::VALUE_NONE,
                'Name'
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
        $config->resultCallbacks = [[$logger, 'productImported']];

        // import!
        $this->xmlProductReader->import($fileName, $config, $logger);

        if (!$logger->hasExceptionOccurred() && $logger->getFailedProductCount() === 0) {
            return \Magento\Framework\Console\Cli::RETURN_SUCCESS;
        } else {
            return \Magento\Framework\Console\Cli::RETURN_FAILURE;
        }
    }
}