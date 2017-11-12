# Product Import

Imports product data into Magento 2 via direct database access.

## Important

Use this library if you need speed and don't care about any plugins or custom event handlers that are normally activated when products change in Magento.

This library just helps you to get products into Magento's database quickly, low level.

After an import has completed, the product and category indexers need to be run. The library will not do this for you.

You need to perform logging yourself.

## Approach

This extension writes product data directly to the database. For more speed, it performs all inserts with 1000 records at once. Thus, it works with batches.

The developer may consider the importer as a queue, that is flushed to the database when it is full (1000 products). At the end of the import a manual flush() needs to be done, to write the remaining data to the database.

For each product user defined "result callbacks" are called. This allows you to handle the new ids, and process errors.

## Example

* Simple product
* Different store view
* categories
* by name, by id

    // load the import factory (preferably via DI)
    $factory = ObjectManager::getInstance()->get(ImporterFactory::class);

    $log = "";

    $config = new ImportConfig();
    $config->resultCallback[] = function(Product $product) use (&$log) {

        if ($product->ok) {
            $log .= sprintf("%s: success! sku = %s, id = %s\n", $product->lineNumber, $product->sku, $product->id);
        } else {
            $log .= sprintf("%s: failed! error = %s\n", $product->lineNumber, implode('; ', $product->errors));
        }

    };

    list($importer, $error) = $factory->create($config);

    $lines = [
        ['Purple Box', "purple-box", "3.95"],
        ['Yellow Box', "yellow-box", "2.95"]
    ];

    foreach ($lines as $i => $line) {

        $product = new SimpleProduct();
        $product->name = $line[0];
        $product->sku = $line[1];
        $product->price = $line[2];
        $product->lineNumber = $i + 1;

        $importer->insert($product);
    }

    $importer->flush();

## Example: url_keys

URL keys need to be imported on the store view level.

First create the product on the global level

    $product = new SimpleProduct('nervous-dinosaur', 0);
    $product->name = 'Nervous Dinosaur';
    $product->price = '9.95';

    $import->import($product);

then recreate the product on the store view level

    $product = new SimpleProduct('nervous-dinosaur', new Reference('store_de'));
        $product->url_key = new GeneratedUrlKey(UrlKey::URL_KEY_SCHEME_FROM_NAME, DUPLICATE_KEY_STRATEGY_ADD_HASHED_SKU);

    $import->import($product);

## Supported

* inserts
* updates (using sku as a key)
* input validation
* import at global and store view level
* category ids
* dry run (no writes to the database)

## Features

* trims leading and trailing whitespace (spaces, tabs, newlines) from all fields
* input is validated on data type, requiredness,  and length restrictions
* result callback, a function that is called with the results of the import (id, error)

## To be supported

* import text entries per row
* all types of products
* categories
* product-category links (by id, by name)
* auto create category (from name)
* stock
* auto add attribute option
* csv import
* rest request
* import images using files, urls
* trim: none, all except text fields

## Goals

The library aims to be

* fast (a thousand products per second)
* easy to use (the api should be simple to use, and well documented, it should be easy to do common things, and uncommon things should be possible)
* robust (by default the library should take the safe side when a decision is to be made, also it should not halt on a single product failing)
* complete (if at all possible, all product import features should be present)

## Design principle

This library follows the following principle

    it does exactly what it says on the tin

That means: it just does what you tell it to do. Nothing less, and nothing more.

You cannot expect it do enable a product by default, or to create url_rewrites. You need to specify what to create, and at what store view level.
This makes it a reliable and efficient system. Yes, you need to know what you're doing. But then, you always did when importing stuff.

## Assumptions

* Input in UTF-8 (Magento standard)
* Database query length is at least 1 MB (this has been a MySQL default for long)

## Notes

* If category_ids contains an id that does not belong to a category, it is discarded without an error message
* Think about very long field values (not crossing default 1MB query size?)
* People might import configurables before simples
* check for non-utf-8 in csv imports
* Is there no better way to ensure that no 2 products get the same sku?
* Other values than 1 and 2 can be entered for 'status'
* Updates: do not use default value for attribute_set_code, because it may unintentionally overwrite an existing one
* attribute_set_id can currently not be updated. Reason: if one would forget it as a field, it would default to something, and this could produce an unsolicited change.

## url_keys, url_paths, and url rewrites

* new category =>
    * url_key
    * url_path
    * url_rewrite

* category update
    * url_key
    * url_path
    * url_rewrite (TODO: update category url_rewrites and product url_rewrites)
    * url_rewrite 301 (moved permanently) (save_rewrites_history; TODO)

* new product
    * url_key
    * url_path (not implemented in Magento 2)
    * url_rewrite
    * catalog_url_rewrite_product_category (TODO)

* product update
    * url_key
    * url_path (not implemented in Magento 2)
    * url_rewrite (TODO update product url_rewrites)
    * catalog_url_rewrite_product_category (TODO)

## url_keys: why not just add id?

A url key must be unique. It is also commonly based on the name of the product, which is often not unique. This problem is often solved by adding the product id to
the second and further duplicate occurrences of the url_key. For example

    synthetisch-kinderdekbed-4-seizoenen-18521.html

I chose not to add the id because:

* the generated url keys cannot be moved to another database, because it will have different product ids.
* in order to check the url_key, I need the product id, so I need to create an catalog_product_entity row. If the provided or generated url_key check fails this row must be removed. This is messy.
* a dry run (import without actual database changes) is not possible, because inserts are needed to generate ids

I thought about an alternative to the id and I came up with the following:

    the first 5 characters of the md5 hash of the sku

This takes away the problems mentioned above and it modifies the desired url_key in just about the same way as an id would.

An example of a url modified with the hash:

    synthetisch-kinderdekbed-4-seizoenen-5hdl9.html

The library provides these possibilities for url_keys:

* based on name (on conflict, add a 5-character sku hash). This is the default option.
* based on name (on conflict, add the sku converted to url).
* based on name
* based on sku (useful when you have descriptive sku's like 'satin-glove-10-inches')
* based on sku (optionally adding a 5-character sku hash to prevent duplicates).
* provide your own url_key (it's up to you to make it unique, uniqueness is checked)

No solution to the duplicate key problem is perfect. There may still be duplicate key errors. These are reported and a human will have to solve them manually.

## On empty values

* A value of null will not be stored at all
* A value of "" will be stored as an empty string for varchar and text attributes, otherwise will not be stored at all

## Thanks to

This project ows a great deal of ideas and inspiration from Magmi / Magento 1 [Magmi](https://github.com/dweeves/magmi-git)

## Coding style

### Multiple return values

I use

    list($importer, $error) = $factory->create($config);

This is Go-style programming. It forces the developer to think about the error that may occur.

### Getters and setters

I do not use getters and setters in the inner loop (that is, for the code that is used for each product again) because they use a nontrivial amount of time in large amounts.

### Quote input

The reason for not quoting all input is speed.

I quote input only when necessary, that is, for input of string content. All other content is checked by the validator and cannot corrupt the database.

### Names and ids

For imports and exports it is customary to use human readable names for attributes. "visibility" for example is exported by Magento's exporter as "Catalog, Search". The internal value is 4.
Somehow the names should be converted into id's. Quickly, easily and robustly, if possible. These types of names exist:

* constants defined in code (STATUS_ENABLED, VISIBILITY_BOTH)
* names and codes (store view code, attribute set name). These can be changed by the user.
* option values (these are translatable)

For option values, the admin value is preferred.

I chose for the option to have the developer explicitly call convertNameToId() before adding a value to a product, since the conversion is only done when needed, it is explicit, and can be easily preprocessed by the importer.

### Batch processing

I only used batch processing because it is much faster than individual queries per product. For the developer, it is less comfortable, because the importer's process() function doesn't reply with the import results immediately. The resultCallbacks callback array is the only way the developer can get error feedback. It is not ideal, but I could think of no better method.

### Memory use

I try to keep the memory footprint of the importer small and of constant size. The number of products to be imported should not be limited by the importer. All product and feedback data is released once a batch is processed.

### Nice to know

* When concatenating sets of values "(a, b, c)" "(d, e, f)" etc, implode(", ", $values) is faster than just string concatenation, even though an array of 1000 items needs to be created

### The slowness of the unique url_key constraint

https://sourceforge.net/p/magmi/patches/23/

alter table catalog_product_entity_varchar add index abc (attribute_id, value);


## Fields

sku
store_view_code
attribute_set_code
product_type
categories
product_websites
name
description
short_description
weight
product_online
tax_class_name
visibility
price
special_price
special_price_from_date
special_price_to_date
url_key	meta_title
meta_keywords
meta_description
base_image
base_image_label
small_image
small_image_label
thumbnail_image
thumbnail_image_label
swatch_image
swatch_image_label
created_at
updated_at
new_from_date
new_to_date
display_product_options_in
map_price
msrp_price
map_enabled
gift_message_available
custom_design
custom_design_from
custom_design_to
custom_layout_update
page_layout
product_options_container
msrp_display_actual_price_type
country_of_manufacture
additional_attributes
related_skus
related_position
crosssell_skus
crosssell_position
upsell_skus
upsell_position
additional_images
additional_image_labels
hide_from_product_page
bundle_price_type
bundle_sku_type
bundle_price_view
bundle_weight_type
bundle_values
bundle_shipment_type
associated_skus

