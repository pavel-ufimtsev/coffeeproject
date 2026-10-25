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
        // Shop settings edited in the admin, e.g. courier prices.
        Schema::create('settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->json('value')->nullable();
            $table->timestamps();
        });

        // A customer's saved delivery addresses, picked by label at checkout.
        Schema::create('addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('label', 50);
            $table->boolean('is_default')->default(false);
            $table->string('city');
            $table->string('street');
            $table->string('house', 30);
            $table->string('apartment', 30)->nullable();
            $table->string('entrance', 10)->nullable();
            $table->string('floor', 10)->nullable();
            $table->string('intercom', 30)->nullable();
            $table->string('courier_note', 500)->nullable();
            $table->timestamps();
        });

        Schema::create('promo_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('type', 20);
            $table->decimal('value', 10, 2);
            $table->decimal('min_subtotal', 10, 2)->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->unsignedInteger('max_uses')->nullable();
            $table->unsignedInteger('used_count')->default(0);
            $table->boolean('is_active')->default(true);
            $table->string('note')->nullable();
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('number', 20)->nullable()->unique();
            // Unguessable key for the thank-you page, which guests see too.
            $table->string('token', 40)->unique();
            // One per checkout page load, so a double submit cannot place two orders.
            $table->string('checkout_token', 40)->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 20)->default('new')->index();

            $table->string('first_name');
            $table->string('last_name');
            $table->string('phone', 20)->index();
            $table->string('email')->index();

            $table->string('delivery_method', 20);
            $table->decimal('delivery_price', 10, 2)->default(0);
            $table->string('city');
            $table->string('street');
            $table->string('house', 30);
            $table->string('apartment', 30)->nullable();
            $table->string('entrance', 10)->nullable();
            $table->string('floor', 10)->nullable();
            $table->string('intercom', 30)->nullable();
            $table->string('courier_note', 500)->nullable();

            $table->string('payment_method', 20);
            $table->boolean('is_paid')->default(false);

            $table->foreignId('promo_code_id')->nullable()->constrained()->nullOnDelete();
            $table->string('promo_code', 50)->nullable();
            $table->decimal('subtotal', 10, 2);
            $table->decimal('discount', 10, 2)->default(0);
            $table->decimal('total', 10, 2);

            $table->text('comment')->nullable();
            $table->timestamp('consent_at');
            $table->string('ip', 45)->nullable();

            $table->string('retailcrm_id', 20)->nullable();
            $table->timestamp('retailcrm_pushed_at')->nullable();
            $table->text('retailcrm_error')->nullable();
            $table->timestamps();
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('retailcrm_offer_id')->nullable();
            // Copies of what was bought, so later catalog changes leave the order as it was.
            $table->string('name');
            $table->string('variant_label')->nullable();
            $table->string('grind', 20)->nullable();
            $table->string('grind_label')->nullable();
            $table->decimal('price', 10, 2);
            $table->unsignedSmallInteger('quantity');
            $table->decimal('total', 10, 2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('promo_codes');
        Schema::dropIfExists('addresses');
        Schema::dropIfExists('settings');
    }
};
