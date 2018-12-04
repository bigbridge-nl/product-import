# Database

Tables that will be changed by the importer (insert, update, delete)

## Magento 2 database tables

### Main product and EAV attributes

* catalog_category_product
* catalog_product_entity and catalog_product_entity_*

### Url rewrite

* url_rewrite
* catalog_url_rewrite_product_category

### Images

* catalog_product_entity_media_gallery
* catalog_product_entity_media_gallery_value_to_entity
* catalog_product_entity_media_gallery_value

### Attribute options

* eav_attribute_option
* eav_attribute_option_value

### Stock items

* cataloginventory_stock_item

### Tier prices

* catalog_product_entity_tier_price

### Configurable products

* catalog_product_super_attribute_label
* catalog_product_super_link

### Related / Up-sell / Cross-sell links, Grouped product links

* catalog_product_relation
* catalog_product_link
* catalog_product_link_attribute
* catalog_product_link_attribute_int
* catalog_product_link_attribute_decimal
* catalog_product_link_type

### Downloadable products

* downloadable_link
* downloadable_link_title
* downloadable_link_price
* downloadable_sample
* downloadable_sample_title

### Bundle products

* catalog_product_bundle_option
* catalog_product_bundle_option_value
* catalog_product_bundle_selection

### Category

(insert only)

* catalog_category_entity
* catalog_category_entity_*

### Category links

* catalog_category_product

### Website links

* catalog_product_website

### Custom options

* catalog_product_option
* catalog_product_option_price
* catalog_product_option_title
* catalog_product_option_type_price
* catalog_product_option_type_title
* catalog_product_option_type_value

### Multi-Source Inventory

* inventory_source_item
* inventory_low_stock_notification_configuration

## Remarks

* The fields 'deferred_stock_update' and 'use_config_deferred_stock_update' that are available in the ui of 'Advanced Inventory' are not stored and not used by Magento [https://community.magento.com/t5/Admin-Configuration-Questions/Use-Deferred-Stock-Update/td-p/67547]
* The eav attribute 'quantity_and_stock_status' is unofficially deprecated and not used [https://magento.stackexchange.com/questions/139840/what-is-the-real-usage-of-product-attribute-quantity-and-stock-status]
* There is an attribute "minimal_price", but I don't think it is actually used. There is confusion about the use of minimum advised price (MAP) and manufacturer's suggested retail price (MSRP). Check [https://github.com/magento/magento2/issues/5662]
* Configurables require a price, but once set, cannot be changed. It is used to default price the variants. Grouped products have no price. For bundle products a price is shown in the backend, but it cannot be set, nor edited (?)
* The database structure of custom options is prepared for different titles and prices per store view. However, when you edit custom options via the backend, they are always stored on the global level. This is either a bug, or an incomplete feature. See [https://github.com/magento/magento2/issues/6165]
* First and second level categories have no url_key, url_path and url_rewrites entries. First level is invisible root (1). Second level is website root (Default Category = 1).
* in catalog_product_entity, has_options is a flag that denotes the presence of custom options. required_options denotes the present of required options. The flags have a different meaning for bundled products and configurable products, but I don't know what it is. Currently they are given fixed values.
