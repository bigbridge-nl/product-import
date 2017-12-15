# Design considerations

## Goals

The library aims to be

* fast (a thousand products per second)
* easy to use (the api should be simple to use, and well documented, it should be easy to do common things, and uncommon things should be possible)
* robust (by default the library should take the safe side when a decision is to be made, also it should not halt on a single product failing)
* complete (if at all possible, all product import features should be present)

## Approach

This extension writes product data directly to the database. For more speed, it performs all inserts with 1000 records at once. Thus, it works with batches.

The developer may consider the importer as a queue, that is flushed to the database when it is full (1000 products). At the end of the import a manual flush() needs to be done, to write the remaining data to the database.

For each product user defined "result callbacks" are called. This allows you to handle the new ids, and process errors.

## Conflicting url_keys: why not just add id?

A url key must be unique. It is also commonly based on the name of the product, which is often not unique. This problem is commonly solved by adding the product id to
the second and further duplicate occurrences of the url_key. For example

    synthetisch-kinderdekbed-4-seizoenen-18521.html

I chose not to add the id because:

* the generated url keys cannot be moved to another database, because it will have different product ids.
* in order to check the url_key, I need the product id, so I need to create an catalog_product_entity row. If the provided or generated url_key check fails this row must be removed. This is messy.
* a dry run (import without actual database changes) is not possible, because inserts are needed to generate ids

I thought about the alternatives to the id and I choose to give the user three options:

- create an error
- add a serial number (-1, -2, -3)
- add the sku (which is unique)

This takes away the problems mentioned above.

Note: although the sku is unique, the sku-turned-into-url_key is not, so it has the same problem as the name, in a lesser form.

An example of a url modified with the hash:

    synthetisch-kinderdekbed-4-seizoenen-1.html

The library provides these possibilities for url_keys:

* user-specified url_key (on conflict, create an error)
* based on name (on conflict, create an error)
* based on name (on conflict, add a serial number).
* based on name (on conflict, add the sku converted to url) (the result is not necessarily unique, and may error).
* based on sku
* based on sku (on conflict, add a serial number).

### Quote input

The reason for not quoting all input is speed. The little quoting I currently do already takes up 5% of import time. This is because it occurs in the innermost loop of the import.

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

### Maximum query size

This library creates long queries. This reduces the overhead of query transportation, interpretation and execution. We must take care that the query does not become too large. Each query must not exceed 1 MB, a standard MySQL maximum query size.

### Nice to know

* When concatenating sets of values "(a, b, c)" "(d, e, f)" etc, implode(", ", $values) is faster than just string concatenation, even though an array of 1000 items needs to be created

### The slowness of the unique url_key constraint

To check the uniqueness of url_keys, it is necessary to query catalog_product_entity_varchar for specific values. Magento has no key for it.

I considered many alternatives, but none of them had the simplicity of just adding an index. I hesitate to change core Magento 2 tables, of course, but here I draw the line. Magento 2 _should_ have had this index.

Magmi encountered this problem, in a more severe form, because products are handled individually.

https://sourceforge.net/p/magmi/patches/23/

### Images

All images are places in a temporary location in the validation phase, before being processed  further. This ensures that all images are valid when being processed.
Make sure to remove all images from their temporary location later.
