# XML import

The XML import provided allows you to import products from an XML file.

Example:

    bin/magento bigbridge:product:import --dry-run --auto-create-option="color" --auto-create-option="manufacturer" vendor/bigbridge/product-import/doc/example/some-products.xml

All options are listed with --help

    bin/magento bigbridge:product:import --help

## Reference implementation

The XML import also serves as an example of how to use the library. Copy it and adapt it to your needs.

You will probably wonder why I used such an old PHP XML parser (xml_parse). That's because I wanted to importer to handle very large files, and claim a fixed, small amount of memory, and I wanted it to print line numbers in the error message.
