<?php

namespace App\Services\Imports;

use App\Models\Imports\SourceListing;
use App\Models\Imports\SourceListingImage;
use App\Services\Imports\Contracts\SourceAdapter;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * Downloads a source gallery into PRIVATE storage with provenance, removes
 * exact duplicates (SHA-256), and generates WebP + thumbnail variants that
 * keep the original aspect ratio. Images are copied to the public disk only
 * by publish(), which callers invoke solely when the source's image
 * permission is authorized. Pixels are never altered beyond resizing:
 * watermarks stay, nothing is AI-generated or retouched.
 */
class ImageImporter
{
    public const MAX_BYTES = 10 * 1024 * 1024;
    public const FULL_WIDTH = 1280;
    public const THUMB_WIDTH = 480;
    private const DIR = 'imports/vehicles';

    /** @return array{stored: int, duplicates: int, failed: int} */
    public function import(SourceListing $sl, SourceAdapter $adapter, array $urls): array
    {
        $counts = ['stored' => 0, 'duplicates' => 0, 'failed' => 0];
        $seen = [];
        foreach (array_values($urls) as $position => $url) {
            $image = SourceListingImage::firstOrNew(['source_listing_id' => $sl->id, 'position' => $position]);
            $image->source_url = $url;
            // Idempotent: an image already stored from the same URL is kept.
            if ($image->exists && $image->status === 'stored' && $image->sha256 && Storage::disk('local')->exists((string) $image->original_path)) {
                $seen[$image->sha256] = true;
                $counts['stored']++;

                continue;
            }
            try {
                $bytes = $adapter->downloadImage($url);
                $stored = $this->store($bytes);
                $image->fill($stored);
                if (isset($seen[$stored['sha256']])) {
                    $image->status = 'duplicate';
                    $counts['duplicates']++;
                } else {
                    $seen[$stored['sha256']] = true;
                    $image->status = 'stored';
                    $counts['stored']++;
                }
                $image->error = null;
            } catch (Throwable $e) {
                $image->status = 'failed';
                $image->error = mb_substr($e->getMessage(), 0, 500);
                $counts['failed']++;
            }
            $image->save();
        }
        // Remove rows for positions that no longer exist in the source gallery.
        SourceListingImage::where('source_listing_id', $sl->id)->where('position', '>=', count($urls))->delete();

        $first = true;
        foreach ($sl->images()->get() as $img) {
            $img->update(['is_cover' => $first && $img->status === 'stored']);
            if ($img->status === 'stored') {
                $first = false;
            }
        }

        return $counts;
    }

    /** @return array{sha256: string, original_path: string, webp_path: ?string, thumb_path: ?string, width: int, height: int, bytes: int} */
    public function store(string $bytes): array
    {
        if ($bytes === '' || strlen($bytes) > self::MAX_BYTES) {
            throw new RuntimeException('Image is empty or larger than 10 MB.');
        }
        $info = @getimagesizefromstring($bytes);
        $ext = match ($info[2] ?? null) {
            IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp', default => null,
        };
        if (! $ext || ($info[0] ?? 0) < 200 || ($info[1] ?? 0) < 150) {
            throw new RuntimeException('Not a usable JPG/PNG/WEBP photo.');
        }
        $sha = hash('sha256', $bytes);
        $base = self::DIR.'/'.substr($sha, 0, 2).'/'.$sha;
        $disk = Storage::disk('local');
        if (! $disk->exists("$base.$ext")) {
            $disk->put("$base.$ext", $bytes);
        }
        $webp = $thumb = null;
        if (function_exists('imagewebp')) {
            $webp = ($disk->exists("$base.webp") || $this->variant($bytes, $disk->path("$base.webp"), self::FULL_WIDTH)) ? "$base.webp" : null;
            $thumb = ($disk->exists("$base-thumb.webp") || $this->variant($bytes, $disk->path("$base-thumb.webp"), self::THUMB_WIDTH)) ? "$base-thumb.webp" : null;
        }

        return [
            'sha256' => $sha, 'original_path' => "$base.$ext", 'webp_path' => $webp, 'thumb_path' => $thumb,
            'width' => (int) $info[0], 'height' => (int) $info[1], 'bytes' => strlen($bytes),
        ];
    }

    /**
     * Copy stored variants to the public disk. Call only when the source's
     * image permission is authorized. Returns public URLs in gallery order.
     *
     * @return list<array{full: string, thumb: string, original: string}>
     */
    public function publish(SourceListing $sl): array
    {
        $out = [];
        foreach ($sl->images()->where('status', 'stored')->get() as $img) {
            $paths = [];
            foreach (['original' => $img->original_path, 'full' => $img->webp_path ?: $img->original_path, 'thumb' => $img->thumb_path ?: $img->webp_path ?: $img->original_path] as $k => $path) {
                if (! Storage::disk('public')->exists($path)) {
                    Storage::disk('public')->put($path, Storage::disk('local')->get($path));
                }
                $paths[$k] = '/storage/'.$path;
            }
            $img->update(['public_paths' => $paths]);
            $out[] = $paths;
        }

        return $out;
    }

    /** Remove public copies that no other published listing still uses. */
    public function unpublish(SourceListing $sl): int
    {
        $removed = 0;
        foreach ($sl->images()->whereNotNull('public_paths')->get() as $img) {
            $sharedElsewhere = SourceListingImage::where('sha256', $img->sha256)->where('id', '!=', $img->id)->whereNotNull('public_paths')->exists();
            if (! $sharedElsewhere) {
                foreach ($img->public_paths as $url) {
                    $path = substr($url, strlen('/storage/'));
                    if (Storage::disk('public')->exists($path)) {
                        Storage::disk('public')->delete($path);
                        $removed++;
                    }
                }
            }
            $img->update(['public_paths' => null]);
        }

        return $removed;
    }

    private function variant(string $bytes, string $target, int $maxWidth): bool
    {
        $src = @imagecreatefromstring($bytes);
        if (! $src) {
            return false;
        }
        $w = imagesx($src);
        $h = imagesy($src);
        $nw = min($w, $maxWidth);
        $nh = (int) round($h * $nw / $w);
        $dst = imagecreatetruecolor($nw, $nh);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
        @mkdir(dirname($target), 0775, true);
        $ok = imagewebp($dst, $target, 82);
        imagedestroy($src);
        imagedestroy($dst);

        return $ok;
    }
}
