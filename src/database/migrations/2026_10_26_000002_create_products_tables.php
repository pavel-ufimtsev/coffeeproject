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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->foreignId('roaster_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 32);
            $table->string('status', 16)->default('draft')->index();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('tagline')->nullable();
            $table->text('summary')->nullable();
            $table->longText('description')->nullable();
            $table->json('specs')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->unsignedInteger('sort')->default(0);
            $table->string('seo_title')->nullable();
            $table->string('seo_description', 500)->nullable();
            $table->unsignedBigInteger('retailcrm_product_id')->nullable()->index();
            $table->string('retailcrm_name')->nullable();
            $table->boolean('retailcrm_active')->nullable();
            $table->timestamps();
        });

        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->unsignedInteger('weight_g')->nullable();
            $table->unsignedBigInteger('retailcrm_offer_id')->nullable()->unique();
            $table->decimal('price', 10, 2)->nullable();
            $table->decimal('old_price', 10, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('retailcrm_active')->nullable();
            $table->string('sync_status', 16)->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });

        Schema::create('product_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->string('thumb_path');
            $table->string('alt')->nullable();
            $table->unsignedInteger('sort')->default(0);
            $table->string('source', 16)->default('upload');
            $table->timestamps();
        });

        Schema::create('filter_value_product', function (Blueprint $table) {
            $table->foreignId('filter_value_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();

            $table->primary(['filter_value_id', 'product_id']);
        });

        Schema::create('collection_product', function (Blueprint $table) {
            $table->foreignId('collection_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sort')->default(0);

            $table->primary(['collection_id', 'product_id']);
        });

        Schema::create('location_product_variant', function (Blueprint $table) {
            $table->foreignId('location_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();

            $table->primary(['location_id', 'product_variant_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('location_product_variant');
        Schema::dropIfExists('collection_product');
        Schema::dropIfExists('filter_value_product');
        Schema::dropIfExists('product_images');
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('products');
    }
};
