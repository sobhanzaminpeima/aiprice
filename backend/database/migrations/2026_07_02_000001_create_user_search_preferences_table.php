<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_search_preferences', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('asset_type'); // vehicle, property, rental
            $table->decimal('budget_max', 15, 2)->nullable();
            $table->string('city')->nullable();
            $table->json('priorities')->nullable(); // e.g. ["best_price", "family"]
            $table->string('source')->default('intent_composer'); // intent_composer, chat
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_search_preferences');
    }
};
