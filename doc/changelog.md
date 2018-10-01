# Changelog

## 1.2.0 : Set images; import with unknown type - 01-10-2018

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