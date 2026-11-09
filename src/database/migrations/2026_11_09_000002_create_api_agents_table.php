<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AI agents and other programs that use the API. Their bearer tokens live in
 * personal_access_tokens; an agent is never a person who can sign in.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_agents', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_agents');
    }
};
