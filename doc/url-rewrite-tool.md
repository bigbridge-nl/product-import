# Url Rewrite Tool

This command line tool updates the url-rewrite entries of your products.

    bin/magento bigbridge:product:urlrewrite

Without any further options, it checks the existing url_rewrite records of all store views and only updates them when necessary.

It respects the Magento setting that tells you if 301 redirects must be created when the product url changes.

## Specific store views

You can specify one or more store views if you only want these updated:

    bin/magento bigbridge:product:urlrewrite -s en_store -s nl_store

## Delete 301 redirects

The tool has another option and it should be used with care

    bin/magento bigbridge:product:urlrewrite --redirects delete

When the url of a product page changes, by default Magento keeps the old url and creates an HTTP response 301 REDIRECT when this url is requested. If this Magento option is turned off, no redirects will be stored.

However, the number of redirects is a known source of database bloat. When a shop is set up, and many products move from one category to the next, the url_rewrite table may be filled with hundreds of thousands of senseless redirects. In this case, the "delete" option is handy. It removes all 301's from the database and creates no new 301's in this run.

Use in a production shop is inadvisable. It is a SEO killer: products will no longer be accessible via old urls that may exist on the internet.

## Delete category path urls

If your shop has "Use Categories Path for Product URLs" (Configuration / Catalog / Search Engine Optimization) turned off, there is no sense creating url_rewrites with category paths (i.e. gear/bags/joust-duffie-bag.html), they are not used. But Magento and this library will create them anyway.

This takes a lot of time in url_rewrite creation and it takes up most of the records in the url_rewrite table.

This is how to get rid of these rewrites:

    bin/magento bigbridge:product:urlrewrite --category-path-urls delete

It makes sure these are not created, and will be removed if they are exist.

Note that this will also remove existing category path product url redirects.
