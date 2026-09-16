# Kingletas_CatalogIndex

Serves category, search, product and GraphQL pages from OpenSearch documents instead of loading every attribute from MySQL. It keeps those documents current within seconds of an order, a save or a scheduled change.

The database stays in charge of anything involving money. Pages show what the documents say, but add to cart and checkout still check stock and price against MySQL. If the documents are missing, stale or unreachable, a page quietly loads from the database instead. The module counts every time that happens, so you can see it.

Nothing a shopper sees changes on install. Every page type is off until you switch it on.

---

## Contents

- [What it does](#what-it-does)
- [Pages and versions](#pages-and-versions)
- [How it works](#how-it-works)
- [Installation](#installation)
- [Update lanes and RabbitMQ](#update-lanes-and-rabbitmq)
- [Content staging](#content-staging)
- [Commands](#commands)
- [What it guarantees](#what-it-guarantees)
- [What it costs and what it saves](#what-it-costs-and-what-it-saves)
- [What the category and swatch switches add](#what-the-category-and-swatch-switches-add)
- [Gotchas](#gotchas)
- [What has been proved, and where](#what-has-been-proved-and-where)
- [Tests](#tests)
- [Rebranding](#rebranding)

---

## What it does

A category page in Magento asks OpenSearch which products to show, then loads each of them again from MySQL. That second load reads attributes from five EAV tables, the media gallery, review summaries, and, for configurable products, their variants one parent at a time. It's where most of the database load on a busy catalog comes from.

This module keeps a ready-to-use document per product, price, stock level and category in OpenSearch, and fills those Magento objects from the documents instead. Your theme and extensions still get the `Product` and `Category` objects they expect. That's why Luma, Hyvä and headless storefronts all benefit without template changes.

| | Before | With this module |
| --- | --- | --- |
| Attribute values on a listing | One query per EAV backend type | One OpenSearch request for the whole page |
| Product, price and stock for a page | Several queries | The same single request |
| Review summary per product | One query per product on some themes | In the document |
| Swatch options on a configurable listing | One query per swatch attribute per product | In the document, when switched on |
| A category's product count | One count query per category | In the document |
| A product page | A full EAV load | One document read |
| After an order | Whatever the next reindex decides | Stock document refreshed on a priority queue, only changed pages purged |

---

## Pages and versions

Every page type has its own switch under **Stores > Configuration > Catalog > Catalog Index > Pages Served from Documents**.

| Page | What reads documents | Notes |
| --- | --- | --- |
| Category product lists | The layer's product collection | Filters, sorting, pagination and catalog permissions still run in the entity query |
| Search result lists | The search layer's product collection | Same path as category lists |
| Product pages | The product the page loads | Simple, virtual and configurable products without custom options. Everything else reads the database |
| Category pages | The category the page loads | |
| Category menus, breadcrumbs and filters | Every category list a page builds | Works on both the EAV and the flat category resource |
| Configurable swatches and options | The option rows Magento asks for per swatch attribute | Off on install. Turn it on only where every website uses the default stock or the store shows out of stock products |
| Related, up-sell and cross-sell | Link collections ordered by position | Includes the cart cross-sell block |
| CMS product list widget | The widget's collection | |
| GraphQL `products` | The query's product collection | Field resolvers that query on their own still do |

It's built for Magento Open Source and Adobe Commerce 2.4.8 and 2.4.9, and for Mage-OS releases on the same framework. It needs PHP 8.4, which Magento supports from 2.4.8.

It has run on Open Source 2.4.8-p2 and Adobe Commerce 2.4.8-p2, and its hooks were checked in the source of Open Source 2.4.9. The other releases are expected to work but haven't been checked yet. Content staging and multi-source inventory are detected, not required.

Page-by-page detail, including what's deliberately out of scope and why, is in [docs/page-coverage.md](docs/page-coverage.md).

---

## How it works

```text
          writes                                        reads
  ┌──────────────────────┐                     ┌──────────────────────────┐
  │ admin save, import,  │                     │ category, search, product│
  │ order, schedule      │                     │ widget, GraphQL          │
  └──────────┬───────────┘                     └────────────┬─────────────┘
             │ change log triggers, events                  │ collection marked
             ▼                                              ▼
   priority queue (price, stock)              one _mget: product + price + stock
   refresh queue (product, category)                        │
             │                                              ▼
             ▼                                     fill Magento objects,
   build documents in batches                      or load from MySQL and
   write with external versions ───────────▶      count why
   purge only the tags that changed
```

There are four document families. Each has its own index, change log, indexer and lane:

| Family | One document per | Updated by |
| --- | --- | --- |
| `product` | product and store view, a configurable's super attribute rows (with store labels) and option rows inside | Change log on EAV, media, category, URL, relation, review, `catalog_product_super_attribute` and `catalog_product_super_attribute_label` tables |
| `price` | product and website, every customer group inside | Change log on the price index |
| `stock` | product and website, variants' salability inside | Orders, refunds, cancellations, a reservation sweep, the stock change log |
| `category` | category and store view | Change log on category tables |

A configurable's product document doesn't carry its variants. Magento keeps building those itself and caches them.

The product change log watches the two super attribute tables on both Open Source and Adobe Commerce. So when a configurable's super attribute is added, removed or relabelled, even by an import or direct SQL with no product save, that product's document is refreshed within the normal update window. Its cache tags are purged too, which also clears Magento's cached variants for it.

The full design, with the reasoning for each decision, is in [docs/architecture.md](docs/architecture.md).

---

## Installation

```bash
composer require kingletas/module-catalog-index
bin/magento module:enable Kingletas_CatalogIndex
bin/magento setup:upgrade
```

It needs `Magento_OpenSearch` enabled, which every 2.4.8 and later install ships with, and `Kingletas_Foundation` 2.2 or later.

Then, in this order:

1. **Point it at OpenSearch.** By default it reuses the host and credentials under Catalog Search. To use a separate cluster, set **Use the Catalog Search Engine Connection** to No and fill in the connection group.
2. **Put the indexers on schedule.** That's what installs the change-log triggers:

    ```bash
    bin/magento indexer:set-mode schedule kingletas_catalog_index_product kingletas_catalog_index_price kingletas_catalog_index_stock kingletas_catalog_index_category
    ```

3. **Switch on building**, then build everything once:

    ```bash
    bin/magento config:set kingletas_catalog_index/general/enabled 1
    bin/magento kingletas:catalog-index:rebuild
    ```

4. **Run the consumers**, or let `cron_consumers_runner` start them:

    ```bash
    bin/magento queue:consumers:start kingletas.catalog_index.priority
    bin/magento queue:consumers:start kingletas.catalog_index.refresh
    ```

5. **Check before you trust it.** `status` should show every index live with a document count, and `verify` should find no drift:

    ```bash
    bin/magento kingletas:catalog-index:status
    bin/magento kingletas:catalog-index:verify --no-repair
    ```

6. **Switch pages on one at a time**, starting with category lists. Watch the fallback column in `status` for a day before you turn on the next one.

Recommended production values, and the reasons for them, are in [docs/recommended-settings.md](docs/recommended-settings.md).

---

## Update lanes and RabbitMQ

Refreshes travel on two topics, so a large product import never delays a stock change:

| Topic | Carries | Consumer |
| --- | --- | --- |
| `kingletas.catalog_index.priority` | price and stock | `kingletas.catalog_index.priority` |
| `kingletas.catalog_index.refresh` | product and category | `kingletas.catalog_index.refresh` |

**Neither topic names a connection**, so the store's default decides. If RabbitMQ is configured in `app/etc/env.php`, they run on RabbitMQ. If it isn't, they run on the database queue. To choose explicitly:

```php
'queue' => [
    'default_connection' => 'amqp',
    'topics' => [
        'kingletas.catalog_index.priority' => ['publisher' => 'amqp-magento'],
    ],
],
```

**Update Mode** decides whether a refresh is queued at all:

| Mode | Behaviour |
| --- | --- |
| Queue (default) | Every refresh is published and the consumers apply it |
| Inline for small batches | A save of up to **Largest Inline Refresh** products is applied in the same request. Orders are always queued, so checkout never waits |
| Change log only | Saves are left to the change log cron; orders, schedules and reservations are still queued, because no change log records them |

---

## Content staging

Adobe Commerce records a scheduled update as a new row version that becomes active later. No database trigger fires when that moment arrives. **Watch Scheduled Updates** handles it:

| Setting | Behaviour |
| --- | --- |
| Automatic (default) | Watches when `Magento_Staging` is enabled and the catalog tables carry `row_id` |
| Always | Watches whenever the tables carry `row_id`, even if the staging module is disabled |
| Never | Doesn't scan; use this on a staged install that never schedules catalog changes |

While it's watching, a cron job runs every minute. It finds product and category versions that started or ended since its last run and queues their documents. The change-log triggers on staged tables resolve `row_id` to the entity id, so the same `mview.xml` works on both editions.

Dated values that aren't staging, such as a special price that ends on a date, are handled on every edition. Building a document records the moment it next changes, and the same cron job queues it then.

---

## Commands

```bash
bin/magento kingletas:catalog-index:status                     # builds, backlog, fallbacks, breaker
bin/magento kingletas:catalog-index:status --hours=1
bin/magento kingletas:catalog-index:rebuild                    # every family into fresh indexes
bin/magento kingletas:catalog-index:rebuild --family=stock --scope=1
bin/magento kingletas:catalog-index:rebuild --family=product --ids=12,34
bin/magento kingletas:catalog-index:verify --sample=500        # rebuild a sample in memory and compare
bin/magento kingletas:catalog-index:verify --no-repair
bin/magento kingletas:catalog-index:inspect SKU-123 --store-id=1
```

`indexer:reindex kingletas_catalog_index_product` runs the same full rebuild as the command.

---

## What it guarantees

### A page never mixes sources

A listing is filled from documents only if every product on it has one. One missing document sends the whole page to the database, so a shopper never sees two versions of the catalog side by side.

### An outage costs one timeout per cooldown

Page reads wait **400 ms** by default. After **5** failures in a row, the circuit breaker stops pages asking for **30 seconds**, and every page in that window loads from the database. Without it, an OpenSearch outage would add a timeout to every request.

### A slower writer can never overwrite a faster one

Every document is written with an external version taken from the clock before its data was read. OpenSearch refuses a write older than what it holds, so a slow consumer that finishes late doesn't replace fresher data.

### A rebuild never loses what changed while it ran

A full rebuild writes into a new index and replays the change log from the moment it started. Then it moves the alias in one request and replays anything that arrived in between. The previous build is kept, so you can roll back by hand.

### Only what a shopper would see change is purged

Each document carries a fingerprint per group of fields. After a write, the module compares fingerprints and purges only:

- `cat_p_{id}` when something on a listing or product page changed
- `cat_c_p_{id}` for categories a product joined or left, or where it became or stopped being buyable
- `cat_c_{id}` when a category page changed

Stock going from 40 to 39 purges nothing. An empty tag list, which Magento reads as "clean everything", is refused.

Purges run one at a time under a lock shared by every server. If a purge can't get the lock within five seconds, it parks its tags. The next purge, or a job that runs every minute, sends them, so none is lost.

Purges go through `clean_cache_by_tags`, so Varnish and the built-in page cache both hear them. The application cache (Valkey or Redis) is cleaned by the same tags.

### Every fallback has a reason

`missing_document`, `store_error`, `breaker_open` and `unsupported_product` are counted per page per hour in the application cache, never in the database. `status` marks a page over budget when its fallbacks pass **Fallback Budget**.

### Drift repairs itself and only speaks up when it matters

Every hour, a sample of product documents is rebuilt in memory and compared with what's stored. Anything that differs is rebuilt. A warning is logged only when the share passes **Warn Above This Share Drifted**, and it repeats only when that share doubles or six hours have passed.

---

## Gotchas

> [!tip] Every read switch can be on
> Measured on Adobe Commerce 2.4.8-p2 with every read switch on and every statement timed: a category listing of twelve configurables costs **69 database statements against Magento's 169**, a configurable product page **92 against 109**, and a 24-product GraphQL query **22 against 23**. Every storefront page renders byte for byte what Magento renders. The one exception is the order of related products, which Magento shuffles itself.

- **Configurable variants are never built from documents.** Once any request asks for a configurable's variants, Magento keeps them in its shared cache. A variant built from a document would carry no gallery and no prices. Writing one into that cache would empty a product page's per-swatch video data, and anything that prices it would query one variant at a time. With that cache warm, the build saved nothing, so it isn't done. The product document carries the configurable's super attribute rows and option rows instead.
- **Swatch attribute collections are built when Magento asks for them, not when a page loads.** Most surfaces never ask, and GraphQL is one of them. Building every configurable's collection up front cost a GraphQL products query 46 extra statements.
- **Salability on a page still runs Magento's own check.** Documents set `is_salable` for templates that read it, but never `salable`. That key makes `Product::isSalable()` return before the events that catalog permissions and other extensions use. So a configurable listing still runs one count query per product, which is the largest single piece of database work left on that page. It's possible to answer that from a document without losing those events, because the type model is called between the two of them. It isn't done here, and what it would take is in [docs/page-coverage.md](docs/page-coverage.md).
- **The display isn't the decision.** Salable quantity in a stock document subtracts open reservations and the minimum quantity, but it doesn't model every inventory rule. Checkout decides from MySQL, as it always did.
- **Product pages for bundle, grouped, downloadable and gift card products, and any product with custom options, always read the database.** Their pages render data a document doesn't carry.
- **Listings keep Magento's entity query.** Price, sort order, filters and catalog permissions still come from MySQL and the price index. What leaves MySQL is the attribute load, the gallery query and the per-product lookups.
- **A full reindex of Magento's price or stock index swaps tables and fires no trigger.** When that happens, the module queues a full rebuild of its price or stock documents, so expect a burst of work after `indexer:reindex`.
- **The first reservation sweep starts from the newest reservation**, not the oldest. Enabling the module on a store with years of reservations won't replay them.
- **Counters are approximate under heavy concurrency**, because the cache has no atomic increment. They're a rate, not a ledger.
- **Serving configurable options is off by default, and there's one kind of store it must stay off for.** Magento Inventory filters those option rows by salability when a website uses a stock other than the default one and the store hides out of stock products. A document can't know that without depending on multi-source inventory, and this module doesn't. Leave the switch off on such a store, and Magento's own query answers as before.
- **A swatch option's label lives in the attribute, not the product.** Renaming or reordering options changes no product row, so nothing reaches the change log. When an attribute is saved, the module queues every configurable that uses it, which covers the admin. A label changed straight in the database reaches nothing until the next rebuild.
- **Category counts match the count a category page asks for, not the one a collection loads.** A collection told to load product counts computes an anchor category's count across its descendants, and it overwrites the document's value with its own. The document replaces the single count `Category::getProductCount()` would otherwise run. That's the one a menu, a breadcrumb and a filter cost.
- **Clocks must agree.** Versions come from the clock of whichever server built the document. If hosts are more than a few seconds apart, an older build could win a race.
- **Magento doesn't order everything it renders.** A configurable product's swatch options, and a product page's related and up-sell items, come back in an order that differs between two identical requests, with or without this module. If you compare two renders byte for byte, normalise those first, or the comparison reports a difference that isn't one.
- **Parked purges need cron.** A purge that waited too long for the lock is parked and sent by the next purge or the per-minute job. If cron is stopped and nothing else purges, those pages stay stale. `status` shows how many tags are parked.
- **The OpenSearch password goes into a URL.** The module uses Magento's own OpenSearch client, which puts credentials into the host URL without encoding them. A password containing `@`, `:` or `/` breaks the connection here, exactly as it breaks catalog search.
- **Another module replacing Magento's EAV read handler on the storefront** would be bypassed for the product and category page's own entity, because this module wraps Magento's handler. Nothing in Open Source or Adobe Commerce 2.4.8 does that.

---

## What it costs and what it saves

Measured on Magento Open Source 2.4.8-p2 in production mode, with 1,200 products from Magento's own performance fixtures and the page cache bypassed. The module's switches were flipped off and on in alternating rounds, so the machine's own drift cancels out.

| Page | MySQL queries, off → on | Change |
| --- | --- | --- |
| Category list, 28 products | 89.2 → 72.2 | 19% fewer |
| Category list, nested | 86.2 → 69.2 | 20% fewer |
| Search results | 94.2 → 78.2 | 17% fewer |
| Product page, simple | 57.2 → 50.2 | 12% fewer |
| Product page, configurable | 74.2 → 66.2 | 11% fewer |
| GraphQL `products`, 24 items | 34.2 → 33.2 | 3% fewer |
| Home page | 20.2 → 20.2 | unchanged, nothing on it reads documents |

With three concurrent shoppers doing equal work, queries per request fell from 84.9 to 71.2 and rows scanned per request from 297 to 284. Each request fetched 25.5 documents from OpenSearch.

On **Adobe Commerce 2.4.8-p2** with 2,048 sample products, the saving is larger, because its pages carry configurable products with swatches:

| Page | MySQL queries, off → on | Change |
| --- | --- | --- |
| Category list, men's tops | 169.2 → 124.5 | 26% fewer |
| Home page, with a product widget | 86.2 → 78.2 | 9% fewer |
| Product page, configurable | 109.2 → 101.2 | 7% fewer |
| Search results | 133.2 → 129.5 | 3% fewer |

Timed with MariaDB's slow log at a zero threshold, that category page spends **48.3 ms in MySQL without the module and 33.5 ms with it**, across 169 statements and 124 statements.

**Page time didn't change on Open Source, and it improved on Commerce in step with its database time.** On this store, a category page spends **11.9 ms of about 176 ms inside MySQL**. Even removing every catalog query it makes could save at most 7% of the page.

This module moves database work, not rendering work, and rendering is where a Magento page spends its time. Expect it to matter where the database is the constraint: a large catalog, many concurrent shoppers, a read replica under pressure, or a database billed by load. Don't expect a faster page on a small catalog.

### What the category and swatch switches add

Measured after the rest of the module was already on. Those two switches were flipped off and on in alternating rounds, twelve runs a page, with the page cache bypassed.

**Adobe Commerce 2.4.8-p2, 2,048 sample products, production mode.**

| Page | MySQL queries, off → on | Change |
| --- | --- | --- |
| Category list, men's tops | 124.2 → 93.2 | 25% fewer |
| Search results | 130.5 → 103.2 | 21% fewer |
| Home page, with a product widget | 78.5 → 69.2 | 12% fewer |
| Product page, configurable | 101.2 → 94.2 | 7% fewer |
| Category list, gear | 73.2 → 68.2 | 7% fewer |
| Product page, simple | 71.2 → 70.2 | 1% fewer |

**Magento Open Source 2.4.8-p2, 1,200 products from Magento's own performance fixtures.**

| Page | MySQL queries, off → on | Change |
| --- | --- | --- |
| Category list, 28 products | 72.2 → 62.2 | 14% fewer |
| Category list, nested | 69.2 → 60.2 | 13% fewer |
| Product page, configurable | 66.2 → 59.2 | 11% fewer |
| Search results | 79.5 → 76.2 | 4% fewer |
| Product page, simple | 50.2 → 49.2 | 2% fewer |

**The category switch adds documents to the request, not requests.** With it on, a page reads about 27 more category documents, and OpenSearch connections per request don't change. The extra documents arrive inside the multi-get the page already makes. A category's document that's needed twice in one request is read once.

**Page time moved on one page.** The Commerce category list of configurables went from a median of 264 ms to 226 ms, and that held in all three rounds. Every other page stayed inside this machine's own noise. That's the same result the module as a whole gives: it moves database work, not rendering work.

## What has been proved, and where

The suites prove the classes and their wiring. These stores prove the rest.

**Magento Open Source 2.4.8-p2, PHP 8.4, 1,200 products from Magento's own performance fixtures, production mode.**

| Claim | Result |
| --- | --- |
| Installs from a composer path repository, enables, upgrades, compiles | Yes, after two defects were fixed: `mview.xml` and two plugins named virtual types |
| Change-log triggers sit beside Magento's own | 72 triggers reference this module's change logs on the tables it subscribes to |
| A full rebuild of every family | 1,200 product, price and stock documents and 31 categories, in about six seconds |
| `verify` finds no drift | 0 of 200 sampled documents differed |
| Category lists, search lists, category pages, product pages and GraphQL read documents | Yes; `status` counted every read and no fallback |
| Pages render exactly as they do without the module | Yes: 11 pages byte-identical after request-varying values are normalised |
| A core stock reindex that fires no trigger still reaches the documents | Yes: the follow-the-indexer plugin queued a rebuild, the consumer applied it, and the product page then said out of stock |
| An OpenSearch outage leaves the storefront answering | Yes: category and product pages answered 200, the breaker opened, and `status` marked the category page over budget with `store_error` |
| Database work per request | 16% fewer queries; see *What it costs and what it saves* |
| Every category document's product count equals the count Magento's own query returns | Yes, all 31 categories |
| Every configurable's option rows equal the rows Magento's own query returns | Yes, all 32 groups, field for field and in the same order |

**Adobe Commerce 2.4.8-p2 with content staging and catalog permissions, 2,048 sample products, production mode.**

| Claim | Result |
| --- | --- |
| Installs beside the released Kingletas modules | Yes, with a targeted composer update; it needs foundation 2.2 |
| Staging is detected | `status` reports staging watched |
| The `row_id` change log resolves the entity id | Yes. Commerce's own staging subscription covers the staged attribute tables, and this module's covers `catalog_product_entity_media_gallery_value_to_entity`, which Commerce's doesn't |
| A full rebuild of every family | 2,048 documents per family and 38 categories |
| `verify` finds no drift | 0 of 200 sampled documents differed |
| Every category document's product count equals the count Magento's own query returns | Yes, all 38 categories, with content staging installed |
| Every configurable's option rows equal the rows Magento's own query returns | Yes, all 294 groups across 147 configurables, field for field and in the same order |
| Pages render exactly as they do without the module | 9 of 10 byte-identical. The tenth differs only in the order of its related products, which differs between two identical requests of the same state |
| Renaming a swatch option reaches the documents | Yes: the option was renamed, the attribute saved, the consumer run, and the new label appeared in the document |
| Adding, removing or relabelling a configurable's super attribute with no product save reaches the documents | Yes: the product's document was refreshed within the normal update window and its cache tags were purged |

Still unproved: Mage-OS, Open Source 2.4.9, B2B shared catalogs, multi-source inventory with more than one source, Hyvä, and any store under real traffic.

---

## Tests

```bash
make install    # needs repo.magento.com credentials, for magento/framework
make check
```

`make check` runs the coding standard and all four suites: 254 tests, with no database and no Magento bootstrap.

| Suite | What it covers |
| --- | --- |
| Unit | Every class with its collaborators doubled |
| Wiring | Every XML file against the code it names |
| Behaviour | An order taking a product off category pages; a rebuild keeping a mid-build price change; a page falling back through a store outage and the breaker; a purge parked while another holds the lock and sent by the next holder |
| Performance | A batch of any size costs the same queries; a listing of any size is one OpenSearch request; refreshes cost one read and one write per batch; purges cost one event per 500 tags |

---

## Rebranding

```bash
php ../bin/rebrand Acme
```

After rebranding, move the existing data:

```sql
RENAME TABLE kingletas_catalog_index_state TO acme_catalog_index_state;
RENAME TABLE kingletas_catalog_index_schedule TO acme_catalog_index_schedule;
RENAME TABLE kingletas_catalog_index_parked_purge TO acme_catalog_index_parked_purge;
UPDATE core_config_data SET path = REPLACE(path, 'kingletas_catalog_index/', 'acme_catalog_index/')
 WHERE path LIKE 'kingletas_catalog_index/%';
```

The rewrite renames the indexer ids, change-log tables, queue topics and console commands. Existing OpenSearch aliases keep the old prefix, so set **Index Prefix** before the first rebuild on the new name, or rebuild once afterwards.
