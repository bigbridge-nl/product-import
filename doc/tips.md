# Tips and tricks

If your import is just not fast enough, you may find some pointers here to speed things up.

## Reduce the number of products

If your shop contains a large number of products that are just variants of a small number of main products, consider leaving out these variants altogether.

Even though the ERP that Magento syncs to has a separate item (article) for each product, it may not be necessary to have all of these in Magento.
This is the case when the ERP has a separate article for each permutation of features of a product (red small, red large, green small, green large, blue small, blue large).
If the number of permutations alone causes the number of products to run in the hundreds of thousands or more, you may look into this alternative.

Many times a unique product id is not necessary for the order. The important identifier is the sku. This is usually a fixed product sku, but it can also be composed by custom code.
This may seem like a lot of work, but in such a case you may have write a custom frontend component anyway because of the large number of options.
Composing the sku is then a relatively small part.

## The fastest import is no import

If you are regularly importing all products from some external source, check if it is possible to import just the ones that have changed.

* You can query some ERP packages to give you the products that have changed since a certain date, or
* You can store the hash of all data of the source product. Next sync, create a new hash. Only if the hash has changed, sync the product.

## Flush

$importer->flush() needs to be called just once, after all products are imported. Calling it after each product will greatly slow the importer down.

## Find out which part of the import is slow

Your import may look like this

    foreach ($lines as $i => $line) {

        $product = new SimpleProduct($line[1]);

        $global->setName($line[0]);
        $global->setPrice($line[2]);

        $image = $product->addImage('/path/to/peanut_butter.png');
        $product->addCategoriesByGlobalName(['Default Category/Desks', 'Default Category/Chairs', 'Default Category/Boards']);
        $global->generateUrlKey();

        $importer->importSimpleProduct($product);
    }

If you find the import to be slow, it is likely that one part of it forms the bottleneck of the process. To test which part this is, use a subset of the complete import, with 1000 products or so.

Then start by commenting out all lines

    foreach ($lines as $i => $line) {

        $product = new SimpleProduct($line[1]);

        $global->setName($line[0]);
        $global->setPrice($line[2]);

        // $image = $product->addImage('/path/to/peanut_butter.png');
        // $product->addCategoriesByGlobalName(['Default Category/Desks', 'Default Category/Chairs', 'Default Category/Boards']);
        // $global->generateUrlKey();

        $importer->importSimpleProduct($product);
    }

measure the speed of this import. Then, one by one, comment the lines in, and measure again. Once you have found the bottleneck, it is much easier to proceed.

## The overhead of reading the data

Consider the possibility that reading the data from the csv (or some other source) is the part of the import that takes up a lot of time.
Check this by not importing any data at all and measure the time it takes.
A large CSV should be read line-by-line, and not in whole up front. Same thing for XML.
Each line of import should take a fixed amount of time, and all memory should be freed.

## Images

The library contains means to cache images. Using these helps you to avoid downloading them over and over again.

Check the ImportConfig class for settings.

    $config->existingImageStrategy

## Url rewrites

By default a url rewrite is created for each category that a product is in.

    gear/bags/joust-duffie-bag.html
    outlet/joust-duffie-bag.html
    new/joust-duffie-bag.html

It takes quite some time and it may not be needed at all. If your shop has only simple product urls

    joust-duffie-bag.html

Set the following config value. The importer will not create any of the category redirects. It will also remove existing ones.

    $config->handleCategoryRewrites = "delete";

## Log slow queries

The class Magento2DbConnection has a property $echoSlowQueries that echoes slow queries when set to true. This may help you find a bottleneck.
