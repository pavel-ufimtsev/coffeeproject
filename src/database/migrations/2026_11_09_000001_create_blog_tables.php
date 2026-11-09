<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The blog: posts written in Markdown, categories, internal tags, images,
 * links to products and a revision for every save.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blog_categories', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('seo_title')->nullable();
            $table->string('seo_description', 500)->nullable();
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Tags have no public pages; they group posts for "related posts" and for agents.
        Schema::create('blog_tags', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('blog_images', function (Blueprint $table) {
            $table->id();
            $table->string('path');
            $table->string('thumb_path');
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->string('original_name')->nullable();
            $table->string('alt', 500)->nullable();
            $table->string('caption', 500)->nullable();
            $table->string('credit')->nullable();
            // A person's name or "agent: {token name}".
            $table->string('uploaded_by')->nullable();
            $table->timestamps();
        });

        Schema::create('blog_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('blog_category_id')->nullable()->constrained()->nullOnDelete();
            // Old slugs are kept exactly, including underscores and capitals.
            $table->string('slug')->unique();
            $table->string('title');
            $table->text('excerpt')->nullable();
            $table->longText('body');
            $table->foreignId('cover_image_id')->nullable()->constrained('blog_images')->nullOnDelete();
            $table->string('status', 20)->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->string('author_name')->nullable();
            $table->string('seo_title')->nullable();
            $table->string('seo_description', 500)->nullable();
            $table->string('canonical_url')->nullable();
            $table->boolean('noindex')->default(false);
            $table->unsignedSmallInteger('reading_minutes')->default(1);
            // Where the post came from: archive, admin or agent.
            $table->string('source', 20)->default('admin');
            $table->string('legacy_url')->nullable();
            // Facts the importer found: words, images found and missing, old tags, links.
            $table->json('import_meta')->nullable();
            // Last change by a person or an agent; the importer never overwrites a post edited after import.
            $table->timestamp('edited_at')->nullable();
            // The editorial agent's verdict: publish, refresh, merge or retire.
            $table->string('triage_decision', 20)->nullable();
            $table->unsignedTinyInteger('triage_confidence')->nullable();
            $table->text('triage_reason')->nullable();
            $table->string('triage_merge_into')->nullable();
            $table->timestamp('triaged_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'published_at']);
            $table->index('triage_decision');
        });

        Schema::create('blog_post_tag', function (Blueprint $table) {
            $table->foreignId('blog_post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('blog_tag_id')->constrained()->cascadeOnDelete();
            $table->primary(['blog_post_id', 'blog_tag_id']);
        });

        Schema::create('blog_post_product', function (Blueprint $table) {
            $table->foreignId('blog_post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sort')->default(0);
            $table->primary(['blog_post_id', 'product_id']);
        });

        // Which posts use which image, so an image in use cannot be deleted.
        Schema::create('blog_image_post', function (Blueprint $table) {
            $table->foreignId('blog_image_id')->constrained()->cascadeOnDelete();
            $table->foreignId('blog_post_id')->constrained()->cascadeOnDelete();
            $table->primary(['blog_image_id', 'blog_post_id']);
        });

        Schema::create('blog_post_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('blog_post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            // A person's name or "agent: {token name}".
            $table->string('author');
            $table->string('note')->nullable();
            $table->string('title');
            $table->text('excerpt')->nullable();
            $table->longText('body');
            $table->string('seo_title')->nullable();
            $table->string('seo_description', 500)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['blog_post_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_post_revisions');
        Schema::dropIfExists('blog_image_post');
        Schema::dropIfExists('blog_post_product');
        Schema::dropIfExists('blog_post_tag');
        Schema::dropIfExists('blog_posts');
        Schema::dropIfExists('blog_images');
        Schema::dropIfExists('blog_tags');
        Schema::dropIfExists('blog_categories');
    }
};
