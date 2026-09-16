# From nothing to a working module-catalog-index

By the end of this, a category page on your store is filled from OpenSearch instead of MySQL, and you can prove it.

Plan on about an hour on a staging copy of the store. Don't start on production.

## Contents

- [What this is](#what-this-is)
- [Before you start](#before-you-start)
- [Step 1: install it](#step-1-install-it)
- [Step 2: point it at OpenSearch](#step-2-point-it-at-opensearch)
- [Step 3: build the documents](#step-3-build-the-documents)
- [Step 4: switch on one page type](#step-4-switch-on-one-page-type)
- [Step 5: prove an order updates the page](#step-5-prove-an-order-updates-the-page)
- [If something looks wrong](#if-something-looks-wrong)
- [What you get for free](#what-you-get-for-free)
- [Where to go next](#where-to-go-next)

## What this is

When a shopper opens a category page, Magento asks OpenSearch which products to show, then loads every one of them again from MySQL. On a busy catalog, that second step is most of the database load.

This module keeps a ready-made document for each product, price, stock level and category in OpenSearch. Pages fill Magento's normal product objects from those documents, so your theme and extensions keep working. When a document is missing or OpenSearch is down, the page loads from MySQL as before, and the module counts it.

## Before you start

You need:

- Magento Open Source or Adobe Commerce 2.4.8 or later, or Mage-OS on the same framework, running PHP 8.4
- OpenSearch that the store already uses for catalog search
- Cron running, so the queue consumers and the per-minute jobs start

> [!warning] Two stores, not yet production
> This has been installed and proved on Magento Open Source 2.4.8-p2 and Adobe Commerce 2.4.8-p2, both in production mode with a full catalog. No store has run it under real traffic. The README lists what is still unproved under *What has been proved, and where*.

## Step 1: install it

```bash
composer require kingletas/module-catalog-index
bin/magento module:enable Kingletas_CatalogIndex
bin/magento setup:upgrade
```

Nothing a shopper sees changes yet. Every page type starts switched off.

## Step 2: point it at OpenSearch

By default the module uses the same host and credentials as **Stores > Configuration > Catalog > Catalog > Catalog Search**. If that already works, there's nothing to set.

To use a separate cluster, go to **Stores > Configuration > Catalog > Catalog Index > OpenSearch Connection**, set **Use the Catalog Search Engine Connection** to No, and fill in the host.

Then put the four indexers on schedule. This is the step that installs the database triggers that record every change:

```bash
bin/magento indexer:set-mode schedule kingletas_catalog_index_product kingletas_catalog_index_price kingletas_catalog_index_stock kingletas_catalog_index_category
```

If you skip this, documents are only built by a full rebuild and go stale between rebuilds.

## Step 3: build the documents

Switch building on, then build everything once:

```bash
bin/magento config:set kingletas_catalog_index/general/enabled 1
bin/magento kingletas:catalog-index:rebuild
```

Check the result:

```bash
bin/magento kingletas:catalog-index:status
```

You should see one row per index for every active store view or website, each with a document count. A count of zero on a store that has products means the build found nothing to index. Check that the products are enabled and assigned to that website.

Now check the documents match the database:

```bash
bin/magento kingletas:catalog-index:verify --no-repair
```

A healthy store reports no drifted documents. Don't go on to the next step until it does.

## Step 4: switch on one page type

Start with category lists. They're the busiest page and the easiest to compare.

1. Open a category page and take a screenshot.
2. Go to **Stores > Configuration > Catalog > Catalog Index > Pages Served from Documents** and set **Category Product Lists** to Yes.
3. Flush the page cache and open the same category page.
4. Compare it with your screenshot. Names, images, prices, swatches and review stars should be identical.
5. Run `status` again. The reads table shows the category listing with a count of pages served from documents and pages that fell back.

A few fallbacks with the reason `missing_document` right after switching on are normal while the page cache refills. A steady stream of them means documents are missing for products on that page. Run `inspect` on one of them:

```bash
bin/magento kingletas:catalog-index:inspect SKU-123 --store-id=1
```

It prints what is stored next to what the database would build now, and names every field that differs.

Leave category lists on for a day and watch the fallback count before switching on the next page type.

## Step 5: prove an order updates the page

This is the part the module exists for.

1. Pick a product that shows on a category page and has a stock quantity of 1.
2. Place an order for it.
3. Within a few seconds, reload the category page. If the store hides out-of-stock products, it's gone. If it shows them, it's marked out of stock.

If it takes minutes instead of seconds, the priority consumer probably isn't running:

```bash
bin/magento queue:consumers:start kingletas.catalog_index.priority
```

On a store behind Varnish, `varnishlog -g request -q 'ReqMethod eq "PURGE"'` shows the purge. It should name that product's tags and nothing broader.

## If something looks wrong

Switch the page type off. The page goes straight back to loading from MySQL, and nothing else needs undoing.

| What you see | Most likely cause |
| --- | --- |
| Every page falls back with `breaker_open` | OpenSearch was unreachable. Pages wait 30 seconds and then try again |
| Every page falls back with `store_error` | The connection settings are wrong, or the indexes were never built |
| Old prices or stock that never update | The consumers aren't running, or the indexers are still on "Update on Save" |
| A product page ignores the switch | Bundle, grouped, downloadable, gift card and anything with custom options always load from MySQL |

## What you get for free

- A page never mixes documents with database data. If one product is missing, the whole page loads from MySQL.
- An older write can never replace a newer one, so a slow queue can't put stale stock back.
- A full rebuild never loses changes made while it runs.
- Only pages whose visible content changed are purged, and the module refuses to flush the whole cache.
- An hourly check rebuilds a sample of documents, repairs any that drifted, and only warns you when the share is past your budget.

## Where to go next

- [README](../README.md) for every setting, command and guarantee
- [Recommended settings](recommended-settings.md) for production values
- [Page coverage](page-coverage.md) for which pages work on which Magento versions
- [Architecture](architecture.md) for how the pieces fit together
- [CONTRIBUTING.md](../CONTRIBUTING.md)
