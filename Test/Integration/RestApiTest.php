<?php

namespace BigBridge\ProductImport\Test\Integration;

use SimpleXMLElement;

/**
 * @author Patrick van Bergen
 */
class RestApiTest extends \PHPUnit\Framework\TestCase
{
    // integration test with my own username / password; sorry about that :}
    const TEST_ADMIN_USER_USERNAME = "admin";
    const TEST_ADMIN_USER_PASSWORD = "admin123";

    public function testRestApi()
    {
        // include Magento
        require_once __DIR__ . '/../../../../../index.php';

        // http://mydomain/index.php/rest/V1/bigbridge/products
        $om = \Magento\Framework\App\ObjectManager::getInstance();
        $storeManager = $om->get(\Magento\Store\Model\StoreManagerInterface::class);

        // create base url for webshop
        $baseUrl = $storeManager->getStore()->getUrl();
        // remove phphar section that is added in test
        $baseUrl = preg_replace('#/phpunit[^/]+#', '', $baseUrl);

        $userData = array("username" => self::TEST_ADMIN_USER_USERNAME, "password" => self::TEST_ADMIN_USER_PASSWORD);
        $ch = curl_init($baseUrl . "rest/V1/integration/admin/token");
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($userData));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type: application/json", "Content-Length: " . strlen(json_encode($userData))));
        $token = curl_exec($ch);

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

#die($response);

        $xml = new SimpleXMLElement($response->getBody());

        $this->assertEquals("1", $xml->ok_product_count);
        $this->assertEquals("0", $xml->failed_product_count);
        $this->assertEquals("false", $xml->error_occurred);
    }
}