# Integration tests

To set up integration tests I gratefully borrowed from [Andreas von Studnitz article](https://www.integer-net.com/integration-tests-with-magento-2/). If the instructions below don't help, check this article.

## Empty database

Create an empty database called "magento_integration_tests".

    CREATE DATABASE magento_integration_tests;

## Create install-config-mysql.php

Copy the file

    dev/tests/integration/etc/install-config-mysql.php.dist

to

    dev/tests/integration/etc/install-config-mysql.php

and change these to your own settings

* db-host
* db-user
* db-password
* db-name (to 'magento_integration_tests')

## Create phpunit.xml

Copy the file

    dev/tests/integration/phpunit.xml.dist

to

    dev/tests/integration/phpunit.xml

Change the 'testsuites' element in phpunit.xml into

    <testsuites>
        <testsuite name="BigBridge">
            <directory suffix="Test.php">../../../vendor/bigbridge/product-import/Test/Integration</directory>
        </testsuite>
    </testsuites>

## Add the index

Since this extension is not installed in the test installation, you must at least add the index, needed for the speed tests.

    alter table catalog_product_entity_varchar add index CATALOG_PRODUCT_ENTITY_VARCHAR_ATTRIBUTE_ID_VALUE (attribute_id, value);

## Run dev:tests:run

Run the integration tests from command line with this command:

    bin/magento dev:tests:run integration

## PHPStorm

To run tests from PHPStorm tell PHPUnit library to "Use composer autoloader" and

    Path to script: /path_to_root/vendor/autoload.php

and

    Default configuration file: /path_to_root/dev/tests/integration/phpunit.xml

## Tips

If you want to speed up the initialization time, you can set the variable  TESTS_CLEANUP inside the phpunit.xml to “disabled” instead of “enabled”.
