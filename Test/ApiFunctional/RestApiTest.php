<?php /** @noinspection ALL */

namespace BigBridge\ProductImport\Test\ApiFunctional;

use SimpleXMLElement;

/**
 * @author Patrick van Bergen
 */
class RestApiTest extends \PHPUnit\Framework\TestCase
{
    const TEST_ADMIN_USER_USERNAME = 'admin';//\Magento\TestFramework\Bootstrap::ADMIN_NAME;
    const TEST_ADMIN_USER_PASSWORD = 'admin123';//\Magento\TestFramework\Bootstrap::ADMIN_PASSWORD;

    public static function setUpBeforeClass(): void
    {
        // include Magento
        require_once __DIR__ . '/../../../../../index.php';

        \Magento\Framework\App\ObjectManager::getInstance();
    }

    public function testRestApi()
    {
        // http://mydomain/index.php/rest/V1/bigbridge/products
        $om = \Magento\Framework\App\ObjectManager::getInstance();
        $storeManager = $om->get(\Magento\Store\Model\StoreManagerInterface::class);

        // create base url for webshop
        $baseUrl = $storeManager->getStore()->getUrl();

        // remove phphar section that is added in test
        $baseUrl = preg_replace('#/(ide-)?phpunit[^/]+#', '', $baseUrl);

#var_dump($baseUrl);

        $userData = array("username" => self::TEST_ADMIN_USER_USERNAME, "password" => self::TEST_ADMIN_USER_PASSWORD);
        $ch = curl_init($baseUrl . "rest/V1/integration/admin/token");
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($userData));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type: application/json", "Content-Length: " . strlen(json_encode($userData))));
        $token = curl_exec($ch);

#echo $token;exit;
#$token = '"pic5s5uqe2mx4yfdr03bv6p977ki6vnx"';

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

#echo($response);exit;

        $xml = new SimpleXMLElement($response);

        $this->assertEquals("1", $xml->ok_product_count);
        $this->assertEquals("0", $xml->failed_product_count);
        $this->assertEquals("false", $xml->error_occurred);
        $this->assertEquals(1, preg_match('/Dry run/', $xml->output));
    }
}
