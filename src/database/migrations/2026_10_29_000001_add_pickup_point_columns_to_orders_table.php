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
        Schema::table('orders', function (Blueprint $table) {
            // CDEK orders keep the pickup point's city and address in city/street; there is no house number.
            $table->string('house', 30)->nullable()->change();
            $table->string('pickup_point_code', 30)->nullable()->after('courier_note');
            $table->string('pickup_point_name')->nullable()->after('pickup_point_code');
            $table->unsignedSmallInteger('delivery_tariff')->nullable()->after('pickup_point_name');
            // CDEK's estimate at checkout, e.g. "3-5 дней".
            $table->string('delivery_days', 30)->nullable()->after('delivery_tariff');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['pickup_point_code', 'pickup_point_name', 'delivery_tariff', 'delivery_days']);
        });
    }
};
