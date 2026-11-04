<?php

use App\Support\BrandGuard;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One-off cleanup: older imports appended "Source: https://www.kktcarabam.com/…"
 * to descriptions and generated captions reused it, so buyers saw another
 * marketplace's name. Rewrite stored copy through BrandGuard. The original
 * source URL stays in listings.meta for audit.
 */
return new class extends Migration
{
    public function up(): void
    {
        $targets = [
            'listings' => ['title', 'description'],
            'social_posts' => ['caption'],
        ];
        foreach ($targets as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            DB::table($table)->select(array_merge(['id'], $columns))->orderBy('id')
                ->chunk(200, function ($rows) use ($table, $columns) {
                    foreach ($rows as $row) {
                        $changes = [];
                        foreach ($columns as $col) {
                            $value = $row->{$col};
                            if (is_string($value) && (BrandGuard::mentionsThirdParty($value) || preg_match('/verified (?:fair )?pric/i', $value))) {
                                $changes[$col] = BrandGuard::scrub($value);
                            }
                        }
                        if ($changes) {
                            DB::table($table)->where('id', $row->id)->update($changes);
                        }
                    }
                });
        }
    }

    public function down(): void
    {
        // Irreversible content cleanup.
    }
};
