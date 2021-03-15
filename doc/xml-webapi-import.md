# XML webapi import

The XML import provided allows you to import products from a REST request with the XML passed in the body.

## Example XML

See doc/example for some examples of XML that can be posted.

* a-basic-product.xml - contains the basic attributes needed to put a product on a frontend website
* a-bundle.xml - contains just the attributes of this type
* all-custom-options.xml - highlights custom options
* some-products.xml - my test-file with all products and features

## URL and parameters

The main webapi URL is

    /rest/V1/bigbridge/products

The url can be extended with extra parameters that will be passed to the import configuration.

### Dry run

Perform a dry run only

    dry-run=1

### Attribute options

Supply attributes for automatic option creation

    auto-create-option[]=manufacturer&auto-create-option[]=color_group

### Categories

Create categories automatically:

    auto-create-categories=1

Select an alternative category path separator

    path-separator=!

The attribute url_path of a generated category contains all parent categories by default (i.e. 'furniture/tables/corner-chairs'). We'll call this "segmented". To create a simple url_path ('corner-chairs'), change this setting to 'flat':

    category-url-type=flat

By default, existing product-category links are not removed when they are not part of the import. This is done because content managers often need to place products in categories other than the ones defined in the import.  

If you want these links to be removed anyway, this can be done by:

    category-strategy=set

("Set" here means: set values as specified)

### Empty values

Handling empty element values

Remove existing textual attribute values whose values in the XML are empty

    empty-text=remove

Remove existing non-textual attribute values whose values in the XML are empty

    empty-non-text=remove

### Url keys

Base url_key on SKU

    url-key-source=from-sku

Handling duplicate url_keys: add SKU

    url-key-strategy=add-sku

add serial number

    url-key-strategy=add-serial

allow duplicates

    url-key-strategy=allow

### Images

Specify the base dir for images

    image-source-dir=http://source-of-images.net

Specify an alternative local image cache directory

    image-cache-dir=/tmp
    
Specify the type of images caching (default is force-download)

check the directory where images are cached (pub/media/import) first

    image-caching=check-import-dir
    
use HTTP caching techniques

    image-caching=http-caching

Set the image strategy to remove images that are not mentioned in the import (default: add)

    image=set

### Product types

Is product type changed allowed (default: non-destructive)?

    product-type-change=allowed
    product-type-change=forbidden

### Urls rewrites

By default, existing url rewrites are redirected to new url rewrites.

If you don't need redirects, you can skip their creation and remove existing redirects with

    redirect=delete

If you don't need urls with category paths, and remove existing redirects, use

    category-path-urls=delete

### M2EPro updates

[M2EPro](https://m2epro.com/) is an extension that synchronizes data with several sales channels.

To inform M2EPro, if installed, of all changes that were made to products, use:

    m2epro=yes

Do not enable the Track Direct Database Changes in M2EPro, if you are using this feature.

### XSD validation

Skip XSD validation

    skip-xsd=1

Remember to encode URL parameters.

## Example PHP code

The webapi call follows Magento webapi standards. Here is example PHP code that makes the import:

    $baseUrl = "https://mystore.com/";

    $userData = [
        "username" => 'some-admin-username',
        "password" => 'some-admin-password'
    );

    $ch = curl_init($baseUrl . "rest/V1/integration/admin/token");
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($userData));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type: application/json", "Content-Length: " . strlen(json_encode($userData))));
    $token = curl_exec($ch);

    $content = file_get_contents(__DIR__ . '/../../doc/example/a-basic-product.xml');

    $ch = curl_init($baseUrl . "rest/V1/bigbridge/products?dry-run=1");
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_POSTFIELDS, $content);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . json_decode($token),
        'Accept: text/xml',
        'Content-Type: text/xml',
        "Content-Length: " . strlen($content),
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);

    $xml = new SimpleXMLElement($response);

    echo $xml->ok_product_count;
    echo $xml->failed_product_count;
    echo $xml->error_occurred;
    echo $xml->output;
