<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fields the redesigned listing page needs: how many similar listings the
 * price check used, a risk level with its reason, and the description in
 * several languages.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->unsignedInteger('ai_comparable_count')->nullable()->after('ai_comparable_ids');
            $table->string('risk_level', 10)->nullable()->after('ai_comparable_count'); // low | medium | high
            $table->string('risk_note', 500)->nullable()->after('risk_level');
            $table->json('description_i18n')->nullable()->after('description'); // {"en":..,"tr":..,"fa":..}
            $table->timestamp('translated_at')->nullable()->after('description_i18n');
            $table->index(['type', 'city', 'published_at'], 'listings_area_time_idx');
        });
    }

    public function down(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->dropIndex('listings_area_time_idx');
            $table->dropColumn(['ai_comparable_count', 'risk_level', 'risk_note', 'description_i18n', 'translated_at']);
        });
    }
};
