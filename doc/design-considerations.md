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

## Add, update, delete

Simple databases actions are INSERT, UPDATE and DELETE. For products, things are a bit more complicated.

On complete products these types of actions are common:

* INSERT-ONLY (only allow new products)
* UPDATE-ONLY (only update existing products)
* DELETE-ONLY (delete products)
* UPSERT (both insert and update, but not delete)
* SYNC (insert, update and remove)
* REPLACE (remove and re-insert products)

UPSERT is what I currently implemented in this library. It is safe, but not complete.
Magento standard offers UPSERT, REPLACE and DELETE-ONLY. Implementing the other two is simple, but I know of no use cases, so I will wait until I do.

On the attribute level we can distinguish other actions:

For simple attributes:

* INSERT
* UPDATE
* DELETE

For complex attributes (like category-ids, images, etc)

* INSERT
* UPDATE (update based on keys, do not remove other elements)
* SYNC (insert, update and remove)
* REPLACE
* DELETE

Currently I have used several types of mutations, depending on what fitted best in each case. I have documented the choice in the readme.

INSERT is always available.
UPDATE is used for categories and images, because these tend to be modified even when a sync is in place.
SYNC is used only for tier prices.
REPLACE is used when calculating a diff is complicated and error-prone.

Eventually I would like the user of the library to be able to choose the type of mutation she wants, but this is quite a lot of work.

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

## url_path

The `url_path` property of products serves no purpose, but it does cause problems. Magento still uses it to check if the url_key has changed. Therefore it's best to remove it when updating the url key.  

## Names and ids

For imports and exports it is customary to use human readable names for attributes. "visibility" for example is exported by Magento's exporter as "Catalog, Search". The internal value is 4.
Somehow the names should be converted into id's. Quickly, easily and robustly, if possible. These types of names exist:

* constants defined in code (STATUS_ENABLED, VISIBILITY_BOTH)
* names and codes (store view code, attribute set name). These can be changed by the user.
* option values (these are translatable)

For option values, the admin value is preferred.

I chose for the option to have the developer explicitly call convertNameToId() before adding a value to a product, since the conversion is only done when needed, it is explicit, and can be easily preprocessed by the importer.

## Batch processing

I only used batch processing because it is much faster than individual queries per product. For the developer, it is less comfortable, because the importer's process() function doesn't reply with the import results immediately. The resultCallback callback is the only way the developer can get error feedback. It is not ideal, but I could think of no better method.

## Placeholders

In order to import a product that has links to other products that have not yet been imported, the library creates placeholders for these linked products. A placeholder is just a product with some required properties that will be replaced by the real product once it comes in. It is a VirtualProduct because it needs to be converted to anything else.

Distinguish these cases for non-existing linked products:

1. they are added to the same batch _before_ the product
2. they are added to the same batch _after_ the product
3. they are added to a later batch

In case of 1 the library needs to check if the linked product is in the batch already, otherwise it would overwrite the linked product. It does this by checking the sku in the $products array.

In case of 2 the library needs to replace the placeholder with the linked product, preventing the creation of the placeholder. It does this by assigning the linked product to the same sku in the $products array.

Only if 1 and 2 do not apply, a placeholder is created. It can be replaced in a later batch, case 3. The library needs to be able to recognize it as a placeholder, in case its type needs to be converted. This conversion must always be allowed. This is already the case for virtual products, but could have been forbidden by a config setting. This is checked in ProductTypeChanger.

## Memory use

I try to keep the memory footprint of the importer small and of constant size. The number of products to be imported should not be limited by the importer. All product and feedback data is released once a batch is processed.

## Maximum query size

This library creates long queries. This reduces the overhead of query transportation, interpretation and execution considerably.

Each query must not exceed the "max_allowed_packet" settings of MySQL since this is the maximum size of a query. Crossing it causes an error:

    Got a packet bigger than 'max_allowed_packet' bytes

This setting's default varies per MySQL version. It can also be changed on the server by adding this setting to the config file (i.e. /etc/mysql/my.cnf)

    [mysqld]
    max_allowed_packet=16M

The library does not try to change the set limit. It requires a minimum size of 1 MB, a very small default available since MySQL 5.5.
In any case it limits its queries to 16 MB max. No use making the max_allowed_packet larger.

The library reads the server value using "SELECT @@max_allowed_packet".
I have not found a way to inspect the client value of the variable. I read it defaults to 16 MB, so this should be fine.

The number of inserts per query ($chunkSize) is determined by

$chunkSize = $maxAllowedPacket / $magnitude;

$maxAllowedPacket is the MySQL constant (in kB).
$magnitude is the maximum size of each insert in kB (in order of magnitude, as a power of two). It must be explicitly specified per query by the library developer.
(He/she needs to specify that each multiple insert is max 1 kB, 2 kB, or 128 kB)

This allows me to use the allowed packet size efficiently while not straining the library developer much.

## Url Rewrite

The table url_rewrite (and its companion, catalog_url_rewrite_product_category) are indexes for product, category and cms page pretty urls.
It is not generated as part of the indexing process and must be built whenever products change.

Updating them is quite a task! You must think of at least the following:

url rewrite entries depend on:

* the url_key of the product
* the url_path of each of its categories (which depends on its url_keys)
* the url suffix

when you create the url_rewrites:

* create a url_rewrite for the product without any category
* create a url_rewrite for each of its categories
* create a url_rewrite for each of its categories' parent categories (except for the topmost two levels)
* each store view has its own url_rewrites, but global has none

note:

* products with a visibility of "not-visible-individually" get no url_rewrite (since they would have no use)
* existing url_rewrites are not removed when a product changes to "not-visible-individually"
* when a product or category has no url_key for a store view, it inherits its url_key from global
* the metadata field was encoded with serialize before M2.2, and with JSON since M2.2
* when a category is removed from a product, all its category-based rewrites are removed, including the 301's

When the config value "save rewrites history", is on, old rewrites are never deleted, but just transformed into 301 entries. New entries are added to the list.
Only non-301 rewrites get an entry in catalog_url_rewrite_product_category.

On conflicts:

* url_keys have no unique key constraint (not could they have, since they are stored in the _varchar table)
* url_rewrites are subject to a unique key constraint on request_path and store view id
* this constraint causes problems on updates where url_keys move from one product to another
* product request_paths shared the same space with category paths and cms paths

The table catalog_url_rewrite_product_category is rarely used by Magento, but where it is, it is joined to url_rewrite in order to efficiently put a restriction on a category,
since url_rewrite's category information is encoded in the metadata field.

Note: entries with redirect_type = 0 must be written to the database before entries with redirect_type = 301, because Magento relies on this order.

https://github.com/magento/magento2/blob/2.4-develop/app/code/Magento/Catalog/Model/Product/Url.php

A `$filterData['redirect_type'] = 0` is missing here.

This means that all rewrites must be removed and re-added, whenever a url rewrite changes.

## Nice to know

* When concatenating sets of values "(a, b, c)" "(d, e, f)" etc, implode(", ", $values) is faster than just string concatenation, even though an array of 1000 items needs to be created

* I used to distinguish between inserted products and updated products. But a quick test showed that the speed difference between inserts and inserts-as-update is very small (a few percents) and it reduces complexity quite a bit if you don't need to consider inserts anymore.

## The slowness of the unique url_key constraint

To check the uniqueness of url_keys, it is necessary to query catalog_product_entity_varchar for specific values. Magento has no key for it.

I considered many alternatives, but none of them had the simplicity of just adding an index. I hesitate to change core Magento 2 tables, of course, but here I draw the line. Magento 2 _should_ have had this index.

Magmi encountered this problem, in a more severe form, because products are handled individually.

https://sourceforge.net/p/magmi/patches/23/

## Images

All images are placed in a temporary location in the validation phase, before being processed  further. This ensures that all images are valid when being processed.
Make sure to remove all images from their temporary location later.

When an image is updated, the library must check if a database entry exists for this image and if the file still exists that belongs to this database entry. 

## Default values

I came back from the idea of setting default values (attribute_set_id, visibility, etc) for new products. They slow the importer down a bit, they make the system a little less flexible , and they provide a false sense of security. I want to make the user think at least a few minutes about these values. She will have to do this eventually anyway. And they were some attributes that I could not settle on to make them defaults (url_key, website_ids).

## Removing attribute values

Magento 2 usually sets eav attribute values to null, when an attribute is cleared in the admin panel. This is database pollution, and I will not contribute to it, if not necessary.

Note that when a product is first created, these attributes are not set to null. New custom attributes also won't have a value of null in the database. So I think it is fair to assume that Magento's queries won't break if no null value is present, but the attribute value is just missing.

## Query speed

I use queries with many inserts / updates at once, because this is faster than individual queries.

I tested this. For the mass import of eav attributes I tried prepared statements

    $sql = "INSERT INTO `{$tableName}` (`entity_id`, `attribute_id`, `store_id`, `value`)" .
            " VALUES (:a, :b, :c, :d) " .
            " ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)";

    $stm = $this->db->prepare($sql);

    $values = [];
    foreach ($storeViews as $storeView) {

        $entityId = $storeView->parent->id;
        $value = $this->db->quote($storeView->getAttribute($eavAttribute));
        $storeViewId = $storeView->getStoreViewId();

        $stm->execute([
            'a' => $entityId,
            'b' => $attributeId,
            'c' => $storeViewId,
            'd' => $value
        ]);
    }

I tried this both with and without prepared statements, it didn't matter. Both were about 4 times slower than the single query with 1000 inserts.

I checked

    $values[] = "({$entityId},{$attributeId},{$storeViewId},{$value})";
    $values[] = sprintf("(%s,%s,%s,%s)", $entityId, $attributeId, $storeViewId, $value);
    $values[] = "(" . $entityId . "," . $attributeId . "," . $storeViewId . "," . $value . ")";

they are all the same speed.

I tried concatenation in stead of array implode

    $values = "";
    $sep = "";
    foreach ($storeViews as $storeView) {

        $entityId = $storeView->parent->id;
        $value = $this->db->quote($storeView->getAttribute($eavAttribute));
        $storeViewId = $storeView->getStoreViewId();
        $values .= "{$sep}({$entityId},{$attributeId},{$storeViewId},{$value})";
        $sep = ",";
    }

    $sql = "INSERT INTO `{$tableName}` (`entity_id`, `attribute_id`, `store_id`, `value`)" .
        " VALUES " . $values .
        " ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)";

it made not difference in speed.

Quite late in the process I started using PDO binding of values (using the ?,?,?,? notation). It escapes all input, which makes the library less vulnerable to sql-injection
and there is no performance penalty (which I expected).

Collecting values like this proved fastest

    $values[] = $product->id;
    $values[] = $variant->id;

It is faster than array_push() and much faster than array_merge().

## XML import

I used [XSD/XML Schema generator](https://www.freeformatter.com/xsd-generator.html) to generate the XSD. I adapted it later to fill in the requiredness of attributes.

* generate xsd from some-products.xml, choose XSD design "Russion Doll"

You will probably wonder why I used such an old PHP XML parser (xml_parse). That's because I wanted to importer to handle very large files, and claim a fixed, small amount of memory, and I wanted it to print line numbers in the error message.

## XSD

When I use xsd validation on my computer I get this error

    '' is not a valid value of the atomic type 'xs:int'

and similar errors. This is a [known error](https://bugs.launchpad.net/lxml/+bug/1615510). I changed all integer and boolean attributes types to string for this reason. And I added an option --skip-xsd for users that have other problems with this library.

Compositors

### A D B C

multiple children, each child one 0/1 times

    <xs:all><xs:element minOccurs="0"/>

Common for an element with multiple children, all or most of which are optional, and none of which can occur more than once.

### A A A

one child 0+ times

    <xs:sequence><xs:element maxOccurs="unbounded" minOccurs="0"/>

I use the sequence only with a single child.

### B A C C B A

multiple children, each child 0+ times

    <xs:choice maxOccurs="unbounded" minOccurs="0"><xs:element minOccurs="0"/>

This is very unrestrictive. Use it sparingly.

## Webapi import

The webapi is not suited for mass import. To follow the standard use of the webapi, all products would be instantiated before the actual import began. The memory usage would be huge. Therefore I choose to bend the rules a little and have the service read from the POST body directly.

This way it efficiently imports many many products and we still have the use Magento's ACL security framework.

## Caches

An importer needs caches to run efficiently. Category metadata caches for example, greatly speed up the import process.
The drawback of caching is that their information can become outdated. Processes independent from the import can create or modify categories for example.
More importantly, the callback functions inside of an import may change categories while the import is running.

Therefore it is important that the caches of an importer are clearly marked can be reset by the user.
This product import library assumes no caches need to emptied during product import. Cache management is the responsibility of the user.
The user has access to all caches via the CacheManager class.

## Version-specific functionality

Some import functions are available only from a certain version of Magento. Multi-source inventory is available from Magento 2.3 for example. The importer tackles this with by version checking at the beginning of the preprocessing phase. This way it won't need to check at each step of the import.

