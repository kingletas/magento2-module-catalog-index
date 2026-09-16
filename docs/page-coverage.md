# Page coverage

Which storefront surfaces read documents, on which Magento versions and editions, and what is left to the database on purpose.

## Contents

- [Status key](#status-key)
- [Storefront pages](#storefront-pages)
- [Editions and versions](#editions-and-versions)
- [Themes and front ends](#themes-and-front-ends)
- [Deliberately left to the database](#deliberately-left-to-the-database)
- [Planned](#planned)

## Status key

| Mark | Meaning |
| --- | --- |
| Served | Reads documents when switched on, covered by the suites |
| Partial | Reads documents for part of what the page loads |
| Database | Always reads MySQL, by design |
| Planned | Not built yet |

The module has run on Magento Open Source 2.4.8-p2 and Adobe Commerce 2.4.8-p2 in production mode. Which surfaces each store exercised is in *What has been proved, and where* in the README.

## Storefront pages

| Surface | Status | What reads documents | What still reads MySQL |
| --- | --- | --- | --- |
| Category product list | Served | Every attribute, URL, gallery, review summary, salability | Entity query: filters, sort, price join, pagination, permissions |
| Search result list | Served | As above | As above, plus the search request itself |
| Layered navigation | Partial | Each category's attributes and product count | Facets come from Magento's own search request |
| Product page, simple or virtual | Served | The whole product | Stock item extension attribute, catalog rule price resolution |
| Product page, configurable | Partial | The parent product, its swatch option rows and its super attribute rows when switched on | Its variants, which Magento caches across requests; salability |
| Product page, bundle, grouped, downloadable, gift card | Database | Nothing | Everything, because their options live outside the document |
| Product page with custom options | Database | Nothing | Everything, for the same reason |
| Category page | Served | The category, and every category its breadcrumb and children lists load | CMS block, product list (see above) |
| Related, up-sell, cross-sell | Served | As category lists | As category lists |
| Cart cross-sell block | Served | As category lists | As category lists |
| CMS product list widget | Served | As category lists | As category lists |
| GraphQL `products` | Partial | Attributes the collection would have loaded | Resolvers that load their own data, such as `price_range` and `media_gallery` |
| GraphQL `categoryList` | Planned | | |
| Top menu | Served | Every category's attributes, URL and product count | Magento caches the rendered block, so this is the cost of building it |

## Editions and versions

| | 2.4.8 | 2.4.9 |
| --- | --- | --- |
| Magento Open Source | Run on a store | Source read, suites run |
| Adobe Commerce | Run on a store | Expected |
| Mage-OS, on the same framework | Expected | Expected |

**Run on a store** means installed, rebuilt and served pages on 2.4.8-p2 in production mode. **Source read** means the hooks, signatures and trigger shape the module relies on were checked in that release's code: Open Source 2.4.9 (framework 103.0.9), where the suites run, and Adobe Commerce 2.4.8-p2, for the staging trigger. **Expected** means the same classes and methods exist in Magento's public history for that line and nothing suggests they changed, but nobody has opened that release's code or run the module on it. 2.4.6 and 2.4.7 are not supported: the module needs PHP 8.4, and Magento supports it from 2.4.8.

| Feature | Behaviour |
| --- | --- |
| Content staging | Detected. Triggers resolve `row_id`; scheduled versions are scanned when **Watch Scheduled Updates** allows |
| Multi-source inventory | Detected. Salable quantity reads the stock index table and subtracts reservations; without it the legacy stock status is read |
| Catalog permissions | Every event it listens to still fires: the listing collection's load events, `catalog_controller_product_init_after`, `catalog_controller_category_init_after`, and `catalog_product_is_salable_after`, because hydration never sets `salable` itself. Read from Adobe Commerce 2.4.8-p2's event configuration, not yet run |
| B2B shared catalog | Not verified. Leave product pages switched off on a B2B store until checked |
| OpenSearch | Required, through `Magento_OpenSearch` and the client it builds. A store on Elasticsearch 8 would need a second gateway, which is not written |
| RabbitMQ | Used when it is the store's default queue connection; the database queue otherwise |
| Varnish, built-in page cache | Purged through `clean_cache_by_tags`, which both listen to |
| Fastly | Its extension is expected to listen to the same event; not checked |

## Themes and front ends

The module changes no template. It fills the same `Product` and `Category` objects Magento would, so any theme that renders from them benefits.

| Front end | Notes |
| --- | --- |
| Luma and Luma children | Listing, product and category pages |
| Hyvä | Same collections and repositories; swatch rendering should be checked before switching on configurable options |
| PWA Studio and other headless | Through GraphQL `products` |

## Deliberately left to the database

| Surface | Why |
| --- | --- |
| Add to cart, cart, checkout, order placement | They decide money and stock |
| Admin grids and forms | An administrator must see what is stored, not a copy |
| REST catalog API | Integrations write back what they read |
| Imports and exports | They are the source documents are built from |
| Sitemaps and feeds | Run from cron, where a page cache gains nothing and a stale URL costs rankings |

## Planned

- GraphQL `categoryList` and `categories` from category documents
- Listing results, filters and facets queried from the product index directly, so a listing makes no MySQL query at all
- An integration suite that runs the read paths against a real store
- **The salability count a configurable listing runs, one per product.** It is the largest single piece of database work left on that page. It is not answered here, because the only seam that keeps the events catalog permissions depends on is an around plugin on `Configurable::isSalable`.
