# Changelog

## Unreleased

First version. Nothing has been released yet.

Builds four families of document in OpenSearch: product and category per store view, price and stock per website. Product documents carry every attribute value, the product's URL, gallery, tier prices, review summary and category positions. A configurable product's document also carries its super attribute rows, with their store labels, and its option rows. Magento builds and caches the variants itself. Price documents carry every customer group's indexed prices. Stock documents carry salable quantity after open reservations and whether each variant can be bought.

Category lists, search lists, related, up-sell and cross-sell blocks, the CMS product list widget and GraphQL `products` fill their products' attributes from documents in one request instead of loading them from MySQL. Product pages for simple, virtual and configurable products without custom options, and category pages, load from documents too. Each of these has its own switch, and every switch is off on install.

No around plugin is involved. A listing takes the attributes documents can supply out of its database load before it loads, and fills them afterwards. A product or category page's own entity reads its attribute values through Magento's entity manager read handlers. So its entity row, its repository cache and every other plugin behave as they always did.

Installing the module in a real store turned up three problems. First, `mview.xml` and the plugin declarations named virtual types, which Magento refuses: `setup:upgrade` warned about a missing class and `setup:di:compile` failed. The subscription and listing markers are real classes now.

Second, the product page enters its document scope on `controller_action_predispatch_catalog_product_view`. That's because the controller loads the page's product before `catalog_controller_product_init_before` fires, and the repository then caches it.

Third, entries this module adds to Magento's own `AttributePool`, `ExtensionPool` and GraphQL collection-processor lists moved to `etc/di.xml`. In an area file Magento replaces such a list instead of adding to it. That had removed the default EAV read handler from the storefront and every core processor from GraphQL, so `products` queries came back with empty names. A wiring test now refuses an array argument on another module's class in any area file.

Every category list a page builds fills its attributes, its URL and its product count from documents, so the top menu, the breadcrumb and the layered navigation stop asking the database per category. It works on both the EAV and the flat category resource. That matters because the storefront uses the flat one by default. A category document's product count equals the count `Category::getProductCount()` would run, checked against every category on both editions.

Configurable products answer the option query Magento runs once per swatch attribute per product from their document instead. That query is Magento's heaviest on a listing of configurables. The document's rows match what it returns field for field and in the same order, checked against every configurable on both editions.

The option switch is off by default, and there's one kind of store where it must stay off. Where a website uses a stock other than the default one and out of stock products are hidden, Magento Inventory filters those rows by salability. A document can't know that without depending on multi-source inventory, and this module doesn't.

A configurable product's super attribute rows and their store labels come from its document too, behind a switch of their own. On a listing, Magento runs two queries per configurable product to learn which attributes tell its variants apart and what to call them in this store. A seeded attribute collection answers both.

The seeded collection is a real one, with its items in place and its loaded flag set. So everything that iterates it, counts it or asks it for its items behaves as before. The option rows in it come through the same provider the option switch already serves. A document that can't supply every row supplies none, because half a collection renders half the swatches.

Nothing reads a configurable's variants from its document any more, so they're no longer built. That saves a child collection load per build batch.

The module now watches `catalog_product_super_attribute` and `catalog_product_super_attribute_label` through the product change log, on both editions. Adding, removing or relabelling a super attribute refreshes the configurable's document and purges its cache tags, which clears Magento's cached variants for it. That holds even when the change comes from an import or direct SQL with no product save. Before this, such a change left the document and the cached variants stale until the product was next saved.

A swatch option's label and order live in the attribute rather than in any product, so renaming an option reaches no change log. Saving an attribute now queues every configurable that uses it. That was proved end to end: an option renamed, the attribute saved, the consumer run, and the new label read back out of the document.

Category documents already read once in a request aren't read again. A page walks the same categories for its menu, its breadcrumb and each node's children, and without this each walk was a separate multi-get.

A listing no longer asks the database for each product's categories. The document's category list is filled in, which removes one query per product on every listing page. Media gallery values keep the string shape the database gives them, so the gallery's own JSON renders exactly as Magento renders it.

With the category and swatch switches on as well, a Commerce category list of configurables makes 25% fewer database queries, and its median page time fell from 264 ms to 226 ms. Search results make 21% fewer, the home page 12% fewer and a configurable product page 7% fewer. On Open Source the same switches take 14% off a category list and 11% off a configurable product page. The category switch adds about 27 documents to a page's existing multi-get and no extra request to OpenSearch.

Measured on both editions in production mode. On Open Source with 1,200 products, a category page makes 19% fewer database queries, and a whole page renders in the same time because that store spends only 11.9 ms of about 176 ms in MySQL. On Adobe Commerce with 2,048 sample products, the same page goes from 169 statements and 48.3 ms of database time to 124 statements and 33.5 ms.

Eleven storefront pages and two GraphQL queries render identically with the switches off and on. That comparison sets aside request-varying values and the orderings Magento itself doesn't stabilise.

**Configurable variants are no longer built from documents, and the `read/hydrate_variants` switch is gone.** Magento keeps a configurable's variants in its shared cache once any request asks for them. A category page that built them from documents wrote variants with no gallery and no prices into that cache, and every product page served afterwards read them back.

The product page's per-swatch video data came up empty. A GraphQL products query that priced those variants cost 392 database statements against Magento's 23, one tier price and one catalog rule query per variant. With that cache warm, the build saved no statements on a category, search or home page. A store that set the switch keeps a harmless configuration row.

**Swatch attribute collections are built when Magento asks for one, not when a page loads.** Seeding every configurable's collection up front cost a GraphQL products query 46 extra statements, because GraphQL never asks for one. A before plugin on the configurable type now builds the collection at the moment Magento does. With every read switch on, a GraphQL products query costs 22 statements against Magento's 23.

**A configurable's attributes and options are filed under the one id Magento asks for each by**: attributes under the entity id, options under the link field. Under content staging the row id and the entity id come from separate counters. Once they drift, one product's row id can equal another product's entity id, and filing both under both ids let that product render the other's swatches.

A page falls back to the database when a document is missing, when OpenSearch fails, or while the circuit breaker is open after repeated failures. Each fallback is counted per page and reason, and `kingletas:catalog-index:status` reports the counts against a budget.

Several things keep documents current:

- change-log triggers on every table they're built from
- a priority queue for price and stock
- a refresh queue for products and categories
- observers on orders, refunds, cancellations and saves
- a per-minute sweep of new inventory reservations
- a per-minute scan for dated values and staged versions coming due

Queue topics name no connection, so they run on RabbitMQ where it's the store's default and on the database queue otherwise. Update Mode chooses between queueing, applying small batches inline, and leaving saves to the change log. Scheduled content updates are watched automatically where content staging is installed, and the setting can force that on or off.

Every request to OpenSearch goes through Magento's own client from `Magento_OpenSearch`. It's built from the catalog search connection or the module's own fields, with a short timeout for page reads. Writes use external versions, so an older build can never replace a newer document.

A full rebuild writes into a new index, replays the change log from when it started, moves the alias in one request and replays anything that arrived in between. Only cache tags for changes a shopper can see are purged, through the same event Magento's own indexers use, and an empty tag list is refused.

Purges run one at a time under a lock every server shares. A purge that can't get the lock within five seconds parks its tags in `kingletas_catalog_index_parked_purge`. The next purge to hold the lock, or a job every minute, purges them, so no purge is lost and none runs unlocked. `status` shows how many tags are parked. The clock and the lock handling come from Kingletas_Foundation 2.2.

An hourly check rebuilds a sample of product documents in memory, rebuilds any that differ from what's stored, and logs a warning only past a budget.

Adds `kingletas:catalog-index:status`, `rebuild`, `verify` and `inspect`, four indexers, two tables (`kingletas_catalog_index_state` and `kingletas_catalog_index_schedule`) and the `kingletas_catalog_index` configuration section.

Every line the console commands print, every status label and every exception message goes through `__()`, so a store that translates can. Log messages stay in English so they can be searched.

Requires PHP 8.4, so Magento Open Source or Adobe Commerce 2.4.8 or later. Adds `docs/from-nothing.md`, a walkthrough from install to a category page served from documents.
