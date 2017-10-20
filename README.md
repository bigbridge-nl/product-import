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

## Supported

* inserts
* updates (using sku as a key)
* input validation
* import at global and store view level
* category ids

## Features

* trims leading and trailing whitespace (spaces, tabs, newlines) from all fields
* input is validated on data type, requiredness,  and length restrictions
* result callback, a function that is called with the results of the import (id, error)

## To be supported

* import text entries per row
* dry run
* all types of products
* categories
* product-category links (by id, by name)
* auto create category (from name)
* stock
* store view name
* auto add attribute option
* csv import
* rest request
* error reporting of failed imports
* import images using files, urls
* trim: none, all except text fields

## Goals

* fast
* easy to use
* robust
* complete

## Assumptions

* Input in UTF-8

## Notes

* If category_ids contains an id that does not belong to a category, it is discarded without an error message
* Think about very long field values (not crossing default 1MB query size?)
* People might import configurables before simples
* check for non-utf-8 in csv imports
* Is there no better way to ensure that no 2 products get the same sku?
* Other values than 1 and 2 can be entered for 'status'
* Updates: do not use default value for attribute_set_code, because it may unintentionally overwrite an existing one
* attribute_set_id can currently not be updated. Reason: if one would forget it as a field, it would default to something, and this could produce an unsolicited change.

## On empty values

* An unassigned property will not be stored at all
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

