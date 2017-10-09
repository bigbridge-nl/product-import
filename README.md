# Product Import

Imports product data into Magento 2 via direct database access.

Use this library if you need speed and don't care about plugins and custom event handling that is normally done by Magento.

## To be supported

* all types of products
* categories
* product-categorie links (by id, by name)
* auto create category (from name)
* stock
* multiple store views, multiple sites
* auto add attribute option
* csv import
* rest request
* validation
* error reporting, per product
* flat tables
* indexes

## Goals

* fast
* easy to use
* robust
* hard to make mistakes
* flexible
* complete

## Assumptions

* Input in UTF-8

## Test

* Think about very long field values (not crossing default 1MB query size?)

## Thanks to

This project ows a great deal of ideas and code from Magmi / Magento 1 [Magmi](https://github.com/dweeves/magmi-git)
