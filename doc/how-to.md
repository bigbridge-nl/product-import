# How-to

Procedures for common import tasks.

## Allowed errors count

The library does not have an "allowed errors count", because it imports products in bulk.
But if your aim is to stop the import after n errors have occurred and report only these n errors you can do this:

    $errorCount = 0;

    $config->resultCallback = function(Product $product) use (&$errorCount, &$log)) {

        if (!$product->isOk()) {
            $errorCount++;
        }

        if ($errorCount <= $allowedErrorsCount) {
            $log .= sprintf("%s: failed! error = %s\n", $product->lineNumber, implode('; ', $product->getErrors()));
        }
    };

    while ($moreProducts) {

        if ($errorCount > $allowedErrorsCount) {
            break;
        }

        $importer->importSimpleProduct($product);
    }

This is useful if you have many errors to fix.

## Multi-line CSV import

In a CSV import each product's store view information may be stored on a separate line.

    sku     store       price       description
    rrh1    admin       19.95       Little Red Riding Hood
    rrh1    de_store                Rotkäppchen
    rrh1    nl_store                Roodkapje

To import these products, you can use this scheme:

    $previousSku = null;

    while ($moreProducts) {

        $sku = $row['sku'];

        // start a new product when the sku changes
        if ($sku !== $previousSku) {
            $product = new SimpleProduct($sku);
        }

        $importer->importSimpleProduct($product);

        $previousSku = $sku;
    }
