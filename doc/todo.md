# Todo

## Functionality

* allow delete products
* Solve todo's in code
* convert non-simple product types to other types

# Validation

* create unresolved attributes for all
* check attribute value uniqueness
* check if reference ids exist
* check for tierprice duplicates and make sure the import still works
* some decimal numbers may not be negative
* import category-ids: check if the ids exist

# Defaults

* url_key is a default attribute?
* by default add to all websites?

## CSV import

* Support import of Magento export csv
* Update table import_history
* csv import
* xlsx import
* rest request

## Extra

* an url-rewrite tool

## Testing

* speed test calibration tool
- test with minimal products should be very fast (is a check that no unnecessary code is executed)
* tests may only be run in a special shop (not production)
- remove created test-records
- test with 500.000 records in the database

## Notes

* If category_ids contains an id that does not belong to a category, it is discarded without an error message
* Think about very long field values (not crossing default 1MB query size?)
* check for non-utf-8 in csv imports
* Other values than 1 and 2 can be entered for 'status'
* attribute_set_id can currently not be updated. Reason: if one would forget it as a field, it would default to something, and this could produce an unsolicited change.

## Fields

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
* trim: none, all except text fields
