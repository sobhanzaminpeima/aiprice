<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Console\Scheduling\CallbackEvent;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * `schedule:run` for hosts that disable proc_open/exec (e.g. Hostinger
 * shared hosting). Laravel's runner launches every scheduled command as a
 * subprocess, which silently fails there; this runs the same due events
 * in-process via Artisan::call instead.
 *
 * Cron, every minute:  php /path/to/artisan schedule:run-inline
 */
class ScheduleRunInline extends Command
{
    protected $signature = 'schedule:run-inline';

    protected $description = 'Run due scheduled commands in-process (no proc_open needed)';

    public function handle(Schedule $schedule): int
    {
        $due = collect($schedule->dueEvents($this->laravel))
            ->filter(fn (Event $e) => $e->filtersPass($this->laravel));

        if ($due->isEmpty()) {
            return self::SUCCESS;
        }

        foreach ($due as $event) {
            $label = $event instanceof CallbackEvent ? ($event->description ?: 'callback') : $this->artisanCommand($event);

            // One minute's cron must not start a task the previous run is still
            // executing (queue:work runs for up to ~55s).
            $lock = Cache::lock('schedule-inline:'.$event->mutexName(), 3600);
            if (!$lock->get()) {
                $this->line("skip (running): {$label}");
                continue;
            }

            try {
                $started = microtime(true);
                if ($event instanceof CallbackEvent) {
                    $event->run($this->laravel);
                } elseif ($command = $this->artisanCommand($event)) {
                    Artisan::call($command);
                } else {
                    $this->warn("skip (not an artisan command): {$event->command}");
                    continue;
                }
                $this->line(sprintf('ran %s (%.1fs)', $label, microtime(true) - $started));
            } catch (\Throwable $e) {
                Log::error('Inline scheduled task failed', ['task' => $label, 'error' => $e->getMessage()]);
                $this->error("failed: {$label} — {$e->getMessage()}");
            } finally {
                $lock->release();
            }
        }

        return self::SUCCESS;
    }

    /**
     * Schedule::command() stores "'<php>' 'artisan' <command …>"; recover the
     * part after "artisan" so it can be passed to Artisan::call().
     */
    private function artisanCommand(Event $event): ?string
    {
        // Quoted with ' on Linux and " on Windows.
        return preg_match('/artisan[\'"]?\s+(.+)$/', (string) $event->command, $m) ? trim($m[1]) : null;
    }
}
