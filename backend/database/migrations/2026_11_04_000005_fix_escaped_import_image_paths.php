<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Follow-up to 000004: listings.images is stored as JSON with escaped slashes
 * (imports\/kktcarabam\/...), so the plain-text match missed every row. Decode
 * the JSON, move the files, and store the neutral paths.
 */
return new class extends Migration
{
    public function up(): void
    {
        $disk = Storage::disk('public');
        $fix = function (?string $url) use ($disk): ?string {
            if (! is_string($url) || ! str_contains($url, 'imports/kktcarabam/')) {
                return $url;
            }
            $from = ltrim(str_replace('/storage/', '', $url), '/');
            $to = str_replace('imports/kktcarabam/', 'imports/vehicles/', $from);
            if ($disk->exists($from) && ! $disk->exists($to)) {
                $disk->move($from, $to);
            }

            return str_replace('imports/kktcarabam/', 'imports/vehicles/', $url);
        };

        DB::table('listings')->where(fn ($q) => $q->where('images', 'like', '%kktcarabam%')->orWhere('thumbnail', 'like', '%kktcarabam%'))
            ->orderBy('id')->chunk(100, function ($rows) use ($fix) {
                foreach ($rows as $row) {
                    $images = json_decode((string) $row->images, true);
                    $images = is_array($images) ? array_map($fix, $images) : $images;
                    DB::table('listings')->where('id', $row->id)->update([
                        'images' => is_array($images) ? json_encode($images, JSON_UNESCAPED_SLASHES) : $row->images,
                        'thumbnail' => $fix($row->thumbnail),
                    ]);
                }
            });
    }

    public function down(): void
    {
        // Not reversed: the neutral path is the intended state.
    }
};
