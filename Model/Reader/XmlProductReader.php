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

        if ($config->dryRun) {
            $output->info("Dry run: no products are stored in the database (All auto-generated items used by these products will still be stored)");
        }

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
        $elementHandler = new ElementHandler($importer);

        if (!preg_match('/.xml$/i', $xmlPath)) {
            throw new Exception("Input file '{$xmlPath}' should be an .xml file");
        }

        if (!file_exists($xmlPath)) {
            throw new Exception("Input file '{$xmlPath}' does not exist");
        }

        // open stream
        $stream = fopen($xmlPath, 'r');
        $parser = xml_parser_create("UTF-8");

        // set options
        xml_parser_set_option($parser, XML_OPTION_TARGET_ENCODING, "UTF-8");
        xml_parser_set_option($parser, XML_OPTION_SKIP_WHITE, 0);
        xml_parser_set_option($parser, XML_OPTION_CASE_FOLDING, 0);

        // set handlers
        xml_set_element_handler($parser, [$elementHandler, 'elementStart'], [$elementHandler, 'elementEnd']);
        xml_set_character_data_handler($parser, [$elementHandler, 'characterData']);

        // read and parse chunks
        while (($data = fread($stream, 16384))) {
            xml_parse($parser, $data);
        }
        // "Entity errors are reported at the end of the data thus only if <i>is_final</i> is set and <b>TRUE</b>.
        xml_parse($parser, '', true);

        // check for parse errors
        $errorCode = xml_get_error_code($parser);
        if ($errorCode) {
            $errorString = xml_error_string($errorCode);
            throw new Exception("$errorString in line " . xml_get_current_line_number($parser));
        }

        // close
        xml_parser_free($parser);
        fclose($stream);
    }
}