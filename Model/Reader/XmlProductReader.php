<?php

namespace BigBridge\ProductImport\Model\Reader;

use BigBridge\ProductImport\Api\Data\SimpleProduct;
use BigBridge\ProductImport\Api\ImportConfig;
use BigBridge\ProductImport\Api\Importer;
use BigBridge\ProductImport\Api\ImporterFactory;
use BigBridge\ProductImport\Api\ProductImportLogger;
use DOMDocument;
use XMLReader;
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

        $reader = new XmlReader();
        $success = $reader->open($xmlPath, 'UTF-8', LIBXML_NOWARNING);

        if (!$success) {
            throw new Exception("Unable to read XML from file {$xmlPath}");
        }

        $doc = new DOMDocument();

        // move to the first product node
        while ($reader->read()) {
            if ($reader->name === self::TAG_PRODUCT) {
                break;
            }
        };

        while ($reader->name === self::TAG_PRODUCT) {
            $productNode = simplexml_import_dom($doc->importNode($reader->expand(), true));

            $this->processNode($productNode, $importer, 0);

            // go to next <product />
            $reader->next(self::TAG_PRODUCT);
        }
    }

    /**
     * @param \SimpleXMLElement $productNode
     * @param Importer $importer
     * @throws Exception
     */
    protected function processNode(\SimpleXMLElement $productNode, Importer $importer, int $lineNumber)
    {
        $type = (string)$productNode->type;
        $sku = (string)$productNode->sku;

        switch ($type) {
            case SimpleProduct::TYPE_SIMPLE:
                $product = new SimpleProduct($sku);
                $product->lineNumber = $lineNumber;
                $importer->importSimpleProduct($product);
                break;
            default:
                throw new Exception("Missing 'type'");
        }
    }
}