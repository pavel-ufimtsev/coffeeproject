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
            // Online payment: the bank's ID for the latest attempt and its status (NEW, CONFIRMED, REJECTED…).
            $table->string('payment_id', 20)->nullable()->index()->after('is_paid');
            $table->string('payment_status', 30)->nullable()->after('payment_id');
            $table->unsignedTinyInteger('payment_attempts')->default(0)->after('payment_status');
            $table->timestamp('paid_at')->nullable()->after('payment_attempts');
            // When the confirmation email went out and the order was handed to retailCRM.
            $table->timestamp('confirmed_at')->nullable()->after('paid_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['payment_id', 'payment_status', 'payment_attempts', 'paid_at', 'confirmed_at']);
        });
    }
};
