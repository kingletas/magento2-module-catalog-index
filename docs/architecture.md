# Architecture

How the catalog index is put together, and why each piece has the shape it has. The README says what the module does. This page says how it does it, for someone who's about to change it.

## Contents

- [The rule everything follows](#the-rule-everything-follows)
- [Four families of document](#four-families-of-document)
- [Building a document](#building-a-document)
- [Keeping documents current](#keeping-documents-current)
- [Writing, versions and purges](#writing-versions-and-purges)
- [Full rebuilds](#full-rebuilds)
- [Reading on the storefront](#reading-on-the-storefront)
- [Staying honest: fallbacks, drift and status](#staying-honest-fallbacks-drift-and-status)
- [Extending it](#extending-it)

## The rule everything follows

**Documents are for display. The database decides anything that costs money.** A page may show a product as in stock a few seconds after the last unit sold. That's fine, because add to cart checks MySQL and will say so. Every design choice below does one of three things: it makes documents fresher, it makes a stale one cheaper to catch, or it keeps a failure from reaching a shopper.

## Four families of document

```mermaid
flowchart LR
    subgraph store view
        P[product document]
        C[category document]
    end
    subgraph website
        R[price document<br/>every customer group]
        S[stock document<br/>variants' salability]
    end
    P -- same product id --- R
    P -- same product id --- S
```

| Family | Scope | Why that scope |
| --- | --- | --- |
| product | store view | Attribute values, labels and URLs differ per store view |
| category | store view | Same |
| price | website | Magento's price index is per website and customer group |
| stock | website | A stock serves a website's sales channel |

**Price and stock are separate documents.** That way an order or a price rule can rewrite a small document without rebuilding the product one. It also means a page reads all three in one `_mget`, with nothing joined at write time.

Each family has an alias, `{prefix}_{family}_{scope}`, which points at a physical index named `{alias}_{YmdHisu}`. Only lookup fields are mapped: `entity_id`, `sku`, `type_id`, `category_ids` and `is_salable`. Everything else is stored in `_source` and never indexed, so a catalog with thousands of attribute codes can't explode the mapping.

## Building a document

```mermaid
flowchart TD
    A[ids] --> B[load the batch once<br/>product collection, all attributes]
    B --> C[every provider: prepareBatch<br/>one query each]
    C --> D[every product: contribute<br/>no round trips]
    D --> E{excluded?}
    E -- yes --> F[removed id]
    E -- no --> G[document with a fingerprint per field group]
    D --> H[moments this product next changes]
```

`ProductDocumentBuilder` loads a batch with Magento's own product collection. That's how store-view fallback, website assignment and, on Adobe Commerce, the current staged version all get applied the same way the storefront applies them. Field providers, registered in `di.xml`, then add their part:

| Provider | Adds | Group |
| --- | --- | --- |
| Identity | id, SKU, type, visibility; excludes disabled products | listing |
| Attributes | every attribute value, split into listing and detail | listing, detail |
| Categories | category ids and positions | internal |
| URL | the product's own rewrite for the store view | listing |
| Media | the gallery, labels and positions for the store view | detail |
| Tier prices | tiers in the shape the tier price backend loads | detail |
| Configurable | a configurable's super attribute rows, with store labels, and its option rows | listing |
| Reviews | rating summary and count, zeros when there are none | listing |
| Custom options | whether any exist | internal |
| Schedule | when a dated value next starts or ends | not stored |

**The field group is what a change means.** `listing` and `detail` are rendered, and `internal` isn't. A document whose only change is `updated_at` gets rewritten but purges nothing.

**Every provider loads its batch in `prepareBatch` and never queries in `contribute`.** The performance suite holds the whole build to the same number of queries for one product as for two hundred.

**The link field is always resolved through `MetadataPool`.** Attribute, gallery, tier price and super link tables join on `row_id` where content staging is installed, and on `entity_id` where it isn't.

**A configurable's document doesn't embed its variants.** `ConfigurableFieldProvider` gives the product document the configurable's super attribute rows, with their store labels, and its option rows. The stock document still carries each child's salability.

## Keeping documents current

```mermaid
flowchart LR
    T[(change log triggers)] -->|cron, indexer_update_all_views| I[FamilyIndexer]
    O[order, refund, cancel] --> Pub[RefreshPublisher]
    Sv[product or category save] --> Pub
    Sch[schedule and staging cron] --> Pub
    Rs[reservation sweep cron] --> Pub
    Core[core full reindex] --> Pub
    Pub -->|priority topic| Q1[consumer]
    Pub -->|refresh topic| Q2[consumer]
    Pub -->|inline mode| R
    I --> R[Refresher]
    Q1 --> R
    Q2 --> R
```

There are several ways in, because no single one sees every change:

| Path | Catches | Misses |
| --- | --- | --- |
| Change log triggers | Any write to a subscribed table, including imports and direct SQL | Reservations, scheduled versions becoming active, core full reindexes that swap tables |
| Save and order observers | Admin saves, orders, refunds, cancellations, within seconds | Imports and direct SQL |
| Reservation sweep | Reservations placed by any path, including REST and imports | Nothing it's responsible for |
| Schedule runner | Special price and news dates, staged versions starting or ending | Nothing it's responsible for |
| Core reindex follower | `indexer:reindex` of Magento's price or stock index | |
| Hourly drift check | Anything the others missed, in a sample | Anything outside the sample until a later hour |

**A changed child refreshes its parent.** The parent's option rows and stock depend on its children, so refreshers widen every id set to its parents first.

**Super attributes are watched directly.** The product change log also subscribes to `catalog_product_super_attribute` and `catalog_product_super_attribute_label`. `SuperAttributeSubscription` resolves the entity id from the link field on Adobe Commerce. `SuperAttributeLabelSubscription` finds the product through the label's super attribute, on both editions.

So when a super attribute is added, removed or relabelled without a product save, the configurable's document is still refreshed and its cache tags are purged. That purge clears Magento's cached variants too.

**Nothing raised during checkout runs in that request.** Even in inline mode, order-driven refreshes are queued.

## Writing, versions and purges

`DocumentWriter` does three things for every batch:

1. **Reads the current fingerprints and categories** for the batch from the index it compares against, in one request.
2. **Writes with `version_type=external_gte`**, using a version taken from the clock before the data was read. OpenSearch refuses anything older than what it holds. The writer treats that refusal as "someone newer already wrote this", not as an error.
3. **Deletes removed documents** with the same version, then reports a `ChangeSet`: created, deleted, which field groups changed, and which categories the product joined or left.

`PurgePlanner` turns a `ChangeSet` into tags. `TagPurger` sends them in chunks through `clean_cache_by_tags` and the application cache. The tag rules are in the README under *Only what a shopper would see change is purged*.

**Purges run one at a time.** `TagPurger` takes a lock every server shares, using foundation's `LockRunner` over Magento's lock manager, and waits up to five seconds for it. A purge that can't get the lock parks its tags as rows in `kingletas_catalog_index_parked_purge`.

Whoever holds the lock next purges the parked rows after its own tags. A job that runs every minute does the same when nothing else is purging. Rows are released by id, so a tag parked while a flush is running is never deleted by that flush.

**Every OpenSearch request goes through Magento's own client.** `ClientGateway` builds `Magento\OpenSearch\Model\SearchClient` from the connection settings and calls the `opensearch-php` client it wraps. It passes a per-request timeout: the page read timeout for reads, and the write timeout for everything else. Any failure becomes a `DocumentStoreException`. A 404 is only tolerated where missing is a real answer, such as an alias that doesn't exist yet.

## Full rebuilds

```mermaid
sequenceDiagram
    participant R as FullRebuild
    participant L as Change log
    participant N as New index
    participant A as Alias
    R->>L: version v0
    R->>N: create
    loop every batch
        R->>N: build, compare against alias
    end
    R->>L: version v1, ids between v0 and v1
    R->>N: replay them
    R->>A: move alias to new index (one request)
    R->>L: ids between v1 and now
    R->>A: replay them into the live index
    R->>R: drop builds beyond the kept one, purge what differed
```

The mview cron keeps writing to the old index while the build runs. That's why the change log is replayed twice: once before the alias moves, for everything so far, and once after, for the seconds in between. A lock per alias stops two rebuilds of the same index. If something fails before the alias moves, the half-built index is dropped and the live one is left alone.

Purges during a rebuild compare the new documents with the ones they replace. A nightly rebuild that finds nothing different leaves the cache untouched. The behaviour suite holds both of those.

## Reading on the storefront

```mermaid
flowchart TD
    M[marker plugin marks the collection<br/>with its page] --> B{eav_collection_abstract_load_before}
    B -->|not marked, flat or switched off| DB[Magento loads every attribute]
    B -->|breaker open| DB
    B -->|allowed| S[remove the attributes documents supply<br/>from the select]
    S --> L[collection loads its entities<br/>and any attribute left selected]
    L --> A{catalog_product_collection_load_after}
    A --> F[one _mget: product, price, stock]
    F -->|store error| DB3[count store_error, load skipped attributes]
    F -->|any document missing| DB4[count missing_document, load skipped attributes]
    F -->|all present| H[fill every item, put the codes back on the select, count served]
```

**Listings are marked, not detected.** A collection reads documents only if a marker put it there. The markers are the layer's collection filter for category and search lists, `setPositionOrder` on link collections, the CMS widget's `createCollection`, and GraphQL collection processors. Cart items, checkout, admin grids and imports never pass through a marker, so they never read a document. Marks live in a `WeakMap` on a shared `PageScope`, not on the collection.

**No around plugin sits on the read path.** Before a marked collection loads, an observer on `eav_collection_abstract_load_before` asks `DocumentAttributeCodes` which of its selected attributes a document stores. It removes only those, with `removeAttributeToSelect()`.

The entity query still runs, so price, sort, pagination, filters and catalog permissions all come from Magento. Any attribute a document doesn't store, such as one a third-party module added, is still loaded by Magento.

An observer on `catalog_product_collection_load_after` puts the codes back and fills the items. If documents can't be used, it calls `_loadAttributes()` and `addMediaGalleryData()` itself, so the page ends up exactly as Magento would have loaded it.

**Product and category pages are scoped to their own entity.** `catalog_controller_product_init_before`, and `controller_action_predispatch_catalog_category_view` for categories, record the id the page is loading. `catalog_controller_product_init_after` and `catalog_controller_category_init_after` clear it.

The entity manager still loads the entity row. `ProductAttributeReader` and `CategoryAttributeReader` are registered in the `AttributePool` for the storefront area. They supply attribute values from the document only for that id, and never in edit mode. Every other load goes to Magento's own EAV read handler.

`ServedProductExtension` skips the gallery and custom option reads for a served product. The repository, its instance cache and every plugin on it run as they always do.

**Hydration never overwrites what the entity query loaded.** Indexed prices from the collection's own join stay as they are.

## Staying honest: fallbacks, drift and status

| Signal | Where it lives | When it speaks |
| --- | --- | --- |
| Fallbacks per page and reason | Application cache, hourly buckets | `status`, marked over budget above the configured share |
| Circuit breaker | Application cache | Opens after repeated failures, closes after the cooldown |
| Drift | Hourly sample | Logs a warning above the configured share, again only if it doubles or six hours pass |
| Backlog | Change log version against the view's processed version | `status` |
| Build | State table | `status` |

Nothing here writes to the database on a page view.

## Extending it

**To add a field to product documents,** write a class that implements `FieldProviderInterface`. Extend `AbstractFieldProvider` if it doesn't need batch state. Then add it to the `providers` argument of `ProductDocumentBuilder` in `di.xml`. Load everything in `prepareBatch`, and set each field with the group that matches what a change to it should purge. Add the provider to `BuildCostTest` so the budget covers it.

**To serve a new page type,** add a case to `PageType`, a switch to `system.xml` and `config.xml`, a method to `Config` and a line in `ReadGate`. Then add a marker that calls `PageScope::mark()` on the collection the page loads. Markers are `before` or `after` plugins, or collection processors. They never wrap the call.

**To use another stock source,** implement `StockReaderInterface` and put it first in `StockReaderPool`'s `readers`.
