# Database

## Supported Magento 2 database tables

* catalog_product_entity and catalog_product_entity_*
* catalog_category_product
* catalog_category_entity (insert only)
* url_rewrite
* catalog_url_rewrite_product_category
* catalog_product_entity_media_gallery
* catalog_product_entity_media_gallery_value_to_entity
* catalog_product_entity_media_gallery_value
* cataloginventory_stock_item
* eav_attribute_option
* eav_attribute_option_value

## Remarks

* The fields 'deferred_stock_update' and 'use_config_deferred_stock_update' that are available in the ui of 'Advanced Inventory' are not stored and not used by Magento [https://community.magento.com/t5/Admin-Configuration-Questions/Use-Deferred-Stock-Update/td-p/67547]
* The eav attribute 'quantity_and_stock_status' is unofficially deprecated and not used [https://magento.stackexchange.com/questions/139840/what-is-the-real-usage-of-product-attribute-quantity-and-stock-status]
