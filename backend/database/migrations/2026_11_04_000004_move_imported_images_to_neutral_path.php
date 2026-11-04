<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Imported photos lived under /storage/imports/kktcarabam/, exposing another
 * marketplace's name in every image URL. Move them to imports/vehicles/ and
 * rewrite listings.images / listings.thumbnail. Files are content-hashed, so
 * a move never collides; a missing file just keeps its (rewritten) URL.
 */
return new class extends Migration
{
    public function up(): void
    {
        $disk = Storage::disk('public');
        DB::table('listings')->where(fn ($q) => $q->where('images', 'like', '%imports/kktcarabam/%')->orWhere('thumbnail', 'like', '%imports/kktcarabam/%'))
            ->orderBy('id')->chunk(100, function ($rows) use ($disk) {
                foreach ($rows as $row) {
                    $images = json_decode((string) $row->images, true) ?: [];
                    foreach ($images as $url) {
                        if (is_string($url) && str_contains($url, 'imports/kktcarabam/')) {
                            $from = ltrim(str_replace('/storage/', '', $url), '/');
                            $to = str_replace('imports/kktcarabam/', 'imports/vehicles/', $from);
                            if ($disk->exists($from) && ! $disk->exists($to)) {
                                $disk->move($from, $to);
                            }
                        }
                    }
                    DB::table('listings')->where('id', $row->id)->update([
                        'images' => str_replace('imports/kktcarabam/', 'imports/vehicles/', (string) $row->images),
                        'thumbnail' => $row->thumbnail ? str_replace('imports/kktcarabam/', 'imports/vehicles/', $row->thumbnail) : $row->thumbnail,
                    ]);
                }
            });
    }

    public function down(): void
    {
        // Not reversed: the neutral path is the intended state.
    }
};
