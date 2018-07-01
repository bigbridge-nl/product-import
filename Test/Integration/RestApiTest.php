<?php

namespace BigBridge\ProductImport\Test\Integration;

use SimpleXMLElement;

/**
 * @author Patrick van Bergen
 */
class RestApiTest extends \PHPUnit\Framework\TestCase
{
    public function testRestApi()
    {
        // include Magento
        require_once __DIR__ . '/../../../../../index.php';

        // http://mydomain/index.php/rest/V1/bigbridge/products
        $om = \Magento\Framework\App\ObjectManager::getInstance();
        $storeManager = $om->get(\Magento\Store\Model\StoreManagerInterface::class);
        $url = $storeManager->getStore()->getUrl() . 'rest/V1/bigbridge/products';
        // remove phphar section
        $url = preg_replace('#/phpunit[^/]+#', '', $url);

        $token = 'token';
        $httpHeaders = new \Zend\Http\Headers();
        $httpHeaders->addHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'text/xml',
            'Content-Type' => 'text/xml'
        ]);

        $request = new \Zend\Http\Request();
        $request->setHeaders($httpHeaders);
        $request->setUri($url);
        $request->setMethod(\Zend\Http\Request::METHOD_POST);

        $content = file_get_contents(__DIR__ . '/../../doc/example/a-basic-product.xml');
        $request->setContent($content);

        $client = new \Zend\Http\Client();
        $options = [
            'adapter'   => 'Zend\Http\Client\Adapter\Curl',
            'curloptions' => [CURLOPT_FOLLOWLOCATION => true],
            'maxredirects' => 0,
            'timeout' => 30
        ];
        $client->setOptions($options);

        $response = $client->send($request);
        $xml = new SimpleXMLElement($response->getBody());

        $this->assertEquals("1", $xml->ok_product_count);
        $this->assertEquals("0", $xml->failed_product_count);
        $this->assertEquals("false", $xml->error_occurred);

        // <resource ref="Magento_Catalog::products" />
    }
}