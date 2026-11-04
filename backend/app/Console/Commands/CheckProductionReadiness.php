<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class CheckProductionReadiness extends Command
{
    protected $signature = 'app:check-production';

    protected $description = 'Read-only checks of production configuration, database and SPA assets';

    public function handle(): int
    {
        $checks = [
            'Production environment' => app()->environment('production'),
            'Debug disabled' => config('app.debug') === false,
            'Application key configured' => filled(config('app.key')),
            'HTTPS application URL' => str_starts_with((string) config('app.url'), 'https://'),
            'HTTPS frontend URL' => str_starts_with((string) config('app.frontend_url'), 'https://'),
            'Asynchronous queue' => ! in_array(config('queue.default'), ['sync', 'null'], true),
            'Production mail transport' => ! in_array(config('mail.default'), ['log', 'array'], true),
            'Secure session cookies' => config('session.secure') === true,
            'SPA build present' => is_file(public_path('spa.html')),
            'Storage writable' => is_writable(storage_path()),
            'Bootstrap cache writable' => is_writable(base_path('bootstrap/cache')),
        ];

        try {
            DB::connection()->getPdo();
            $checks['Database connection'] = true;
            $checks['Migrations applied'] = Schema::hasTable('migrations')
                && count(array_diff(
                    array_keys(app('migrator')->getMigrationFiles(database_path('migrations'))),
                    app('migration.repository')->getRan(),
                )) === 0;
        } catch (Throwable) {
            $checks['Database connection and migrations'] = false;
        }

        $this->table(['Check', 'Result'], collect($checks)->map(
            fn ($passed, $name) => [$name, $passed ? 'PASS' : 'FAIL'],
        )->values()->all());
        $this->comment('Also verify real SMTP delivery, queue/cron execution, backups, payment webhooks and browser workflows on the deployment host.');

        return in_array(false, $checks, true) ? self::FAILURE : self::SUCCESS;
    }
}
