<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_provider_connections', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('provider')->unique();
            $table->text('api_key')->nullable();
            $table->string('model');
            $table->boolean('enabled')->default(true);
            $table->boolean('is_active')->default(false);
            $table->unsignedSmallInteger('last_http_status')->nullable();
            $table->string('last_test_result')->nullable();
            $table->timestamp('tested_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_provider_connections');
    }
};
