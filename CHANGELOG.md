# Changelog

## 1.3.1 : Speed optimization for stock

Speed optimizations:

- msi, source items: import quantity and status together

- stock items (qty, is_in_stock, ...) large speed gain by bundling queries per attribute code

## 1.3.0 : Multi-Source Inventory - 07-12-2018

Magento 2.3: Import of source items.

## 1.2.7 : FIX XSD related products / empty select values - 01-12-2018

Pull request by Jeroen Nijhuis / Epartment

- Updated product-import.xsd to use correct element names for linked product types (related, cross-sell, up-sell)
- Adjusted samples to reflect changes

Updated integration test to import all example xml files.

I made the importer's behaviour for empty values more explicit in import.md, especially for empty select and multiselect attributes.

Fixed the fatal error that occurred when a select was set to null.

## 1.2.6 : FIX impossible to import custom attributes via XML - 21-11-2018

Pull request by Antonino Bonumore / Emergento

## 1.2.4 : Lazy loading option values and cache manager - 09-11-2018

Existing option values are now only loaded per attribute, when it is needed.
Newly created option values are given the sort order that matches their position.

The cache manager allows you to refresh any of product importer's caches.

## 1.2.3 : Option value speed up - 03-11-2018

Category info and option values are no longer reloaded from the database every batch.
Newly created option values are given a fixed sort order of 10000.

## 1.2.2 : Make missing links non-fatal - 08-10-2018

Special case: if product A links to product B (for instance upsell), A and B in the same batch, and product B could not be created, the importer threw an exception up until now. I thought this could only occur in case of importer error, but apparently it also occurs when the input data is wrong. Therefore adding an error to product A suffices. Product A is still imported, but without the reference to the non-existing product B.

## 1.2.0, 1.2.1 : Set images; import with unknown type - 01-10-2018

1) By default, the importer does not delete images. Images are only added and updated.

If you want the importer to delete existing product images that are not present in the current import, use this

     $config->imageStrategy = ImportConfig::IMAGE_STRATEGY_SET;
     
This will set images as they are named in the import. However, the importer will still not remove all images if none are added to a product. This is a safety precaution.

2) If the product type is unknown, you can ask the library for the Product, by giving the sku:

    $product = $importer->getExistingProductBySku($sku);

or the id

    $product = $importer->getExistingProductById($id);    
    
The importer will return an object with the correct class, or false if no product with the id or sku could be found.    

## 1.1.2 : Remove version from composer.json

composer.json should not contain a version number 

see [getcomposer](https://getcomposer.org/doc/04-schema.md#version)

## 1.1.1 : Fix for duplicate images

Images with _[d]. (where [d] is a series of decimals) in the filename were duplicated on updates.

## 1.1.0 : Category path url-rewrites - 16-09-2018

Added the option to remove category path url-rewrites.

If a shop does not use category paths in its urls, url_rewrite generation can be simplified a lot. This saves time and reduces the size of the url_rewrite table.

Based on the ideas from [fisheyehq/module-url-rewrite-optimiser](https://github.com/fisheyehq/module-url-rewrite-optimiser)

## 1.0.0 : Public release - 10-09-2018

Publication on Github, into the public domain.