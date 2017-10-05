<?php

namespace BigBridge\ProductImport\Test;

use BigBridge\ProductImport\Model\Importer;
use BigBridge\ProductImport\Model\SimpleProduct;

/**
 * @author Patrick van Bergen
 */
class ImportTest
{
    public function test()
    {
        $importer = new Importer();

        $product = new SimpleProduct();
        $product->setName("abc");
        $product->setSku("def");

        $importer->importProducts();
        $importer->flush();
    }
}