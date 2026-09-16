# Recommended settings

What to set on a production store, and why. Every setting lives under **Stores > Configuration > Catalog > Catalog Index**, and the section id is `kingletas_catalog_index`.

## Contents

- [The short version](#the-short-version)
- [Turning it on for the first time](#turning-it-on-for-the-first-time)
- [Connection and timeouts](#connection-and-timeouts)
- [Index layout](#index-layout)
- [Update mode and batch size](#update-mode-and-batch-size)
- [Purging](#purging)
- [Staging and dated values](#staging-and-dated-values)
- [Drift and fallback budgets](#drift-and-fallback-budgets)
- [What to watch](#what-to-watch)

## The short version

| Setting | Production value | Why |
| --- | --- | --- |
| `general/enabled` | `1` once the first rebuild has run | With it off, nothing is built or read |
| `connection/use_catalog_search` | `1` unless documents live on a separate cluster | One cluster to run, one set of credentials |
| `connection/read_timeout_ms` | `400`, or the p99 of a healthy `_mget` plus a margin | This is the longest a page will wait before using the database |
| `connection/write_timeout` | `30` | Bulk writes of a full batch |
| `connection/index_prefix` | a name unique to the environment, such as `shop_prod` | Two environments on one cluster must never share aliases |
| `index/replicas` | `1` on a cluster with more than one node | A lost node shouldn't send pages to the database |
| `index/keep_previous` | `1` | One build to roll back to by hand |
| `read/*` | on, one page type at a time | See below |
| `read/category_tree` | `1` | Takes a count query per category off every page that shows a menu, a breadcrumb or a filter |
| `read/configurable_options` | `1`, unless a website uses a stock other than the default one and the store hides out of stock products | Magento Inventory filters those option rows by salability on such a store, and a document can't |
| `read/configurable_attributes` | `1` | Takes two queries per configurable off a listing, built only when Magento asks |
| `read/breaker_failures` | `5` | |
| `read/breaker_cooldown` | `30` | Long enough to stop a pile-up, short enough to recover quickly |
| `updates/mode` | `queue` | Consumers absorb bursts; inline mode adds work to admin requests |
| `updates/batch_size` | `200` | Raise it only with a measurement |
| `purge/enabled` | `1` | Without it, pages keep showing old documents until their cache expires |
| `purge/low_stock_threshold` | the number your theme's "only X left" message uses, or `0` | Otherwise a stock change that only moves that message purges nothing |
| `staging/mode` | `auto` | |
| `schedule/enabled` | `1` | Special prices that end on a date need it |
| `drift/sample_size` | `200` per store view | |
| `drift/alert_ratio` | `0.02` | |
| `metrics/fallback_budget` | `0.05` | |

## Turning it on for the first time

1. Put the four indexers on schedule, set `general/enabled` to `1`, and run `bin/magento kingletas:catalog-index:rebuild`.
2. Check that the consumers are running and that `status` shows every index live.
3. Run `verify --no-repair`. It should report nothing. If it reports drift straight after a rebuild, something is changing documents between builds, and it's worth understanding that before any page reads them.
4. Switch on `read/category_listing` for one store view.
5. Watch `status` for a day. The fallback ratio should settle near zero. A high `missing_document` count means products are reaching pages without documents, so check the change log backlog and the consumers. A high `store_error` or `breaker_open` count means OpenSearch is struggling at the read timeout.
6. Repeat for search lists, linked products and widgets, then category pages, then product pages, then GraphQL.

**Switch product pages on last.** They read the whole product from one document, so they're the most sensitive to a field a provider doesn't carry.

## Connection and timeouts

The read timeout is the only setting that changes how long a shopper waits. Measure a healthy `_mget` for a full listing on your cluster, and set the timeout comfortably above its slowest normal response. If it's too low, pages fall back when OpenSearch is merely busy. If it's too high, an outage makes pages slow before the breaker opens.

The password field is encrypted and declared sensitive. Set it with `bin/magento config:sensitive:set --interactive`, so it stays out of your shell history and out of `app/etc/config.php`.

## Index layout

Leave `index/shards` at `1` for almost every catalog. A single shard holds hundreds of thousands of these documents comfortably, and more shards make every `_mget` fan out further.

Set `index/replicas` to at least `1` wherever the cluster has a second node, so losing a node doesn't send every page to the database.

## Update mode and batch size

**Queue** is the right mode for production.

**Inline** suits a small store that isn't running consumers. It applies saves of up to `updates/inline_limit` products in the request that made them.

**Change log only** suits a store whose cron runs every minute and that doesn't want messages for saves. Orders, schedules and reservations are still queued, because no change log records them.

`updates/batch_size` is the number of ids per build and per message. Each batch costs one collection load, one query per field provider, and one read and one write to OpenSearch. Raise it if a rebuild spends most of its time on round trips. Lower it if a batch runs out of memory on products with very large galleries.

## Purging

Purges go by tag, and only for changes a shopper could see. Leave `purge/enabled` on. Turning it off doesn't make pages faster; it makes them wrong until their cache expires.

`purge/low_stock_threshold` only matters if a page shows how many are left. At `0`, a product page is purged when the product becomes buyable or stops being buyable. Set it to the threshold your theme uses, and crossing that threshold purges the product page too.

## Staging and dated values

With `staging/mode` at `auto`, scheduled updates are watched only where content staging is installed. On a staged store that never schedules catalog changes, set it to `disabled` to save a few indexed queries a minute.

`schedule/enabled` rebuilds documents at the start of the day a special price, a "new from" date or a custom design date starts or ends, in the store view's timezone. There's no reason to turn it off.

## Drift and fallback budgets

`drift/alert_ratio` is the share of a sample that may differ before a warning is logged. With the shipped `0.02`, a store whose documents are healthy never logs it. The warning repeats only when drift doubles or six hours pass, so a problem that won't go away doesn't fill the log.

`metrics/fallback_budget` only changes how `status` labels a page. Both budgets are conventions to start from, not measurements. Tighten them once you know what normal looks like on your store.

## What to watch

| Signal | Healthy | Worth a look |
| --- | --- | --- |
| `status` backlog | near zero, rising and falling with activity | growing steadily: cron or `indexer_update_all_views` is not keeping up |
| `status` fallback ratio | under the budget | over it for more than an hour |
| `status` read breaker | closed | open more than briefly |
| `var/log/kingletas/catalog-index-*.log` | quiet | refused documents, failed purges, drift warnings |
| Queue depth for both consumers | drains within a minute | growing: add consumers or raise `maxMessages` |
