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

## Default values

New products will be given the following default values, if they are not specified:

* attribute set: "Default"
* visibility: Catalog, Search
* status: Disabled
* tax class: "Taxable Goods"

## Example Code

### Basic

The following example shows you a simple case of importing a simple product

    // load the import factory (preferably via DI)
    $factory = ObjectManager::getInstance()->get(ImporterFactory::class);

    // your own log functionality
    $log = "";

    $config = new ImportConfig();

    // the callback function to postprocess imported products
    $config->resultCallback[] = function(Product $product) use (&$log) {

        if ($product->isOk()) {
            $log .= sprintf("%s: success! sku = %s, id = %s\n", $product->lineNumber, $product->getSku(), $product->id);
        } else {
            $log .= sprintf("%s: failed! error = %s\n", $product->lineNumber, implode('; ', $product->getErrors()));
        }
    };

    $lines = [
        ['Purple Box', "purple-box", "3.95", "Lila Box", "3.85"],
        ['Yellow Box', "yellow-box", "2.95", "Gelbe Box", "2.85"]
    ];

    try {

        $importer = $factory->create($config);

        foreach ($lines as $i => $line) {

            $product = new SimpleProduct($line[1]);
            $product->lineNumber = $i + 1;

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

        $importer->flush();

    } catch (\Exception $e) {
        $log .= $e->getMessage();
    }

### Categories

Categories are imported by paths of category-names, like this "Doors/Wooden Doors/Specials". Separate category names with "/".

    $product->setCategoriesByGlobalName(['Chairs', 'Tables', 'Chairs/Chaises Longues', 'Carpets/Persian Rugs']);

When the category does not exist, it is created. The name is added to the global scope. If you don't want auto-creation, and rather just see an error, use

    $config->autoCreateCategories = false;

You can also use ids

    $product->setCategoryIds([123, 125]);

The importer does not test whether the the ids exist and will throw an database exception if they don't.

When your import set contains categories with a / in the name, like "Summer / Winter collection", you may want to change the category name separator into something else, like "$"
Make sure to update the imported category paths when you do.

    $config->categoryNamePathSeparator = "$";

### Images

To import images, use this syntax

    $image = $product1->addImage('/path/to/peanut_butter.png');

You can use a url:

    $image = $product1->addImage('http://sandwiches4you.com/path/to/peanut_butter.png');

It is also possible to use local files (these will be hard linked to their destination) and network files (these will be copied).

This will attach the image to the product and it will show up in the backend section "Images and Videos" of the product.

If you want to add one or more roles (image, small_image, thumbnail, swatch_image) to it, use this:

    $product1->global()->setImageRole($image, ProductStoreView::BASE_IMAGE);

It is also possible to use the attribute code of a custom media image attribute.

If necessary, you can even change this role per store view

    $product1->storeView('store_de')->setImageRole($image, ProductStoreView::SMALL_IMAGE);

If you want to add a label, specify the gallery position, and show/hide it on the product page, use this:

    $product1->global()->setImageGalleryInformation($image, "Large jar of peanut butter", 2, true);

Again, this can be store on the store view level:

    $product1->storeView('store_nl')->setImageGalleryInformation($image, "Grote pot pindakaas", 2, true);

## Goals

The library aims to be

* fast (a thousand products per second)
* easy to use (the api should be simple to use, and well documented, it should be easy to do common things, and uncommon things should be possible)
* robust (by default the library should take the safe side when a decision is to be made, also it should not halt on a single product failing)
* complete (if at all possible, all product import features should be present)

## Changes to Magento

The extension adds an index CATALOG_PRODUCT_ENTITY_VARCHAR_ATTRIBUTE_ID_VALUE to catalog_product_entity_varchar because it drastically speeds up checking for duplicate url_keys.

## Assumptions

* Input in UTF-8 (Magento standard)
* Database query length is at least 1 MB (this has been a MySQL default for long)

## On empty values

* A value of "" will be ignored, it is not imported. The reason is that in imports, an empty value often means unknown, or unimportant, but rarely: to be deleted.

## Thanks to

This project ows a great deal of ideas and inspiration from Magmi / Magento 1 [Magmi](https://github.com/dweeves/magmi-git)
