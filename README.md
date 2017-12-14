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

    // a callback function to postprocess imported products
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

The following code pieces are extensions on this basic code.

## Global scope and store view scope

Many attributes (eav attributes) can be specified both on a global level and on a store view level.

An attribute that is specified globally will be used on all store view levels, unless it is overridden by a store view value.

The library makes this distinction explicit with these constructs:

    $product->global()->setName();
    $product->storeView('store_fr')->setName();

Where storeView accepts a store view code.

In the code below I will give examples using global() and storeView(). When I do, remember that both levels are available to you at all times.

## EAV attributes

You can set any attribute by calling a setter, like this

    $product->global()->setWeight('1.21');

and set a custom attribute like this

    $product->storeView('nl')->setCustomAttribute('door_count', '3');

## Errors

The library detects problems in the input in its id-resolution and validation phrases. When it does, it adds descriptive error messages to the product this is processed.

A product that one or more errors is not imported. Errors can be inspected via a custom callback function you can provide.

    $config->resultCallback[] = function(Product $product)) {
        $errors = $product->getErrors();
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

### Websites

You can specify on which websites a product is used, by specifying their codes

    $product->setWebsitesByCode(['clothes', 'bicycles']);

or their ids

    $product->setWebsiteIds([1, 3, 4]);

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

### URL keys

The url_key of a product is used by Magento to create the url of the product page. The url_key is not added to a product automatically by the library. You must do so explicitly with

    $product->global()->setUrlKey('synthetisch-kinderdekbed-4-seizoenen');

It is common practise to generate url_keys based on the name of the product. You can do this with

    $product->storeView('sweden')->generateUrlKey();

If you want to use the "sku" field as the basis for the url_key, in stead of "name", use

    $config->urlKeyScheme = ImportConfig::URL_KEY_SCHEME_FROM_SKU;

A url_key needs to be unique within a store view or within the global level. If it is not, an error is added to the product.

The library has two ways to deal with this problem. You can tell it to add a serial number to the new url_key in case the url_key was already in use by another product.

    $config->duplicateUrlKeyStrategy = ImportConfig::DUPLICATE_KEY_STRATEGY_ADD_SERIAL;

The url of the new product with the same name as an existing product will then look like this

    https://myshop.com/synthetisch-kinderdekbed-4-seizoenen-1.html

or you can add the sku (transformed to url)

    $config->duplicateUrlKeyStrategy = ImportConfig::DUPLICATE_KEY_STRATEGY_ADD_SKU;

The url will then look something like this

    https://myshop.com/synthetisch-kinderdekbed-4-seizoenen-kdb-18004.html

## Dry run

If you want to see what errors an import produces without actually adding products to the database, set the config to "dry run"

    $config->dryRun = true;

## Changes to Magento

The extension adds an index CATALOG_PRODUCT_ENTITY_VARCHAR_ATTRIBUTE_ID_VALUE to catalog_product_entity_varchar because it drastically speeds up checking for duplicate url_keys.

## Assumptions

* Input in UTF-8 (Magento standard)
* Database query length is at least 1 MB (this has been a MySQL default for long)

## On empty values

* A value of "" will be ignored, it is not imported. The reason is that in imports, an empty value often means unknown, or unimportant, but rarely: to be deleted.

## Thanks to

This project ows a great deal of ideas and inspiration from Magmi / Magento 1 [Magmi](https://github.com/dweeves/magmi-git)
