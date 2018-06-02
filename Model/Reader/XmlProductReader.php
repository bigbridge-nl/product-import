<?php

namespace BigBridge\ProductImport\Model\Reader;

use BigBridge\ProductImport\Api\ImportConfig;
use BigBridge\ProductImport\Api\Importer;
use BigBridge\ProductImport\Api\ImporterFactory;
use BigBridge\ProductImport\Api\ProductImportLogger;
use Exception;

/**
 * @author Patrick van Bergen
 */
class XmlProductReader
{
    const TAG_PRODUCT = 'product';

    /** @var ImporterFactory */
    protected $importerFactory;

    public function __construct(
        ImporterFactory $importerFactory
    )
    {
        $this->importerFactory = $importerFactory;
    }

    /**
     * @param string $xmlPath
     * @param ImportConfig $config
     * @param ProductImportLogger $output
     * @throws Exception
     */
    public function import(string $xmlPath, ImportConfig $config, ProductImportLogger $output)
    {
        $time = date('H:i:s');
        $output->info("{$time} Import start");

        try {

            $importer = $this->importerFactory->createImporter($config);

            $this->processFile($xmlPath, $importer);

            $importer->flush();

        } catch (\Exception $e) {
            $output->handleException($e);
        }

        $time = date('H:i:s');
        $output->info("{$time} Import end");
        $output->info("{$output->getOkProductCount()} products imported");
        $output->info("{$output->getFailedProductCount()} products failed");
    }

    /**
     * @param string $xmlPath
     * @param Importer $importer
     * @throws Exception
     */
    protected function processFile(string $xmlPath, Importer $importer)
    {
        if (!preg_match('/.xml$/i', $xmlPath)) {
            throw new Exception("Input file '{$xmlPath}' should be an .xml file");
        }

        if (!file_exists($xmlPath)) {
            throw new Exception("Input file '{$xmlPath}' does not exist");
        }

        $stream = fopen($xmlPath, 'r');
        $parser = xml_parser_create();

        $elementHandler = new ElementHandler($importer);

        xml_set_element_handler($parser, [$elementHandler, 'elementStart'], [$elementHandler, 'elementEnd']);
        xml_set_character_data_handler($parser, [$elementHandler, 'characterData']);

        while (($data = fread($stream, 16384))) {
            xml_parse($parser, $data);
        }
        xml_parse($parser, '', true);

        $errorCode = xml_get_error_code($parser);
        if ($errorCode) {
            $errorString = xml_error_string($errorCode);
            throw new Exception("$errorString in line " . xml_get_current_line_number($parser));
        }

        xml_parser_free($parser);
        fclose($stream);
    }
}