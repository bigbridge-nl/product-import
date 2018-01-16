# Contribute

If you want to help to maintain this library, great!

Please read the text below if you do.

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
* If a property is not set explicitly by the user, no action should be taken. Especially: no values should be removed when the user has not set any values.
* Update the documentation

## Naming

* An id is an integer, mostoften a primary key
* A code is system string for an entity. It may be created by the user, but it cannot be changed by the user.
* A name is human-readable string for an entity. It can be created and changed by the user.

Codes are preferable for use in imports. They cannot be changed by the user, so the import is safe. Also, they work in different Magento installations, as opposed to ids.

Please keep the naming the same as Magento uses it. Do not confuse ids, codes and names, as is often done in imports.

Sometimes Magento uses different names for the same thing. A customer group for example is a "code" internally, although it is a name in the user interface.

### Speed test

The speed tests are important, because speed is one of the main targets of this library.

Before you decide to change something, calibrate the speed tests to your own test environment. Make sure that the tests "just" pass. Or maybe even after the second or third run. These tests have the habit of passing only the second time.

Only when you have calibrated the speed tests should you make changes. After you are done, check the speed test. It will tell you if your change has slowed down the import. It is up to you and me to decide if the slowing down is acceptable or not.
