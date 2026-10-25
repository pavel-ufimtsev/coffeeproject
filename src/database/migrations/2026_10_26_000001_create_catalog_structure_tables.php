<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->restrictOnDelete();
            $table->string('slug');
            $table->string('name');
            $table->string('h1')->nullable();
            $table->text('intro')->nullable();
            $table->string('seo_title')->nullable();
            $table->string('seo_description', 500)->nullable();
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['parent_id', 'slug']);
        });

        Schema::create('filter_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->string('slug');
            $table->string('name');
            $table->boolean('is_visible')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();

            $table->unique(['category_id', 'slug']);
        });

        Schema::create('filter_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('filter_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('filter_values')->nullOnDelete();
            $table->string('slug');
            $table->string('name');
            $table->boolean('is_landing')->default(false);
            $table->string('h1')->nullable();
            $table->text('intro')->nullable();
            $table->string('seo_title')->nullable();
            $table->string('seo_description', 500)->nullable();
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();

            $table->unique(['filter_group_id', 'slug']);
        });

        Schema::create('collections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->string('slug');
            $table->string('name');
            $table->boolean('is_landing')->default(true);
            $table->string('h1')->nullable();
            $table->text('intro')->nullable();
            $table->string('seo_title')->nullable();
            $table->string('seo_description', 500)->nullable();
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();

            $table->unique(['category_id', 'slug']);
        });

        Schema::create('roasters', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('city');
            $table->text('story')->nullable();
            $table->string('website')->nullable();
            $table->boolean('is_partner')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('roaster_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('city');
            $table->string('address')->nullable();
            $table->string('retailcrm_store_code')->nullable();
            $table->json('roast_days')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('locations');
        Schema::dropIfExists('roasters');
        Schema::dropIfExists('collections');
        Schema::dropIfExists('filter_values');
        Schema::dropIfExists('filter_groups');
        Schema::dropIfExists('categories');
    }
};
