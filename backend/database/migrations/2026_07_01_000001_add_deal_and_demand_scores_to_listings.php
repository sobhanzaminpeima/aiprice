<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->decimal('ai_demand_score', 5, 2)->nullable()->after('ai_investment_score');
            $table->decimal('ai_deal_score', 5, 2)->nullable()->after('ai_demand_score');
            $table->json('ai_score_breakdown')->nullable()->after('ai_analysis');
            $table->timestamp('ai_scored_at')->nullable()->after('ai_score_breakdown');
        });
    }

    public function down(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->dropColumn(['ai_demand_score', 'ai_deal_score', 'ai_score_breakdown', 'ai_scored_at']);
        });
    }
};
