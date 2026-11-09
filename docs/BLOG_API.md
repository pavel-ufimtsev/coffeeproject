# Blog API for AI agents

This JSON API gives an agent full control over the blog at `https://www.coffeeproject.ru/api/blog/v1`. People do the same work in the admin. The admin and the API share the same rules: every text change is saved as a revision, a live post that moves keeps its old address working, and nothing touches the shop's catalog or orders.

The machine-readable description is at `GET /api/blog/v1/schema` (OpenAPI 3.1).

## Authentication

Every request sends a bearer token:

```
Authorization: Bearer 3|kq8...
Accept: application/json
```

Tokens belong to an **agent** (e.g. `blog-editor`), never to a person. Manage them on the server:

```bash
php artisan api:agent-token blog-editor --name=triage   # issue a token; it is printed once
php artisan api:agent-token --list                      # agents, token ids, last used
php artisan api:agent-token --revoke=3                  # revoke token 3
```

Each token's name is recorded on everything it changes (`agent: blog-editor (triage)`). To stop an agent at once, set `is_active` to false on its `api_agents` row. Its tokens then get 403.

| Answer | Meaning |
|---|---|
| 401 | No token or an unknown token |
| 403 | The agent is switched off, or the token lacks the `blog:*` ability |
| 404 | No such post, image, section or tag |
| 409 | Not allowed in the current state (e.g. deleting an image a post uses) |
| 422 | Validation failed; `errors` lists messages per field |
| 429 | Over 120 requests a minute for this token; wait and retry |

## Posts

A post is addressed by its **id or slug**: `/posts/412` and `/posts/barista_declension` are the same post.

### Statuses

| Status | On the site? |
|---|---|
| `draft` | No. Only admins can preview it. |
| `scheduled` | Goes live by itself when `published_at` arrives |
| `published` | Yes |
| `archived` | No. Retired, usually with a redirect to a better page. |

`published_at` is the date readers see. Archive posts keep their original date (e.g. 2018) when published. A post edited more than a day after publishing also shows "Updated {date}".

### List: `GET /posts`

Filters: `status`, `category` (section slug, or `none`), `tag`, `source` (`archive`, `admin`, `agent`), `search` (title, slug or body), `triage` (`publish`, `refresh`, `merge`, `retire`, `none` = not triaged yet, `any`), `updated_since`, `trashed=1`, `sort` (`-published_at` by default, also `published_at`, `updated_at`, `-updated_at`, `slug`, `id`), `per_page` (up to 200), `page`.

```bash
curl -H "Authorization: Bearer $TOKEN" \
  "https://www.coffeeproject.ru/api/blog/v1/posts?source=archive&triage=none&per_page=20"
```

```json
{
  "data": [
    {
      "id": 412, "slug": "barista_declension", "url": "https://www.coffeeproject.ru/blog/barista_declension",
      "title": "Как склонять слово бариста", "excerpt": "…", "status": "draft", "is_visible": false,
      "published_at": "2019-03-01T10:00:00+05:00", "edited_at": null, "updated_at": "…",
      "category": "kofe-profi", "tags": ["бариста"], "source": "archive", "words": 512, "reading_minutes": 3,
      "cover": null, "legacy_url": "/blog/barista_declension", "images_missing": 2,
      "triage": { "decision": null, "confidence": null, "reason": null, "merge_into": null, "triaged_at": null },
      "deleted_at": null
    }
  ],
  "links": { "next": "…?page=2" },
  "meta": { "current_page": 1, "last_page": 19, "total": 376 }
}
```

### Read one: `GET /posts/{post}`

This returns everything in the list, plus:

| Field | What it holds |
|---|---|
| `body` | The Markdown text |
| `html` | The text as rendered on the site |
| `seo_title`, `seo_description`, `canonical_url`, `noindex`, `author_name` | SEO and author fields |
| `products` | Slugs of products shown under the article |
| `images` | Every image the post uses |
| `revisions_count` | How many revisions the post has |
| `import` | Facts about restored posts, described below |

The `import` object holds these facts for posts restored from the old site:

| Fact | What it holds |
|---|---|
| `words` | Word count |
| `images_found`, `images_missing`, `missing_images` | Which images came back and which are lost |
| `old_category` | The old WordPress section |
| `old_tags` | The old tags |
| `links` | Outside links in the text, to check for dead ones |
| `modified_at` | When the old blog last changed the post |
| `suggested_action` | The importer's first guess |

### Create: `POST /posts`

`title` and `body` are required. The slug comes from the title unless you send one. A new post is a draft unless you send a `status`.

```json
{
  "title": "Раф с лавандой",
  "body": "Нежный раф с лавандовым сиропом.\n\n## Что понадобится\n\n- эспрессо 30 мл\n- сливки 10% 120 мл",
  "excerpt": "Сливочный раф с лавандой: рецепт на одну чашку.",
  "category": "recepty-kofe",
  "tags": ["раф", "рецепт"],
  "products": ["brazil-fine-cup"],
  "note": "New recipe"
}
```

The answer is `201` with the full post.

### Change: `PATCH /posts/{post}`

Send only the fields you change.

| Field | Notes |
|---|---|
| `slug` | Letters, digits, `_` and `-`. Changing the slug of a live post redirects the old address. |
| `title`, `excerpt`, `body` | The text. `body` is Markdown. |
| `category` | Section slug, or `null` |
| `cover_image_id` | An image id from `/images` |
| `status` | `draft`, `scheduled`, `published` or `archived` |
| `published_at` | ISO date. A future date with `published` means scheduled. |
| `redirect_to` | With `status: archived`: where the old address should lead |
| `seo_title`, `seo_description` | Page title and search snippet (up to 250 and 500 characters) |
| `canonical_url`, `noindex` | Rarely needed |
| `tags` | A list of tag names. It replaces the current list. |
| `products` | A list of product slugs. It replaces the current list. |
| `triage_decision`, `triage_confidence` (0-100), `triage_reason`, `triage_merge_into` (slug) | Your editorial verdict. Setting any of them stamps `triaged_at`. |
| `note` | Why you changed it. Saved on the revision. |

Text changes (`title`, `excerpt`, `body`, `seo_title`, `seo_description`) create a revision. Triage fields alone do not.

### Publish, unpublish, retire, delete

| Request | Body | Effect |
|---|---|---|
| `POST /posts/{post}/publish` | optional `published_at` | Goes live, keeping its date. Old addresses of the post (e.g. `/brew/cezve` for a brewing guide) now redirect here. A redirect away from the post's own address is removed. |
| `POST /posts/{post}/unpublish` | none | Back to draft; the address answers 404 |
| `POST /posts/{post}/archive` | `redirect_to`, `note` | Retired. Its address (and its old addresses) redirect to `redirect_to`, which must start with `/`, e.g. `/blog/c/o-kofe` or `/blog/other-post`. |
| `DELETE /posts/{post}` | optional `redirect_to` | Moved to the bin (`?trashed=1` lists it) |

Prefer **archive with a redirect** over delete. Search engines and old links then land somewhere useful.

### Revisions

| Request | What it does |
|---|---|
| `GET /posts/{post}/revisions` | Lists revisions, newest first: id, author, note, title, time |
| `GET /posts/{post}/revisions/{id}` | Returns one revision with its full text |
| `POST /posts/{post}/revisions/{id}/restore` | Brings that text back as a new revision |

## Markdown conventions

The site renders GitHub-flavoured Markdown: headings, lists, tables, links, images and quotes.

- **Headings:** start sections at `##` (the page title is the only `#`). Articles with three or more `##` sections get a contents box automatically.
- **Images:** `![Alt text describing the photo](/storage/blog/2026/11/abc.webp)`. Upload first, then paste the `markdown` value the image answer gives you.
- **YouTube video:** put `::youtube[VIDEO_ID]` on its own line.
- **Product card:** put `::product[product-slug]` on its own line. Find slugs with `GET /products`. A product that is no longer on sale simply disappears from the page.
- **Links:** use `/coffee/...` or `/blog/...` for the site's own pages, full URLs for others.
- **Raw HTML is stripped.** Write Markdown only.

## Images

| Request | What it does |
|---|---|
| `POST /images` (multipart) | Uploads `file` (JPEG, PNG, GIF or WebP, up to 15 MB), with optional `alt`, `caption`, `credit` |
| `POST /images` (JSON) | Fetches the picture from `source_url`, a public http(s) address. Addresses on private networks are refused. Same optional fields. |
| `GET /images` | Lists the library; filter with `search` or `unused=1` |
| `GET /images/{id}` | Returns one image |
| `PATCH /images/{id}` | Changes `alt`, `caption` or `credit` |
| `DELETE /images/{id}` | Deletes an image. Answers 409 while a post uses it. |

Images are stored as WebP: 1600 px wide for articles, plus a 640 px thumbnail for cards.

```json
{
  "data": {
    "id": 88, "url": "https://www.coffeeproject.ru/storage/blog/2026/11/k3j2.webp", "path": "/storage/blog/2026/11/k3j2.webp",
    "thumb_url": "…-thumb.webp", "width": 1600, "height": 1067,
    "alt": "Турка на горячем песке", "caption": null, "credit": null, "uploaded_by": "agent: blog-editor (triage)",
    "markdown": "![Турка на горячем песке](/storage/blog/2026/11/k3j2.webp)"
  }
}
```

## Sections, tags, products and redirects

| Request | What it does |
|---|---|
| `GET /categories` | Sections with post counts and visible post counts |
| `POST /categories`, `PATCH /categories/{id}` | Creates or changes a section: `slug`, `name`, `description`, `seo_title`, `seo_description`, `sort`, `is_active`. A new slug redirects the old address. |
| `DELETE /categories/{id}` | Deletes a section. Only empty sections can be deleted (409 otherwise). |
| `GET /tags`, `POST /tags`, `PATCH /tags/{id}`, `DELETE /tags/{id}` | Manages internal tags. Tags have no public pages; they choose "related posts". |
| `GET /products?search=` | Lists products on sale with their `::product[...]` line. `archived=1` includes retired products. |
| `GET /redirects?search=` | Lists site redirects |
| `POST /redirects` | Redirects `from` to `to`. Both start with `/`. |
| `DELETE /redirects/{id}` | Removes a redirect |

## How an agent should update an article

1. `GET /posts/{slug}` and read `body`, `import` and `triage`.
2. Edit the Markdown. Keep the author's voice and facts. Fix the wording that breaks the brand rules below, dead links, and references to missing images.
3. `PATCH /posts/{slug}` with the new `body` (plus `excerpt`, `seo_title` and `seo_description` if they need it) and a short `note` saying what changed and why.
4. Publish with `POST /posts/{slug}/publish`, or retire with `POST /posts/{slug}/archive` and a `redirect_to`.
5. If something went wrong, `GET /posts/{slug}/revisions` and restore the last good one.

Brand rules for all text: Coffee Project started with its own coffee shops and roastery (2012–2018). Today it sells coffee roasted to order in Ekaterinburg with delivery across Russia. Never say it owns a roastery or coffee shops today. Never mention white-label suppliers. The public founder is Alexandra Ufimtseva.
