<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\AuthorizedVehicleImportService;
use Illuminate\Console\Command;
use Throwable;

class ImportAuthorizedVehicles extends Command
{
    protected $signature = 'vehicles:import-authorized {manifest : JSON file with a listings array} {--images= : Export image directory} {--owner= : Owning user UUID} {--dry-run}';

    protected $description = 'Import authorized KKTCarabam vehicles and local image galleries without duplicates';

    public function handle(AuthorizedVehicleImportService $service): int
    {
        try {
            $owner = User::where('is_active', true)->findOrFail($this->option('owner'));
            $file = (string) $this->argument('manifest');
            if (! is_file($file)) {
                throw new \RuntimeException('Manifest file not found.');
            }
            $payload = json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
            $result = $service->import($payload['listings'] ?? [], (string) $this->option('images'), $owner, (bool) $this->option('dry-run'));
            $this->line(json_encode($result, JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
