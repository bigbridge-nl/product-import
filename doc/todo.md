# Todo

## Magento Commerce

No support yet for Magento Commerce.

## Validation

* image not found? ignore, error
* check attribute value uniqueness
* check if reference ids exist
* check for tierprice duplicates and make sure the import still works
* import category-ids: check if the ids exist
* if the child of a compound that is itself a child of a compound is invalid, the outer compound may not be invalidated (nesting problem)

## Url rewrites

url_rewrites are created for all store views. No attempt is made to check if they belong to a website that the product is in.

## Extra

* setAllWebsiteIds() adds the product to all websites
