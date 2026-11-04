<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The old crawler inserted invented "sample" competitor listings (external_id
 * "gen-…") whenever a site blocked it, and those fake prices fed the AI price
 * estimate. Remove them; the crawler now fails loudly instead.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('competitor_listings')->where('external_id', 'like', 'gen-%')->delete();
    }

    public function down(): void
    {
        // Fabricated rows are intentionally not restored.
    }
};
