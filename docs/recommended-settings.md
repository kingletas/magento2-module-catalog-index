# Recommended settings

What to set on a production store, and why. Every setting lives under **Stores > Configuration > Catalog > Catalog Index**, section id `kingletas_catalog_index`.

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
| `index/replicas` | `1` on a cluster with more than one node | A lost node should not take pages to the database |
| `index/keep_previous` | `1` | One build to roll back to by hand |
| `read/*` | on, one page type at a time | See below |
| `read/category_tree` | `1` | Takes a count query per category off every page that shows a menu, a breadcrumb or a filter |
| `read/configurable_options` | `1`, unless a website uses a stock other than the default one and the store hides out of stock products | Magento Inventory filters those option rows by salability on such a store, and a document cannot |
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
2. Confirm the consumers are running and `status` shows every index live.
3. Run `verify --no-repair`. It should report nothing. If it reports drift straight after a rebuild, something changes documents between builds, and that is worth understanding before any page reads them.
4. Switch on `read/category_listing` for one store view.
5. Watch `status` for a day. The fallback ratio should settle near zero. A high `missing_document` count means products are reaching pages without documents: check the change log backlog and the consumers. A high `store_error` or `breaker_open` count means OpenSearch is struggling at the read timeout.
6. Repeat for search lists, linked products and widgets, then category pages, then product pages, then GraphQL.

**Switch product pages on last.** They read the whole product from one document, so they are the most sensitive to a field a provider does not carry.

## Connection and timeouts

The read timeout is the only setting that changes what a shopper waits for. Measure a healthy `_mget` for a full listing on your cluster, and set the timeout comfortably above its slowest normal response. Too low and pages fall back when OpenSearch is merely busy; too high and an outage makes pages slow before the breaker opens.

The password field is encrypted and declared sensitive. Set it with `bin/magento config:sensitive:set --interactive` so it stays out of shell history and out of `app/etc/config.php`.

## Index layout

`index/shards` stays at `1` for almost every catalog. A shard holds hundreds of thousands of these documents comfortably, and more shards make every `_mget` fan out further.

`index/replicas` should be at least `1` wherever the cluster has a second node, so a node loss does not send every page to the database.

## Update mode and batch size

**Queue** is right for production. **Inline** suits a small store without consumers running, and applies saves of up to `updates/inline_limit` products in the request that made them. **Change log only** suits a store whose cron runs every minute and that wants no messages for saves; orders, schedules and reservations are still queued, because no change log records them.

`updates/batch_size` is the number of ids per build and per message. Each batch costs one collection load, one query per field provider, one read and one write to OpenSearch. Raise it if a rebuild spends most of its time on round trips; lower it if a batch runs out of memory on products with very large galleries.

## Purging

Purges are by tag and only for changes a shopper could see. Leave `purge/enabled` on. Turning it off does not make pages faster; it makes them wrong until their cache expires.

`purge/low_stock_threshold` matters only if a page shows how many are left. At `0`, a product page is purged when the product becomes or stops being buyable. Set it to the threshold your theme uses, and crossing it purges the product page too.

## Staging and dated values

`staging/mode` at `auto` watches scheduled updates only where content staging is installed. Set it to `disabled` on a staged store that never schedules catalog changes, to save a few indexed queries a minute.

`schedule/enabled` rebuilds documents at the start of the day a special price, a "new from" date or a custom design date starts or ends, in the store view's timezone. There is no reason to turn it off.

## Drift and fallback budgets

`drift/alert_ratio` is the share of a sample that may differ before a warning is logged. The shipped `0.02` means a store whose documents are healthy never logs it. The warning repeats only when drift doubles or six hours pass, so a persistent problem does not fill the log.

`metrics/fallback_budget` only changes how `status` labels a page. Both budgets are conventions to start from, not measurements; tighten them once you know what normal looks like on your store.

## What to watch

| Signal | Healthy | Worth a look |
| --- | --- | --- |
| `status` backlog | near zero, rising and falling with activity | growing steadily: cron or `indexer_update_all_views` is not keeping up |
| `status` fallback ratio | under the budget | over it for more than an hour |
| `status` read breaker | closed | open more than briefly |
| `var/log/kingletas/catalog-index-*.log` | quiet | refused documents, failed purges, drift warnings |
| Queue depth for both consumers | drains within a minute | growing: add consumers or raise `maxMessages` |
