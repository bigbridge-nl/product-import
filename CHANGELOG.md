# Changelog

# 1.7.4 : Simplify access to core config data 

Pull request by Pieter Hoste, rewrites code that was optimized without need and which also lacked features. 

# 1.7.3 : Reopen a closed MySQL connection

Pull request by Pieter Hoste, reopens a MySQL connection that was closed due to inactivity.

# 1.7.2 : Use official Magento version detection when magento/magento2-base is not available

As mentioned by Pieter Hoste, magento/magento2-base may not be available; in this case the official version detection will now be used.

# 1.7.1 : More robust when handling non-existing categories.

Stronger getCategoryInfo check when fetching parent categories. Pull request by Duckↄhip.

# 1.7.0 : Support for M2EPro

An option to notify the extension M2EPro of changes to products.

# 1.6.2 : Remove cached images

In image-set-mode cached images were not removed when a new image was uploaded with the same name. They are now.

# 1.6.1 : Fix remove temporary images in force-download mode

In the (default) mode where images are re-downloaded each import, the images were left in the temporary directory (even though they were not used again). This is now no longer the case. The images are removed after the import.  

## 1.6.0 : Flat type category url paths

* An option, requested by Chris Astley, to create simple url_path attributes for generated categories (i.e 'corner-chairs' instead of the standard 'furniture/tables/corner-chairs'). This extends to the url_rewrite entry as well. 

## 1.5.4 : Fix url rewrites problem

* The attribute `url_path`, if exists, is removed from the imported products
* Writes non-redirects (redirect_type = 0) before redirects (redirect_type = 301) in url_rewrite, because Magento relies on this order when it creates canonical urls and such.

## 1.5.3 : Added missing options

Added some options that were missing from the cli command and the rest api.

## 1.5.2 : Support for Magento 2.4

Support for 20.6 price decimal format.

Fixed import of tier prices in M2.4

## 1.5.1 : Fix XSD for multiple store views

The XSD that validates the product import XML did not allow multiple store views. 

## 1.5.0 : An option to have old category links removed

Guus Portegies asked for this option to have product-to-category links to be removed as well as added.

Setting `$config->categoryStrategy = ImportConfig::CATEGORY_STRATEGY_SET` will remove product-to-category links that are not named in the import.

Use responsively; the documentation explains when this is not a good idea. 

## 1.4.6 : url suffix per website

Take into account that product and category url suffix may differ per website.

## 1.4.5 : url suffix per store view

Take into account that product and category url suffix may differ per store view.

## 1.4.4 : Support for two added columns since M2.2 

lucafuse94 noticed that parent_product_id was missing from catalog_product_bundle_option_value. 

Started monitoring database changes between Magento versions, using Compalex. Added support for 

* Tier prices, percentage_value (since M2.2)
* Bundled product option value, parent_product_id (since M2.2)

Magento 2.3 only added MSI, and did not change the existing product tables.

## 1.4.3 : Sku case sensitive

SKU's are explicitly made case sensitive
Added a function to look up the case sensitive sku in the database.

## 1.4.2 : Fix category level

choleaoum noticed that the level field of created categories was one too high.
Also some missing trim()'s were added to clean input.  

## 1.4.1 : Fix category url_rewrite

The category entries for generated categories had 0 as store_id. Changed this to the ids of actual stores views.

## 1.4.0 : Weee tax

Pull request by Jessica Garcia Santana

- Support for import of a single custom weee attribute (Waste Electrical and Electronic Equipment taxes)

## 1.3.2 : Validation for compound members / default url suffix - 01-11-2019

- Importer now invalidates compound products (configurable, bundle, group) when one of its members has errors.
- Fixed missing price in test file a-configurable.xml
- Configurable product super attribute check now allows attributes with frontend_input 'boolean'
- Product url suffix and category url suffix no longer default to '.html' when there is no value in core_config_data, in stead the value from config.xml is taken

## 1.3.1 : Speed optimization for stock - 09-12-2018

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
