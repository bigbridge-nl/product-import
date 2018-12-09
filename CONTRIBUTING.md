# Contributing

If you want to help to maintain this library, great!

Please read the text below if you do.

### Unit tests

Unit tests require PHPUnit 7 with namespaces.

### Integration tests

To run the integration tests, see [Integration testing](doc/integration-tests.md)

### WebApi functional tests

RestApiTest should be run without a PHPUnit configuration.

Since it uses a nonstandard input (i.e. raw XML), it does not play well with Magento's functional testing framework.

### Speed benchmark

The speed benchmark is important, because speed is one of the main targets of this library. The class MemoryTest is the benchmark.

Before you decide to change something, run the test MemoryTest in your own test environment. Or maybe even after the second or third run. These tests have the habit of passing only the second time. Example output:

    Factory: 0.023 seconds; 858 kB
    Inserts: 13.2 seconds; 569 kB
    Updates: 15.0 seconds; 0 kB
    Peak mem: 16 MB

Write down the results of the benchmark. After you are done making the changes, run the benchmark again. It will tell you if your change has slowed down the import. It is up to you and me to decide if the slowing down is acceptable or not.

The primary aim of MemoryTest is to test memory use. If your change makes the library use more or less memory than before, change the target numbers of the test. We can discuss whether the change in memory use is acceptable.

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
