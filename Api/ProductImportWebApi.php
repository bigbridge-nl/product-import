<?php

namespace BigBridge\ProductImport\Api;

use BigBridge\ProductImport\Model\Reader\ProductImportWebApiLogger;
use BigBridge\ProductImport\Model\Reader\XmlProductReader;
use Exception;

/**
 * @author Patrick van Bergen
 */
class ProductImportWebApi
{
    /** @var ImporterFactory */
    protected $importerFactory;

    /** @var XmlProductReader */
    protected $xmlProductReader;

    public function __construct(
        ImporterFactory $importerFactory,
        XmlProductReader $xmlProductReader
    )
    {
        $this->importerFactory = $importerFactory;
        $this->xmlProductReader = $xmlProductReader;
    }

    /**
     * Imports products from XML
     *
     * @api
     * @return \BigBridge\ProductImport\Model\Reader\ProductImportWebApiLogger
     * @throws Exception
     */
    public function process()
    {
        $config = new ImportConfig();

        $importer = $this->importerFactory->createImporter($config);
        $logger = new ProductImportWebApiLogger();
        $config->resultCallbacks = [[$logger, 'productImported']];

        $this->xmlProductReader->import("php://input", $config, false, $logger);

        $importer->flush();

        return $logger;
    }
}