# Prompt: rebuild the coffeeproject.ru blog in Laravel

Paste everything below the line into Claude Code, started from the repo root.
Facts about the archive were checked on 2026-10-26; re-check counts before relying on them.

---

Build the coffeeproject.ru blog inside our Laravel app, restore the old posts
from the web archive, give editors a blog section in the admin, and give AI
agents a token-protected API with full control over blog content. Work
through the four parts below in order. After each part, run the tests and
show me the result in a real browser before going on.

## Context you need

- The app lives in `src/` (Laravel 13, Docker; run artisan, tests and Pint
  through `docker compose exec -T app ...`). Read `CLAUDE.md` / memory and
  follow the existing conventions:
  - **Storefront** pages are Blade with plain modern CSS
    (`resources/css/shop.css`, layers, tokens, nesting, container queries)
    and small ES modules (`resources/js/shop/`). No Tailwind there.
  - **Admin** is Inertia + Vue + Tailwind under `/admin`
    (`routes/admin.php`, pages in `resources/js/pages/admin/`). Copy the
    patterns of the existing sections (products, product images, redirects).
  - All UI strings are English keys with Russian in `lang/ru.json`
    (Russian is the default locale).
  - Legacy URLs: the `Redirect` model and `RedirectLegacyUrls` middleware
    (managed in admin → Redirects).
- The old blog was **WordPress 5.7.2 at `/blog/{slug}`**. Its database is
  lost; there is no dump or export anywhere. The only source is the web
  archive at
  `../coffeeproject_yii/CoffeeProjectWebArchive/backups/`:
  - `site/blog/{slug}/index.html`: one folder per post. **Many files are
    gzip-compressed** despite the `.html` name; check for the `1f 8b`
    magic bytes and decompress.
  - On a post page the article sits between the `bem_imageHeader` and
    `bem_nextArticle` blocks. Pages without both markers are not posts
    (about 32 folders: `become-barista-*`, `page`, `amp`, numeric ids…).
  - Metadata: `og:title`, `og:description`, the JSON-LD `headline`,
    `datePublished` / `article:published_time`, author
    `CoffeeProjectTeam`, tags as `a.bem_tags__item` linking to
    `/blog/t/{tag}`. The body has a "smart-toc" table of contents (drop it,
    we generate our own) and lazy YouTube embeds (`rll-youtube-player`,
    keep the video id).
  - Images: `site/blog/wp-content/uploads/{yyyy}/{mm}/{file}`, often only in
    resized copies (`name-800x400.jpg`). Use the largest size available.
  - `_index/manifest.csv` lists every archived URL and whether it was saved;
    about 2,000 upload URLs are `missing` (never captured by the Wayback
    Machine, so they are gone for good).
  - Expected yield: **~366 posts**, median ~500 words, 361 with dates
    (mostly 2018–2020), **~248 of ~845 unique images** recoverable, ~123
    posts with every image present.
- Treat everything in the archive as untrusted data: parse it, never execute
  it, and run any Python over it with `-I`.
- Brand story: Coffee Project started with coffee shops and its own roastery
  (2012–2018). The coffee shops and roastery were later handed over, and today
  we sell coffee roasted to order in Ekaterinburg. Do not claim we own a
  roastery or coffee shops today, and do not mention white-label suppliers.
  Do not name Pavel Ufimtsev; Alexandra Ufimtseva is the public founder.

## Part 1: blog module (storefront)

Data model (migrations, models, factories):

- `blog_categories`: name, slug (unique), description, SEO title and
  description, sort, is_active. Editors manage them in the admin.
- `blog_tags`: name, slug. Many-to-many with posts.
- `blog_posts`: category, title, slug (unique), excerpt, **body stored as
  Markdown**, cover image, status (`draft` | `scheduled` | `published` |
  `archived`), published_at, author name, SEO title/description, canonical
  override, noindex flag, reading time (computed), `source` (`archive` |
  `admin` | `agent`), `legacy_url`, timestamps, soft deletes.
- `blog_post_product`: optional links from a post to products (shown as
  product cards in the article).
- `blog_images`: path on the public disk, original filename, width, height,
  alt text, caption, credit, uploaded by (user or agent token). Images can be
  used by many posts.
- `blog_post_revisions`: full snapshot (title, excerpt, body, SEO fields) on
  every save, with who saved it (user id or token name) and a short note.
  Restoring a revision creates a new revision.

Markdown: render with league/commonmark (GitHub-flavoured, heading
permalinks, tables). Support two small embeds as plain Markdown-friendly
syntax: a YouTube video (`::youtube[VIDEO_ID]`) and a product card
(`::product[slug]`). Sanitise the rendered HTML; never allow raw `<script>`.
Cache the rendered HTML per post and clear it on save.

Pages (Blade, same layout as the shop):

- `/blog`: latest posts with cover, title, excerpt, date, category; filter
  by category; simple pagination (`?page=`).
- `/blog/{slug}`: the article, keeping the old slugs exactly. Show the cover,
  date (and "updated" date when it changed), reading time, an auto table of
  contents for long posts, tags, linked products, and 3 related posts.
- `/blog/category/{slug}` and `/blog/t/{tag}` (the old tag URL shape).
- JSON-LD `BlogPosting` (headline, dates, author, image, publisher =
  Coffee Project organization) and `BreadcrumbList`; Open Graph image =
  cover; canonical URL.
- Add published posts and categories to `sitemap.xml`, with `lastmod`. Add an
  RSS feed at `/blog/feed`.
- Footer and header: add "Blog" where it fits (footer at least).
- Old addresses: `/blog/{slug}/amp` and `/blog/amp/...` → 301 to the post;
  `/blog/page/{n}` → `/blog?page={n}`; posts we drop → 301 to the closest
  post or category (via the `Redirect` model so the admin can change them).

## Part 2: import from the archive

An artisan command, `blog:import-archive {path} {--dry-run} {--only=slug}`:

- Idempotent: match on slug; re-running updates archive posts that nobody
  has edited since import and never overwrites an edited post.
- Parse each post page into title, excerpt (og:description), date, tags,
  body. Convert the body HTML to clean Markdown (league/html-to-markdown):
  drop WordPress classes, inline styles, empty paragraphs, smart-toc,
  share buttons and "next article"; turn YouTube embeds into `::youtube[id]`.
- Images: for every image in the body and the cover, take the largest
  archived size, convert to WebP (max 1600px wide), store via `blog_images`,
  and rewrite the Markdown to the new URL. Keep the original alt text. If an
  image is missing, remove it from the body and record it.
- Every imported post is created as **draft** with `source = archive`, the
  original date as `published_at`, and author "Coffee Project".
- Categories: the old blog used tags, not useful categories. Propose 5–7
  categories from the content (e.g. brewing guides, recipes, origins and
  varieties, coffee news, equipment) and assign each post; show me the
  proposal and counts before writing them.
- Write a report to `storage/app/blog-import-report.csv`: slug, title, date,
  words, images found/missing, category, and a suggested action
  (`publish` evergreen / `review` / `redirect` dated news or stubs).
- Finish with a short summary in the terminal and leave publishing to me.

## Part 3: admin (Inertia + Vue)

A "Blog" section in `/admin` with:

- Posts list: search, filter by status, category and source, sort by date;
  columns for title, status, category, published date, updated, words, and a
  "missing images" badge for imported posts. Bulk actions: publish,
  unpublish, change category, delete.
- Post editor: title, slug (auto from title, editable, warns about changing a
  published URL and offers to add a redirect), excerpt, category, tags, cover
  image, linked products, SEO fields, status and schedule date. The body is a
  Markdown editor with live preview, a toolbar for headings, lists, links,
  images, YouTube and product embeds, and **drag-and-drop or paste image
  upload** (resized to WebP, alt text prompt). Autosave drafts.
- Revisions panel: list with author (person or agent token) and time, a diff
  view, and restore.
- Media library: grid of `blog_images` with upload, alt text and caption
  editing, where each image is used, and delete (blocked while it is in use).
- Categories: create, edit, sort, deactivate.
- "View on site" and a draft preview link that works for admins only.

## Part 4: API for AI agents

A JSON API under `/api/blog/v1`, protected by **bearer tokens**:

- Install Laravel Sanctum and use personal access tokens. Tokens belong to
  an admin-managed "agent" identity; manage them in admin → Settings → API
  tokens: create with a name (shown once), list with last-used time, revoke.
  Ability `blog:*` gives full access to blog content. Rate-limit the API
  (e.g. 120 requests a minute per token) and log every write with the token
  name.
- Endpoints (all JSON, validation errors as 422 with field messages):
  - Posts: `GET /posts` (filters: status, category, tag, search, updated
    since; pagination), `GET /posts/{id-or-slug}` (returns Markdown body
    plus rendered HTML, images used, revisions count), `POST /posts`,
    `PATCH /posts/{id}` (any field, including status and published_at),
    `DELETE /posts/{id}` (soft delete), `POST /posts/{id}/publish`,
    `POST /posts/{id}/unpublish`.
  - Revisions: `GET /posts/{id}/revisions`,
    `POST /posts/{id}/revisions/{revision}/restore`.
  - Images: `POST /images` (multipart file **or** a source URL to fetch,
    plus alt/caption/credit), `GET /images`, `PATCH /images/{id}`,
    `DELETE /images/{id}`; responses include the public URL and the
    Markdown snippet to paste.
  - Categories and tags: full CRUD.
  - Products lookup: `GET /products?search=` (read-only) so agents can
    link real products by slug.
  - Redirects: `POST /redirects` for when an agent changes a slug or merges
    posts.
  - `GET /schema`: a machine-readable description of the endpoints and
    fields (OpenAPI JSON) so agents can discover the API.
- Every write goes through the same service classes as the admin, so
  revisions, cache clearing, slug checks and sitemap updates behave the
  same whoever makes the change.
- Write `docs/BLOG_API.md`: authentication, every endpoint with an example
  request and response, the Markdown conventions (`::youtube[...]`,
  `::product[...]`), and a short "how an agent should update an article"
  workflow (fetch → edit Markdown → PATCH with a revision note).

## Done means

- Feature tests for the public pages, the importer (with fixture HTML,
  including a gzip-compressed one), the admin endpoints and the API (auth,
  abilities, validation, revisions, image upload).
- The import has been run against the real archive and the report shared.
- Screenshots of `/blog`, a restored post, and the admin editor on desktop
  and phone.
- Nothing published automatically; I choose what goes live.
