# Catalog rebuild plan

How we restore the coffeeproject.ru catalog after losing the old database, connect it to retailCRM, and then make it manageable from the admin.

## Where we start

- The old site's database is gone. What survives:
  - `../coffeeproject_yii/coffeeproject`: the 2018 Yii2 code, including URL rules, the catalog controller and ~750 product photos.
  - `../coffeeproject_yii/CoffeeProjectWebArchive`: a web archive of the site from 2026, including the `/coffee`, `/chaj` and `/accessory` listings (May–June 2026), ~490 product pages and ~540 product photos.
- retailCRM (`coffeeproject-ru` store) has 587 products, 269 flagged active. It holds names, variants (offers), prices and a "before-sale" price. Its descriptions and characteristics are mostly empty, and it does not track stock. 583 of the 587 products were entered by hand, so retailCRM is the system of record for anything that can be sold.
- The site actually sold 40 products: 23 coffees, 5 drip packs and sets, 7 teas and 5 guides and maps. 34 of them are matched to a retailCRM product. The mixed drip set is probably retailCRM #704. The guides are not in retailCRM.

## Decisions this plan builds on

- **One national catalog.** No city in category or product URLs. Ekaterinburg is the home region. Moscow and St. Petersburg are reached through delivery pages, roaster pages and Yandex region settings (see the project's SEO notes).
- **Old URLs keep working.** Product slugs stay the same. Old city pages and filter pages get 301 redirects.
- **Content lives on the site; sellable items live in retailCRM.** Our database owns categories, filters, texts, specs, photos and SEO. retailCRM owns which variants exist, their prices and, later, stock. Each product and variant stores its retailCRM ID.
- **Roasters and ship-from locations are in the data model from the start**, seeded with Ekaterinburg only. The "ships from" filter appears once a second city has products.
- **The model is generic across product types.** Coffee, tea, accessories, drip packs, gift certificates and guides all use the same tables. Product types differ in their spec fields, options and filters.

## Data model

### Product types

A fixed list in code, not a table. Each type defines its spec fields, purchase options and filter groups.

| Type | Spec fields | Options at purchase | Example filter groups |
|---|---|---|---|
| `coffee` | country, region, farm, altitude, harvest, process, varieties, SCA score, roast level, tasting notes, roaster's notes | grind (8 choices, no price change) | brew method, origin, taste, collection, roaster, ships from |
| `drip` | as coffee, plus pieces per box | — | origin, collection |
| `tea` | tea type, origin, harvest, brewing instructions | — | tea type |
| `accessory` | brand, material, volume, compatibility | — | accessory type, brand |
| `certificate` | face value | — | — |
| `guide` | format (print / PDF) | — | — |

### Tables

| Table | Purpose | Key columns |
|---|---|---|
| `categories` | Navigation tree. Roots are `coffee`, `chaj` and `accessory`, which are also URL prefixes. Children are types such as black tea or brewers. | `parent_id`, `slug`, `name`, `product_type`, `h1`, `intro`, `seo_title`, `seo_description`, `sort`, `is_active` |
| `filter_groups` | A dimension shoppers filter by: brew method, origin, taste, tea type… | `slug`, `name`, `product_type`, `sort`, `is_visible` |
| `filter_values` | One option in a group: espresso, africa, kenya, berry… Can be its own landing page. | `filter_group_id`, `slug`, `name`, `parent_id` (kenya → africa), `is_landing`, `h1`, `intro`, `seo_title`, `seo_description`, `sort` |
| `product_filter_value` | Tags a product with filter values (many per product). | `product_id`, `filter_value_id` |
| `collections` | Editorial groups with manual order: unique, aromatic, strong, decaf, robusta, sale, sets. Also landing pages. | `slug`, `name`, `is_landing`, texts, SEO, `sort` |
| `collection_product` | Membership and order. | `collection_id`, `product_id`, `sort` |
| `roasters` | Coffee Project, Ingresso, future partners. | `slug`, `name`, `city`, `story`, `logo`, `website`, `is_partner`, `is_active` |
| `locations` | Where orders ship from. | `roaster_id`, `name`, `city`, `address`, `retailcrm_store_code`, `roast_days`, `dispatch_cutoff`, `is_active` |
| `products` | One sellable product page. | `category_id`, `roaster_id`, `product_type`, `slug`, `name`, `tagline`, `summary`, `description`, `specs` (JSON, validated per type), `status` (draft / active / archived), `is_featured`, `sort`, SEO fields, `retailcrm_product_id`, `retailcrm_name`, `retailcrm_active` |
| `product_variants` | The sizes or versions sold on the site, each mapped to one retailCRM offer. | `product_id`, `label` (250 г), `weight_g`, `retailcrm_offer_id` (unique), `price`, `old_price`, `is_active`, `sort`, `synced_at` |
| `location_variant` | Which locations a variant ships from. | `location_id`, `product_variant_id` |
| `product_images` | Photos in order. | `product_id`, `path`, `alt`, `sort`, `source` (archive / 2018 / retailCRM / upload) |
| `redirects` | 301s for old URLs. | `from_path` (unique), `to_path`, `status_code`, `hits`, `last_hit_at` |
| `retailcrm_sync_runs` | History of catalog syncs and what changed. | `started_at`, `finished_at`, `changes` (JSON), `errors` |

Notes:
- Prices are cached copies of retailCRM prices, refreshed by the sync. The site never edits them.
- A variant that exists in retailCRM but is not linked (samples, 125 g, "без этикетки", Moscow copies) does not appear on the site. That is how the hidden variants disappear without touching retailCRM.
- Grind is an option passed with the order line, not a variant. It goes to retailCRM as a line property.

## Connecting to retailCRM

1. **Link:** each product stores `retailcrm_product_id`, and each variant stores `retailcrm_offer_id`. The restore links the 34 known matches automatically. The admin (phase 2) lets you search retailCRM and attach the right product and offers.
2. **Sync** (`php artisan retailcrm:sync-catalog`, run by the scheduler every 30 minutes, plus a button in the admin):
   - reads the linked products and offers from retailCRM
   - updates price, old price and retailCRM's active flag and name on our side
   - flags problems for the admin to review: an offer deleted or switched off in retailCRM, a price of 0, a linked product without active offers
   - never writes to retailCRM
3. **Orders (phase 3):** orders go to retailCRM with the variant's `retailcrm_offer_id`, the grind as a line property, and the ship-from location's `retailcrm_store_code`.
4. **Later (decision 7):** if we want the site to become the place where products are created, we expose a catalog feed in retailCRM's ICML format and retailCRM imports it on a schedule. Not needed now.

## URLs and SEO

| URL | Page |
|---|---|
| `/coffee`, `/chaj`, `/accessory` | Category root: all active products, filters, intro text |
| `/chaj/{type}`, `/accessory/{type}` | Child category |
| `/coffee/{filter}` | Filter or collection landing page, only for values marked `is_landing` |
| `/{root}/{product-slug}` | Product page (same slugs as before) |
| `/roasters`, `/roasters/{slug}` | Roaster list and roaster pages (phase 4) |
| `/help/delivery`, `/help/delivery/{city}` | Delivery pages (phase 3) |

- A request to `/coffee/{x}` checks landing-page slugs first, then products. The admin refuses a product slug that equals a filter slug and the other way around.
- Indexed: category roots, child categories, landing pages and products.
- Not indexed: any combination of filters chosen on the page. These use query parameters such as `?brew=espresso&origin=kenya`, are marked noindex and point their canonical at the page without parameters.
- A landing page with fewer than 3 active products is automatically marked noindex.
- Every page gets a unique title, description and H1 (with a template for products), JSON-LD (Product / Offer in RUB, BreadcrumbList, Organization with the Ekaterinburg address), `sitemap.xml`, and a YML feed for Yandex Products.

### Redirects generated from the archive

| Old URL | Goes to |
|---|---|
| `/{city}/coffee`, `/{city}/chaj`, `/{city}/accessory` (11 cities) | `/coffee`, `/chaj`, `/accessory` |
| `/{city}/coffee/{filter}` | `/coffee/{filter}` if it is a landing page, otherwise `/coffee` |
| `/{city}/chaj/{x}`, `/{city}/accessory/{x}` | the matching category |
| Archived product pages (~450) | the product's category, or a named substitute (e.g. an old Kenya lot to the current Kenya coffee) |
| `/coffee?roasters[]=…`, `?countries[]=…` | the new filter parameters |
| `/store-*` pages (2017 era) | `/coffee` |

## Progress

- **Phase 1: done** (2026-10-26). 98 products restored: 35 active (23 coffees, 5 drip packs and sets, 7 teas) and 63 archived teas and accessories. Guides are skipped until there is content for them. All products are linked to retailCRM; 659 redirects cover the old site's URLs. Commands: `make catalog-import`, `make crm-sync`.
- **Phase 2:** next.

## Phases

### Phase 1: Restore the catalog (data, import, storefront)

**1a. Data model**
- Migrations and models for the tables above, product type definitions with spec validation, factories and tests.
- Seed the fixed structure: the 3 roots, child categories, filter groups and values, collections, 2 roasters (Coffee Project, Ingresso), 1 location (Ekaterinburg, store `coffeeproject-ru`).

**1b. Restore data (reviewable, repeatable)**
- A one-off script read the archived listings and product pages and wrote `src/database/data/catalog/` (`products.json`, `redirects.json`, `images/`): one entry per product with name, slug, tagline, texts, specs, tasting notes, roaster, photos, retailCRM IDs and the proposed category, filters and collections. The archive is not on the servers, so the output is committed and the app only reads it.
- `php artisan catalog:import` loads that file into the database. It can be run again safely: it updates existing records by slug.
- Photos are copied from the archive (falling back to the 2018 folder, then retailCRM), resized to web sizes and stored under `storage/app/public/products`.
- `php artisan catalog:import-redirects` builds the `redirects` table from the archive URL list.
- First `retailcrm:sync-catalog` run fills prices and checks every link.

**1c. Storefront**
- Category, landing and product pages in Blade with the shop CSS. Filters work without JavaScript (links and query parameters) and get a small JS enhancement.
- Product page: photos, variant selector (250 г / 1 кг), grind selector, specs, tasting notes, roaster's notes, roaster and "ships from" line.
- Redirect middleware, sitemap, JSON-LD, YML feed.
- "Add to cart" can stay hidden or show "скоро" until phase 3.

**Done when:** the 40 products are live with photos and current prices, every archived URL either works or redirects, and the tests cover routing, filtering, landing-page rules and the sync.

### Phase 2: Admin for the catalog

Inertia pages under `/admin`:
- **Products:** list with search and filters (type, category, status, "needs attention"); edit content, specs (form fields depend on the type), category, filters, collections, roaster, SEO, photos (upload, reorder, alt text) and status; preview; link to retailCRM by searching its products and picking the offers that become variants.
- **Categories:** tree with drag-to-reorder, texts and SEO.
- **Filters:** groups and values, landing page on or off, landing texts and SEO, product count per value.
- **Collections:** membership and manual order.
- **Roasters and locations.**
- **retailCRM sync:** "sync now", last run, list of problems, unlinked active retailCRM products (so new items in retailCRM are noticed).
- **Redirects:** list, add and edit, with hit counts so dead redirects can be cleaned up.
- **Permissions:** for now every admin can do everything. A separate "content editor" role can come later if needed.

**Done when:** a new coffee can be added end to end in the admin (create it in retailCRM, link it, fill in content, publish) without touching code.

### Phase 3: Cart, checkout and orders to retailCRM

Cart and checkout (Blade, with a Vue island for delivery choice), guest checkout, orders sent to retailCRM with offer IDs, grind and the ship-from location's store code. Delivery and payment types use the codes already set up in retailCRM. Order status updates are pulled back from retailCRM. Delivery pages for Ekaterinburg, Moscow, St. Petersburg and the rest of Russia.

### Phase 4: Partners and more cities

Roaster pages, partner locations and their retailCRM warehouses, the "ships from" filter, a delivery estimate per visitor's city, and splitting a cart across locations.

## Decisions needed

These affect phase 1 unless noted.

1. **Archived accessories and teas.** About 30 items are still active in retailCRM and have archived pages (Hario V60, filters, Bool-Bool cups, books, grinders, gift certificates, more teas). Restore any of them as active products, or import them as archived so their URLs keep working?
2. **The 5 guides and maps.** Paid products, free downloads or blog content? If they are sold, they need retailCRM products.
3. **Taste filters.** Approve the taste tags proposed from each coffee's tasting notes, or leave taste out of phase 1.
4. **Roaster filter.** Keep it with 2 roasters, or wait until a partner joins?
5. **Mixed drip set.** Confirm it is retailCRM #704 "Набор дрип кофе — ассорти из 8 сортов кофе" (790 ₽ / 1 280 ₽).
6. **Bundles and sale items.** retailCRM has "РАСПРОДАЖА - …" copies of normal products. On the new site, sale is a collection plus an old price on the normal product. Can those copies be retired in retailCRM?
7. **Where products are created long term.** Keep creating them in retailCRM and linking them (recommended for now), or later make the site the source and feed retailCRM through ICML.
8. **Phase 3:** real delivery options and times for Moscow and St. Petersburg, and whether there is any physical point in Moscow.
