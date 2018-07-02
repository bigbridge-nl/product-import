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

The url can be extended with extra parameters that will be passed to the import configuration:

Perform a dry run only

    dry-run=1

Supply attributes for automatic option creation

    auto-create-option[]=manufacturer&auto-create-option[]=color_group

Is product type changed allowed?

    product-type-change=allowed
    product-type-change=forbidden

What image caching to use?
Check the local cache for existing images

    image-caching=check-import-dir

Use HTTP Cache

    image-caching=http-caching

Create categories automatically:

    auto-create-categories=1

Select an alternative category path separator

    path-separator=!

Specify the base dir for images

    image-source-dir=http://source-of-images.net

Specify an alternative local image cache directory

    image-cache-dir=/tmp

Base url_key on SKU

    url-key-source=from-sku

Handling duplicate url_keys: add SKU

    url-key-strategy=add-sku

add serial number

    url-key-strategy=add-serial

allow duplicates

    url-key-strategy=allow

Handling empty element values

Remove existing textual attribute values whose values in the XML are empty

    empty-text=remove

Remove existing non-textual attribute values whose values in the XML are empty

    empty-non-text=remove

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

    // fetch authentication token
    $ch = curl_init($baseUrl . "rest/V1/integration/admin/token");
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($userData));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type: application/json", "Content-Length: " . strlen(json_encode($userData))));
    $token = curl_exec($ch);

    // both input and output in XML
    $httpHeaders = new \Zend\Http\Headers();
    $httpHeaders->addHeaders([
        'Authorization' => 'Bearer ' . json_decode($token),
        'Accept' => 'text/xml',
        'Content-Type' => 'text/xml'
    ]);

    $request = new \Zend\Http\Request();
    $request->setHeaders($httpHeaders);
    $request->setUri($baseUrl . 'rest/V1/bigbridge/products');
    $request->setMethod(\Zend\Http\Request::METHOD_POST);

    // place the xml in the POST body
    $content = file_get_contents(__DIR__ . '/example/a-basic-product.xml');
    $request->setContent($content);

    $client = new \Zend\Http\Client();
    $options = [
        'adapter'   => 'Zend\Http\Client\Adapter\Curl',
        'curloptions' => [CURLOPT_FOLLOWLOCATION => true],
        'maxredirects' => 0,
        'timeout' => 30
    ];
    $client->setOptions($options);

    // the main request
    $response = $client->send($request);

    // process the response
    $xml = new SimpleXMLElement($response->getBody());

    $this->assertEquals("1", $xml->ok_product_count);
    $this->assertEquals("0", $xml->failed_product_count);
    $this->assertEquals("false", $xml->error_occurred);

