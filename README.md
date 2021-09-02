# BigBridge Product Import

Hi! My name is Patrick van Bergen. I created this library because I needed product import to be fast.

This library imports product data into Magento 2 via direct database queries. It has these main features

* A programming library to import products
* An CLI command for product import via XML file
* A web API service for product import via Post Request with XML
* A tool to update url_rewrites

## Note

The aim of this library is speed. If you find that Magento 2's product importer is too slow, consider using this library.

Experiment with the library in a safe webshop. Make sure to create a database backup before you start.

## Sample code

Let me show you a piece of code to give you an impression what it is like to use this library:

        $importer = $factory->createImporter($config);

        foreach ($lines as $i => $line) {

            $product = new SimpleProduct($line[1]);

            // global eav attributes
            $global = $product->global();
            $global->setName($line[0]);
            $global->setPrice($line[2]);

            // German eav attributes
            $german = $product->storeView('de_store');
            $german->setName($line[3]);
            $german->setPrice($line[4]);

            $importer->importSimpleProduct($product);
        }

        // process any remaining products in the pipeline
        $importer->flush();

 
Continue to read [the full documentation](doc/importer.md)

## Installation

    composer require bigbridge/product-import

## Requirements

* For Magento 2.1+ Opensource Edition
* Requires >= PHP 7.0
* Input in UTF-8 (Magento standard)
* Unix family system

## Features of the Import Library

* insert, update and delete products
* product identification based on product sku or id
* all product types (simple, configurable, grouped, bundle, downloadable, and virtual)
* automatic category generation
* automatic select and multiselect attribute option creation
* import of images from file or url
* image caching (local and HTTP cache)
* custom options
* unique url_key generation
* url_rewrite creation
* whitespace trimming (spaces, tabs, newlines) from all fields, except free field texts
* attribute deletion
* input validation (data type, requiredness,  and length restrictions)
* product type changes
* importing links to products have not been imported yet
* dry run (no products are written to the database)
* multi-source inventory (msi)
* weee taxes
* removes the url_path attribute of imported products
* M2EPro notification

Continue to read about [all importer features](doc/importer.md)

## XML file import tool

The XML import tool allows you to import product data with an XML file. It is fast and has a fixed, low memory footprint.

~~~
    bin/magento bigbridge:product:import
~~~

Continue to read about  [XML file import](doc/xml-file-import.md)

## Web API import service

The web api service performs the same service as the file import tool. But it is accessible via an XML REST call.

    /rest/V1/bigbridge/products

It is a POST call and the XML is passed in the request body.

Continue to read about [XML webapi import](doc/xml-webapi-import.md)

## The url_rewrite tool

Since Magento's url_rewrite table can get corrupted in many ways, it is necessary to have a tool to fix it.

This tool has the following features:

* Fast, it writes queries directly.
* It imposes no downtime for the webshop
* It updates the url_rewrite and catalog_url_rewrite_product_category per product
* It respects Magento's configuration setting "Create Permanent Redirect for URLs if URL Key Changed"
* It does not delete existing 301 url_rewrite redirects
* It does not overwrite existing non-product rewrites
* Updating single store views is possible
* Products with a visibility of "not-visible-individually" get no url_rewrite (since they would have no use; this is Magento 2 policy)

~~~
    bin/magento bigbridge:product:urlrewrite
~~~

Continue to read about [the Url Rewrite Tool](doc/url-rewrite-tool.md)

## Changes to Magento

The extension adds an index CATALOG_PRODUCT_ENTITY_VARCHAR_ATTRIBUTE_ID_VALUE to catalog_product_entity_varchar because it drastically speeds up checking for duplicate url_keys.

## Thanks to

Thanks to Marco de Vries for telling me about the intricacies of product import.

Thanks to Martijn van Berkel for first volunteering to use the importer in production environments and for providing valuable feedback.

This project owes a great deal of ideas and inspiration from Magmi / Magento 1 [Magmi](https://github.com/dweeves/magmi-git)
