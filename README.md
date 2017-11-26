# Product Import

Imports product data into Magento 2 via direct database access.

## Important

Use this library if you need speed and don't care about any plugins or custom event handlers that are normally activated when products change in Magento.

This library just helps you to get products into Magento's database quickly, low level.

After an import has completed, the product and category indexers need to be run. The library will not do this for you.

## Features

* import of product data (new and updates, based on sku)
* automatic category generation (no updates)
* unique url_key generation
* dry run (no writes to the database)
* trims leading and trailing whitespace (spaces, tabs, newlines) from all fields
* input is validated on data type, requiredness,  and length restrictions
* result callback, a function that is called with the results of each imported product (id, error)
* information is only added and overwritten, never removed; the import is not considered to be the only source of information of the shop

## Example Code

The following example shows you a simple case of importing a simple product

    // load the import factory (preferably via DI)
    $factory = ObjectManager::getInstance()->get(ImporterFactory::class);

    // your own log functionality
    $log = "";

    $config = new ImportConfig();

    // the callback function to postprocess imported products
    $config->resultCallback[] = function(Product $product) use (&$log) {

        if ($product->errors) {
            $log .= sprintf("%s: failed! error = %s\n", $product->lineNumber, implode('; ', $product->errors));
        } else {
            $log .= sprintf("%s: success! sku = %s, id = %s\n", $product->lineNumber, $product->getSku(), $product->id);
        }
    };

    list($importer, $error) = $factory->create($config);

    $lines = [
        ['Purple Box', "purple-box", "3.95"],
        ['Yellow Box', "yellow-box", "2.95"]
    ];

    foreach ($lines as $i => $line) {

        $product = new SimpleProduct($line[1]);
        $product->lineNumber = $i + 1;

        // global eav attributes
        $global = $product->global();
        $global->name = $line[0];
        $global->price = $line[2];

        $importer->insert($product);
    }

    $importer->flush();

## Goals

The library aims to be

* fast (a thousand products per second)
* easy to use (the api should be simple to use, and well documented, it should be easy to do common things, and uncommon things should be possible)
* robust (by default the library should take the safe side when a decision is to be made, also it should not halt on a single product failing)
* complete (if at all possible, all product import features should be present)

## Assumptions

* Input in UTF-8 (Magento standard)
* Database query length is at least 1 MB (this has been a MySQL default for long)

## On empty values

* A value of "" will be ignored, it is not imported. The reason is that in imports, an empty value often means unknown, or unimportant, but rarely: to be deleted.

## Thanks to

This project ows a great deal of ideas and inspiration from Magmi / Magento 1 [Magmi](https://github.com/dweeves/magmi-git)
