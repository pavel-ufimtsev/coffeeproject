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
        Schema::create('redirects', function (Blueprint $table) {
            $table->id();
            $table->string('from_path', 512)->unique();
            $table->string('to_path', 512);
            $table->unsignedSmallInteger('status_code')->default(301);
            $table->unsignedInteger('hits')->default(0);
            $table->timestamp('last_hit_at')->nullable();
            $table->timestamps();
        });

        Schema::create('retailcrm_sync_runs', function (Blueprint $table) {
            $table->id();
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('products_checked')->default(0);
            $table->unsignedInteger('variants_changed')->default(0);
            $table->json('changes')->nullable();
            $table->json('issues')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('retailcrm_sync_runs');
        Schema::dropIfExists('redirects');
    }
};
