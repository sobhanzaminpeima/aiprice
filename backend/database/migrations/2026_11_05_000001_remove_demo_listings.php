<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The first deploy seeded 32 sample listings with stock photos
 * (images.unsplash.com) and invented prices. They are not real offers, so
 * they are removed for good. Real listings (seller uploads and authorised
 * imports) never use stock-photo URLs and are kept.
 */
return new class extends Migration
{
    public function up(): void
    {
        $demo = DB::table('listings')
            ->whereNull('import_source_key')
            ->where('images', 'like', '%images.unsplash.com%')
            ->get(['id', 'listable_type', 'listable_id']);

        if ($demo->isEmpty()) {
            return;
        }

        DB::transaction(function () use ($demo) {
            foreach ($demo->groupBy('listable_type') as $type => $rows) {
                $table = match (class_basename((string) $type)) {
                    'Vehicle' => 'vehicles', 'Property' => 'properties', 'Rental' => 'rentals', default => null,
                };
                if ($table) {
                    DB::table($table)->whereIn('id', $rows->pluck('listable_id'))->delete();
                }
            }
            $ids = $demo->pluck('id');
            foreach (['listing_contact_reveals', 'instagram_posts', 'social_posts'] as $t) {
                if (DB::getSchemaBuilder()->hasTable($t) && DB::getSchemaBuilder()->hasColumn($t, 'listing_id')) {
                    DB::table($t)->whereIn('listing_id', $ids)->delete();
                }
            }
            // Child rows with a listing_id foreign key cascade.
            DB::table('listings')->whereIn('id', $ids)->delete();
        });
    }

    public function down(): void
    {
        // Demo data is not restored.
    }
};
