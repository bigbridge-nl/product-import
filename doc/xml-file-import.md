# XML file import

The XML import provided allows you to import products from an XML file.

Example:

    bin/magento bigbridge:product:import vendor/bigbridge/product-import/doc/example/some-products.xml

All options are listed with --help

    bin/magento bigbridge:product:import --help

## Example XML

See doc/example for some examples of import files.

* a-basic-product.xml - contains the basic attributes needed to put a product on a frontend website
* a-bundle.xml - contains just the attributes of this type
* all-custom-options.xml - highlights custom options
* some-products.xml - my test-file with all products and features

## Remarks

Booleans should be entered as 0 or 1.

## Remove an attribute value

To delete the value of a simple attribute from the database, use the element "delete" with the name of the attribute as its value.
For example

    <delete code="special_from_date" />

This only applies to global and store_view level attributes.

## Reference implementation

The XML import also serves as an example of how to use the library. Copy it and adapt it to your needs.

