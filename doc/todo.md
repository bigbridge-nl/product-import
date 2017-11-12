# Todo

Many things, some of which are:

## url_rewrite

* write [] serialized
* Magento 2.2: json

## The product model

- Integrate global and store view Product data in single object
- Create setters for attributes. This reduces the need for validation and facilitates working with attribute subsets

## Defaults

For inserts, not updates

- weight = 1 (or: has weight?)
- status = disabled
- visibility = category, search

## To be supported

* import text entries per row
* all types of products
* categories
* product-category links (by id, by name)
* auto create category (from name)
* stock
* auto add attribute option
* csv import
* rest request
* import images using files, urls
* trim: none, all except text fields

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
product_websites
product_online
price
special_price
special_price_from_date
special_price_to_date
url_key	meta_title
meta_keywords
meta_description
base_image
base_image_label
small_image
small_image_label
thumbnail_image
thumbnail_image_label
swatch_image
swatch_image_label
created_at
updated_at
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
additional_attributes
related_skus
related_position
crosssell_skus
crosssell_position
upsell_skus
upsell_position
additional_images
additional_image_labels
hide_from_product_page
bundle_price_type
bundle_sku_type
bundle_price_view
bundle_weight_type
bundle_values
bundle_shipment_type
associated_skus
