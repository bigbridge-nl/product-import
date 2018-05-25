# Todo

## Functionality

* allow delete products
* Solve todo's in code
* convert non-simple product types to other types

# Validation

* check attribute value uniqueness
* check if reference ids exist
* check for tierprice duplicates and make sure the import still works
* some decimal numbers may not be negative
* import category-ids: check if the ids exist

# Defaults

* do I want defaults? or different documentation?
* url_key is a default attribute?
* by default add to all websites?

## CSV import

* csv import
* xlsx import
* rest request

* Support import of Magento export csv
* Update table import_history
* check for non-utf-8 in csv imports

## Extra

* an url-rewrite tool

## Testing

* test custom options
* test database code, especially the change detection code
* tests may only be run in a special shop (not production)
- remove created test-records
- test with 500.000 records in the database

## Fields

Are some fields still missing from the import?

https://stackoverflow.com/questions/8585943/magento-1-6-1-what-is-options-container

display_product_options_in
custom_design
custom_design_from
custom_design_to
custom_layout_update
page_layout
product_options_container

## Ever?

* allow access to low level functions to plugin that performs custom database queries
* stop importing after x failed products
* replace products (i.e. delete and insert)
