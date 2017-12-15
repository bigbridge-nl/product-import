# Todo

Many things, some of which are:

## To be supported

* import text entries per row
* all types of products
* stock
* auto add attribute option
* csv import
* rest request
* trim: none, all except text fields
* check attribute value uniqueness
* import category-ids: check if the ids exist

## Testing

- remove created test-records
- test with 500.000 records in the database

## Todo's in code

* Solve todo's in code

## Notes

Check ho_import, for compatibility

* If category_ids contains an id that does not belong to a category, it is discarded without an error message
* Think about very long field values (not crossing default 1MB query size?)
* People might import configurables before simples
* check for non-utf-8 in csv imports
* Is there no better way to ensure that no 2 products get the same sku?
* Other values than 1 and 2 can be entered for 'status'
* Updates: do not use default value for attribute_set_code, because it may unintentionally overwrite an existing one
* attribute_set_id can currently not be updated. Reason: if one would forget it as a field, it would default to something, and this could produce an unsolicited change.

## Fields

product_type
product_online
meta_title
meta_keywords
meta_description
new_from_date
new_to_date
display_product_options_in
map_price
msrp_price
map_enabled
gift_message_available
custom_design
custom_design_from
custom_design_to
custom_layout_update
page_layout
product_options_container
msrp_display_actual_price_type
country_of_manufacture
related_skus
related_position
crosssell_skus
crosssell_position
upsell_skus
upsell_position
hide_from_product_page
bundle_price_type
bundle_sku_type
bundle_price_view
bundle_weight_type
bundle_values
bundle_shipment_type
associated_skus
