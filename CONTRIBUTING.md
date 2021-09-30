# Contributing

If you want to help to maintain this library, great!

Please read the text below if you do.

## Admin: processing a change

* Write integration tests for the new feature
* Check if the code runs on PHP 7.1
* Run the [integration tests](doc/integration-tests.md)
* Run the benchmark test before and after the change to see if the code has not become slower
* Add a comment about the version in CHANGELOG.md
* If relevant, add the feature to README.md
* If relevant, create an example xml file in doc/example
* Commit the code `git add . ; git commit ; git push`
* Add a tag and push the code `git tag 1.2.3 ; git push origin 1.2.3`
* Create a title and description for the release [https://github.com/bigbridge-nl/product-import/releases](here)
* Packagist is updated automatically (via [https://github.com/bigbridge-nl/product-import/settings/hooks](a webhook), but check [https://packagist.org/packages/bigbridge/product-import](if it is)

The library doesn't use branches for new features. It just adds tags. There is no version in composer.json. Bugs are only fixed in the last minor version. 

## How to - add a configuration option

The class `ImportConfig` contains all configuration options. These options impact the way the import is handled.

When a new config option is needed, it needs to be available via use of the library, via the XML API, and via the command line tool. So you need to update these classes:

* `ImportConfig`: add the option and constants for possible values (use strings, avoid using booleans)
* `ProductImportWebApi`: add the option to the config
* `ProductImportCommand`: add an InputOption

Each use of configuration option is documented, so update the documentation files:

* `doc/importer.md`: library documentation
* `doc/xml-webapi-import.md`: XML documentation
* `ProductImportCommand`: the command line option is documented in the class itself

Use the same name for the option in all places. You may use [https://github.com/bigbridge-nl/product-import/commit/184dbc45c3d4ef7f440978546eb8823743004365](this commit) as an example of a typical config option.

### Speed benchmark

The speed benchmark is important, because speed is one of the main targets of this library. The class MemoryTest is the benchmark.

Before you decide to change something, run the test MemoryTest in your own test environment. Or maybe even after the second or third run. These tests have the habit of passing only the second time. Example output:

    Factory: 0.023 seconds; 858 kB
    Inserts: 13.2 seconds; 569 kB
    Updates: 15.0 seconds; 0 kB
    Peak mem: 16 MB

Write down the results of the benchmark. After you are done making the changes, run the benchmark again. It will tell you if your change has slowed down the import. It is up to you and me to decide if the slowing down is acceptable or not.

The primary aim of MemoryTest is to test memory use. If your change makes the library use more or less memory than before, change the target numbers of the test. We can discuss whether the change in memory use is acceptable.

## About the tests

To run the integration tests, see [Integration testing](doc/integration-tests.md)

RestApiTest should be run without a PHPUnit configuration. Since it uses a nonstandard input (i.e. raw XML), it does not play well with Magento's functional testing framework.

## General

* Keep dependencies low, only add a use statement to a file after consideration of necessity

For each supported product property

* Add insert code
* Add update code
* Add a test for insert
* Add a test for update
* Update the speed test
* Add validation code
* Add a test for validation
* Add a resolver if needed
* Add a test for resolving
* If a property is not set explicitly by the user, no action should be taken. Especially: no values should be removed when the user has not set any values.
* Add element in the XML import
* Update the documentation

## Naming

* An id is an integer, most often a primary key
* A code is system string for an entity. It may be created by the user, but it cannot be changed by the user.
* A name is human-readable string for an entity. It can be created and changed by the user.

Codes are preferable for use in imports. They cannot be changed by the user, so the import is safe. Also, they work in different Magento installations, as opposed to ids.

Please keep the naming the same as Magento uses it. Do not confuse ids, codes and names, as is often done in imports.

Sometimes Magento uses different names for the same thing. A customer group for example is a "code" internally, although it is a name in the user interface.

## Database changes

Magento doesn't report database changes between two major versions (i.e. M2.3 -> M2.4), but you can use a tool called [Compalex](https://github.com/dlevsha/compalex) to find out the differences in tables and columns. You need to run it only when Magento makes a new major release. 

Magento rarely makes changes to existing database tables, but if they do, the importer must be aware of it. Use the $magentoVersion field from metadata to check the version of the active shop, and make sure to keep supporting older versions.  
