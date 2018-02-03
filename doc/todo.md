# Todo

## Several todo

* uniformization of the API
* make explicit and/or optional which properties are add-only and which are rewritten (category-ids, for example)
* all attributes in an array: collect products per attribute to save time
* url_key is a default attribute?
* are there attributes with site-scope? how to import / update?
* Solve todo's in code
* use var/import as temporary image directory, not /etc
* downloading http images: perform a HEAD request to check if the image size (or hash?) has changed (config option)
* url_rewrite: make sure the translated url_keys of the categories are used

## CSV import

* Support import of Magento export csv
* Update table import_history

## To be supported

* csv import
* xlsx import
* rest request
* allow delete products
* deleting attribute values
* by default add to all websites?
* speed test calibration tool
* create unresolved attributes for all
* check attribute value uniqueness
* check if reference ids exist
* check for tierprice duplicates and make sure the import still works
* allow change type of updated product (needed for placeholders!)
* some decimal numbers may not be negative
* import category-ids: check if the ids exist
* an url-rewrite tool

## Testing

- test with minimal products should be very fast (is a check that no unnecessary code is executed)
* tests may only be run in a special shop (not production)
- remove created test-records
- test with 500.000 records in the database

## Notes

Check ho_import, for compatibility

Check other importers for features and code

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

* product options
* allow access to low level functions to plugin that performs custom database queries
* trim: none, all except text fields
