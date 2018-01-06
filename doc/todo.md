# Todo

Many things, some of which are:

* Check if all relevant indexes are in Update by Schedule mode; even better, set this mode while importing. If the latter is done, inform the user that the cron must be run for the indexes to be updated.

##CSV import

* Support import of Magento export csv
* Update table import_history

## To be supported

* create functions for config
* allow (sku) updates with given ip
* bundled products
* grouped products
* downloadable products
* virtual products
* make explicit and/or optional which properties are add-only and which are rewritten (category-ids, for example)
* add a importer function runIndexers()
* csv import
* allow delete products
* url_key is a default attribute?
* by default add to all websites?
* check if all indexes have been updated correctly after reindex, or that the importer must initiate some of it
* speed test calibration tool
* rest request
* are there attributes with site-scope? how to import / update?
* create unresolved attributes for all
* check attribute value uniqueness
* check if reference ids exist
* check for tierprice duplicates and make sure the import still works
* allow change type of updated product (needed for placeholders!)
* some decimal numbers may not be negative
* import category-ids: check if the ids exist
* deleting attribute values

## Try this out

* is it faster to loop through 1000 products in 1 function faster than calling 1000 functions? use it
* check if prepared functions are really faster (and / or safer)

## Testing

- test with minimal products should be very fast (is a check that no unnecessary code is executed)
- remove created test-records
- test with 500.000 records in the database

## Todo's in code

* Solve todo's in code

## Notes

Check ho_import, for compatibility

Check other importers for features and code

* If category_ids contains an id that does not belong to a category, it is discarded without an error message
* Think about very long field values (not crossing default 1MB query size?)
* check for non-utf-8 in csv imports
* Other values than 1 and 2 can be entered for 'status'
* attribute_set_id can currently not be updated. Reason: if one would forget it as a field, it would default to something, and this could produce an unsolicited change.

## Fields

### Downloadable

product_online

### Bundle

bundle_price_type
bundle_sku_type
bundle_price_view
bundle_weight_type
bundle_values
bundle_shipment_type

### Other

display_product_options_in
map_price
map_enabled
msrp_price
msrp_display_actual_price_type
gift_message_available
custom_design
custom_design_from
custom_design_to
custom_layout_update
page_layout
product_options_container
country_of_manufacture
associated_skus

## Ever?

* product options
* allow access to low level functions to plugin that performs custom database queries
* trim: none, all except text fields
