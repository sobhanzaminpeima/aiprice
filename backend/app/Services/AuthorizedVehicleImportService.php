<?php

namespace App\Services;

use App\Jobs\AnalyzeListingPrice;
use App\Models\Listing;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleBrand;
use App\Models\VehicleModel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class AuthorizedVehicleImportService
{
    public function import(array $rows, string $imageRoot, User $owner, bool $dryRun = false): array
    {
        $data = Validator::make(['listings' => $rows], [
            'listings' => 'required|array|min:1|max:100',
            'listings.*.external_id' => 'required|string|regex:/^[0-9]+$/|distinct',
            'listings.*.source_url' => 'required|url:https',
            'listings.*.brand' => 'required|string|max:100',
            'listings.*.model' => 'required|string|max:100',
            'listings.*.year' => 'required|integer|min:1900|max:'.(now()->year + 1),
            'listings.*.price' => 'required|numeric|min:100',
            'listings.*.currency' => 'required|in:GBP,EUR,USD,TRY',
            'listings.*.city' => 'required|string|max:100',
            'listings.*.mileage' => 'nullable|integer|min:0',
            'listings.*.fuel_type' => 'nullable|string|max:50',
            'listings.*.transmission' => 'nullable|string|max:50',
            'listings.*.engine_size' => 'nullable|string|max:50',
            'listings.*.color' => 'nullable|string|max:50',
            'listings.*.body_type' => 'nullable|string|max:50',
            'listings.*.description' => 'nullable|string|max:10000',
            'listings.*.images_ai_edited' => 'sometimes|boolean',
            'listings.*.images' => 'required|array|min:1|max:50',
            'listings.*.images.*' => 'required|string',
        ])->validate()['listings'];

        $root = realpath($imageRoot);
        if ($root === false) {
            throw ValidationException::withMessages(['images' => 'Image directory does not exist.']);
        }

        // Validate the whole export and every image before making any database changes.
        $prepared = [];
        foreach ($data as $row) {
            $url = parse_url($row['source_url']);
            if (! in_array($url['host'] ?? '', ['www.kktcarabam.com', 'kktcarabam.com'], true)
                || ! str_starts_with($url['path'] ?? '', '/'.$row['external_id'].'-')) {
                throw ValidationException::withMessages(['source_url' => 'Source URL must match the KKTCarabam listing ID.']);
            }
            $row['files'] = [];
            foreach ($row['images'] as $image) {
                $path = realpath($root.DIRECTORY_SEPARATOR.$image);
                if ($path === false || ! str_starts_with($path, $root.DIRECTORY_SEPARATOR)
                    || ! is_file($path) || filesize($path) > 10 * 1024 * 1024) {
                    throw ValidationException::withMessages(['images' => 'Image is missing, outside the image directory or larger than 10 MB.']);
                }
                $info = @getimagesize($path);
                $extension = match ($info[2] ?? null) {
                    IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp', default => null,
                };
                if (! $extension) {
                    throw ValidationException::withMessages(['images' => 'Only genuine JPG, PNG and WEBP images are allowed.']);
                }
                $row['files'][] = ['path' => $path, 'name' => hash_file('sha256', $path).'.'.$extension];
            }
            $prepared[] = $row;
        }

        $result = ['created' => 0, 'skipped' => 0, 'validated' => count($prepared), 'dry_run' => $dryRun];
        if ($dryRun) {
            return $result;
        }

        DB::transaction(function () use ($prepared, $owner, &$result) {
            foreach ($prepared as $row) {
                $key = 'kktcarabam:'.$row['external_id'];
                if (Listing::withTrashed()->where('import_source_key', $key)->exists()) {
                    $result['skipped']++;

                    continue;
                }
                $images = [];
                foreach ($row['files'] as $file) {
                    $path = 'imports/vehicles/'.$file['name']; // neutral path: no third-party name in public URLs
                    if (! Storage::disk('public')->exists($path)) {
                        if (! Storage::disk('public')->put($path, file_get_contents($file['path']))) {
                            throw new \RuntimeException('Could not store imported image.');
                        }
                    }
                    $images[] = '/storage/'.$path;
                }
                $brand = VehicleBrand::firstOrCreate(['name' => $row['brand']], ['is_active' => true]);
                $model = VehicleModel::firstOrCreate(['brand_id' => $brand->id, 'name' => $row['model']], ['is_active' => true]);
                $vehicle = Vehicle::create([
                    'brand_id' => $brand->id, 'model_id' => $model->id, 'year' => $row['year'],
                    'mileage' => $row['mileage'] ?? null, 'fuel_type' => $row['fuel_type'] ?? null,
                    'transmission' => $row['transmission'] ?? null, 'condition' => 'used',
                    'engine_size' => $row['engine_size'] ?? null, 'color' => $row['color'] ?? null,
                    'body_type' => $row['body_type'] ?? null,
                ]);
                $title = \App\Support\BrandGuard::scrub("{$row['year']} {$row['brand']} {$row['model']}");
                $listing = Listing::create([
                    'user_id' => $owner->id, 'type' => 'vehicle', 'listable_type' => Vehicle::class, 'listable_id' => $vehicle->id,
                    'import_source_key' => $key, 'title' => $title, 'price' => $row['price'], 'currency' => $row['currency'],
                    'city' => $row['city'], 'country' => 'CY', 'images' => array_values(array_unique($images)), 'thumbnail' => $images[0],
                    'description' => \App\Support\BrandGuard::scrub(($row['description'] ?? ("$title in {$row['city']}. Advertised at {$row['price']} {$row['currency']}. "
                        .(isset($row['mileage']) ? "Reported mileage: {$row['mileage']} km. " : 'Mileage has not been supplied. ')
                        .'Confirm availability, service history and condition before purchase.'))
                        // Source URL is kept in meta for audit only; never shown to buyers.
                        .(! empty($row['images_ai_edited']) ? ' Images have been AI-edited to remove a watermark; fine details may differ from the originals.' : '')),
                    'status' => 'active', 'published_at' => now(),
                    'meta' => ['source_site' => 'kktcarabam.com', 'external_id' => $row['external_id'], 'source_url' => $row['source_url'],
                        'imported_at' => now()->toIso8601String(), 'authorization_date' => '2026-10-29',
                        'images_ai_edited' => $row['images_ai_edited'] ?? false, 'seller_claims_verified' => false],
                ]);
                AnalyzeListingPrice::dispatch($listing->id)->afterCommit();
                $result['created']++;
            }
        });

        return $result;
    }
}
