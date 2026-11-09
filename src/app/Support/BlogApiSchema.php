<?php

namespace App\Support;

/**
 * OpenAPI description of the blog API, served at GET /api/blog/v1/schema so
 * agents can discover endpoints and fields. Keep in step with routes/api.php
 * and docs/BLOG_API.md.
 */
class BlogApiSchema
{
    /**
     * @return array<string, mixed>
     */
    public static function openApi(): array
    {
        $id = ['name' => 'post', 'in' => 'path', 'required' => true, 'description' => 'Post id or slug', 'schema' => ['type' => 'string']];
        $num = fn (string $name) => ['name' => $name, 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']];
        $query = fn (string $name, string $description, string $type = 'string') => ['name' => $name, 'in' => 'query', 'description' => $description, 'schema' => ['type' => $type]];
        $body = fn (string $schema) => ['required' => true, 'content' => ['application/json' => ['schema' => ['$ref' => "#/components/schemas/{$schema}"]]]];
        $ok = fn (string $description) => ['200' => ['description' => $description]];

        return [
            'openapi' => '3.1.0',
            'info' => [
                'title' => 'Coffee Project blog API',
                'version' => '1',
                'description' => 'Full control over blog content for AI agents. Send "Authorization: Bearer <token>". '
                    .'Bodies are Markdown; embeds go on their own line: ::youtube[VIDEO_ID] and ::product[product-slug]. '
                    .'Every content change is saved as a revision with the token name. Validation errors answer 422 with field messages. '
                    .'Limit: 120 requests a minute per token. Guide: docs/BLOG_API.md.',
            ],
            'servers' => [['url' => url('/api/blog/v1')]],
            'security' => [['bearer' => []]],
            'paths' => [
                '/posts' => [
                    'get' => ['summary' => 'List posts, newest first', 'parameters' => [
                        $query('status', 'draft, scheduled, published or archived'),
                        $query('category', 'Section slug, or "none" for posts without one'),
                        $query('tag', 'Tag slug or name'),
                        $query('source', 'archive, admin or agent'),
                        $query('search', 'Words in the title, slug or body'),
                        $query('triage', 'publish, refresh, merge, retire, "none" (not triaged yet) or "any"'),
                        $query('updated_since', 'ISO date'),
                        $query('trashed', 'true to list deleted posts', 'boolean'),
                        $query('sort', 'published_at, -published_at (default), updated_at, -updated_at, slug, id'),
                        $query('per_page', '1-200, default 50', 'integer'),
                        $query('page', 'Page number', 'integer'),
                    ], 'responses' => $ok('Paginated posts (summary fields)')],
                    'post' => ['summary' => 'Create a post (a draft unless status is given)', 'requestBody' => $body('PostWrite'), 'responses' => ['201' => ['description' => 'The post']]],
                ],
                '/posts/{post}' => [
                    'get' => ['summary' => 'One post with Markdown body, rendered HTML, images, revisions count and import facts', 'parameters' => [$id], 'responses' => $ok('The post')],
                    'patch' => ['summary' => 'Change any fields, including status, triage and published_at', 'parameters' => [$id], 'requestBody' => $body('PostWrite'), 'responses' => $ok('The post')],
                    'delete' => ['summary' => 'Move to the bin; optional redirect_to', 'parameters' => [$id], 'responses' => $ok('Deleted')],
                ],
                '/posts/{post}/publish' => ['post' => ['summary' => 'Publish now, or schedule with a future published_at', 'parameters' => [$id], 'responses' => $ok('The post')]],
                '/posts/{post}/unpublish' => ['post' => ['summary' => 'Back to draft (off the site)', 'parameters' => [$id], 'responses' => $ok('The post')]],
                '/posts/{post}/archive' => ['post' => ['summary' => 'Retire: off the site, address redirects to redirect_to (e.g. /blog/c/o-kofe or another post)', 'parameters' => [$id], 'responses' => $ok('The post')]],
                '/posts/{post}/revisions' => ['get' => ['summary' => 'Revisions, newest first', 'parameters' => [$id], 'responses' => $ok('Revisions')]],
                '/posts/{post}/revisions/{revision}' => ['get' => ['summary' => 'One revision with its full text', 'parameters' => [$id, $num('revision')], 'responses' => $ok('Revision')]],
                '/posts/{post}/revisions/{revision}/restore' => ['post' => ['summary' => 'Bring back a revision as a new revision', 'parameters' => [$id, $num('revision')], 'responses' => $ok('The post')]],
                '/images' => [
                    'get' => ['summary' => 'Image library, newest first', 'parameters' => [$query('search', 'Alt text, caption or file name'), $query('unused', 'true for images no post uses', 'boolean')], 'responses' => $ok('Images')],
                    'post' => ['summary' => 'Upload (multipart "file") or fetch from "source_url"; stored as WebP; answer includes the Markdown line', 'requestBody' => ['content' => [
                        'multipart/form-data' => ['schema' => ['type' => 'object', 'properties' => ['file' => ['type' => 'string', 'format' => 'binary'], 'alt' => ['type' => 'string'], 'caption' => ['type' => 'string'], 'credit' => ['type' => 'string']]]],
                        'application/json' => ['schema' => ['type' => 'object', 'properties' => ['source_url' => ['type' => 'string', 'format' => 'uri'], 'alt' => ['type' => 'string'], 'caption' => ['type' => 'string'], 'credit' => ['type' => 'string']]]],
                    ]], 'responses' => ['201' => ['description' => 'The image']]],
                ],
                '/images/{image}' => [
                    'get' => ['summary' => 'One image', 'parameters' => [$num('image')], 'responses' => $ok('The image')],
                    'patch' => ['summary' => 'Change alt, caption or credit', 'parameters' => [$num('image')], 'responses' => $ok('The image')],
                    'delete' => ['summary' => 'Delete an unused image (409 while a post uses it)', 'parameters' => [$num('image')], 'responses' => $ok('Deleted')],
                ],
                '/categories' => [
                    'get' => ['summary' => 'Blog sections with post counts', 'responses' => $ok('Sections')],
                    'post' => ['summary' => 'Create a section', 'requestBody' => $body('Category'), 'responses' => ['201' => ['description' => 'The section']]],
                ],
                '/categories/{category}' => [
                    'patch' => ['summary' => 'Change a section (a new slug redirects the old address)', 'parameters' => [$num('category')], 'requestBody' => $body('Category'), 'responses' => $ok('The section')],
                    'delete' => ['summary' => 'Delete an empty section (409 otherwise)', 'parameters' => [$num('category')], 'responses' => $ok('Deleted')],
                ],
                '/tags' => [
                    'get' => ['summary' => 'Internal tags with post counts', 'parameters' => [$query('search', 'Name or slug')], 'responses' => $ok('Tags')],
                    'post' => ['summary' => 'Create a tag (slug, name)', 'responses' => ['201' => ['description' => 'The tag']]],
                ],
                '/tags/{tag}' => [
                    'patch' => ['summary' => 'Rename a tag', 'parameters' => [$num('tag')], 'responses' => $ok('The tag')],
                    'delete' => ['summary' => 'Delete a tag', 'parameters' => [$num('tag')], 'responses' => $ok('Deleted')],
                ],
                '/products' => ['get' => ['summary' => 'Products on sale to link with ::product[slug] (archived=true includes retired ones)', 'parameters' => [$query('search', 'Name, slug or tagline'), $query('archived', 'Include archived products', 'boolean')], 'responses' => $ok('Products')]],
                '/redirects' => [
                    'get' => ['summary' => 'Site redirects', 'parameters' => [$query('search', 'Part of an address')], 'responses' => $ok('Redirects')],
                    'post' => ['summary' => 'Redirect an old address to a new one (both start with /)', 'requestBody' => ['content' => ['application/json' => ['schema' => ['type' => 'object', 'required' => ['from', 'to'], 'properties' => ['from' => ['type' => 'string'], 'to' => ['type' => 'string']]]]]], 'responses' => ['201' => ['description' => 'The redirect']]],
                ],
                '/redirects/{redirect}' => ['delete' => ['summary' => 'Remove a redirect', 'parameters' => [$num('redirect')], 'responses' => $ok('Deleted')]],
                '/schema' => ['get' => ['summary' => 'This description', 'responses' => $ok('OpenAPI document')]],
            ],
            'components' => [
                'securitySchemes' => ['bearer' => ['type' => 'http', 'scheme' => 'bearer']],
                'schemas' => [
                    'PostWrite' => ['type' => 'object', 'properties' => [
                        'title' => ['type' => 'string', 'maxLength' => 250, 'description' => 'Required when creating'],
                        'body' => ['type' => 'string', 'description' => 'Markdown. Required when creating'],
                        'slug' => ['type' => 'string', 'pattern' => '^[A-Za-z0-9_-]+$', 'description' => 'Changing the slug of a live post redirects the old address'],
                        'excerpt' => ['type' => ['string', 'null'], 'maxLength' => 1000],
                        'category' => ['type' => ['string', 'null'], 'description' => 'Section slug'],
                        'cover_image_id' => ['type' => ['integer', 'null']],
                        'status' => ['enum' => ['draft', 'scheduled', 'published', 'archived']],
                        'published_at' => ['type' => ['string', 'null'], 'format' => 'date-time', 'description' => 'Kept when publishing; a future date schedules the post'],
                        'redirect_to' => ['type' => ['string', 'null'], 'description' => 'With status archived: where the address should lead'],
                        'seo_title' => ['type' => ['string', 'null'], 'maxLength' => 250],
                        'seo_description' => ['type' => ['string', 'null'], 'maxLength' => 500],
                        'canonical_url' => ['type' => ['string', 'null'], 'format' => 'uri'],
                        'noindex' => ['type' => 'boolean'],
                        'author_name' => ['type' => ['string', 'null']],
                        'tags' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Tag names; replaces the list'],
                        'products' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Product slugs shown under the article; replaces the list'],
                        'triage_decision' => ['enum' => ['publish', 'refresh', 'merge', 'retire', null]],
                        'triage_confidence' => ['type' => ['integer', 'null'], 'minimum' => 0, 'maximum' => 100],
                        'triage_reason' => ['type' => ['string', 'null'], 'maxLength' => 2000],
                        'triage_merge_into' => ['type' => ['string', 'null'], 'description' => 'Slug of the post this one is merged into'],
                        'note' => ['type' => ['string', 'null'], 'maxLength' => 255, 'description' => 'Saved with the revision: why you changed it'],
                    ]],
                    'Category' => ['type' => 'object', 'properties' => [
                        'slug' => ['type' => 'string', 'pattern' => '^[a-z0-9-]+$'],
                        'name' => ['type' => 'string'],
                        'description' => ['type' => ['string', 'null']],
                        'seo_title' => ['type' => ['string', 'null']],
                        'seo_description' => ['type' => ['string', 'null']],
                        'sort' => ['type' => 'integer'],
                        'is_active' => ['type' => 'boolean'],
                    ]],
                ],
            ],
        ];
    }
}
