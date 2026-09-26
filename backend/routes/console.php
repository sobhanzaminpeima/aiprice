<?php

use App\Jobs\AnalyzeListingPrice;
use App\Jobs\CrawlCompetitorSource;
use App\Models\CompetitorSource;
use App\Models\Listing;
use App\Models\Subscription;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Lifecycle tasks
|--------------------------------------------------------------------------
| Run by `php artisan schedule:run` every minute (cron on the server, or
| `php artisan schedule:work` locally). Heavy work is dispatched to the
| queue; a `php artisan queue:work --queue=default,ai,crawl` worker runs it.
*/

Artisan::command('listings:expire', function () {
    $n = Listing::where('status', 'active')
        ->whereNotNull('expires_at')
        ->where('expires_at', '<', now())
        ->update(['status' => 'expired']);
    $this->info("Expired {$n} listings.");
})->purpose('Mark active listings past their expiry date as expired');

Artisan::command('subscriptions:expire', function () {
    $n = Subscription::where('status', 'active')
        ->whereNotNull('current_period_end')
        ->where('current_period_end', '<', now())
        ->update(['status' => 'expired']);
    $this->info("Expired {$n} subscriptions.");
})->purpose('End subscriptions whose paid period is over');

Artisan::command('listings:score-unscored', function () {
    $ids = Listing::where('status', 'active')->whereNull('ai_trust_score')->limit(200)->pluck('id');
    $ids->each(fn ($id) => AnalyzeListingPrice::dispatch($id));
    $this->info("Queued {$ids->count()} listings for AI scoring.");
})->purpose('Queue AI scoring for active listings that have none yet');

Artisan::command('competitors:crawl-due', function () {
    $hours = ['hourly' => 1, 'daily' => 24, 'weekly' => 168];

    $due = CompetitorSource::where('is_active', true)->get()->filter(function (CompetitorSource $s) use ($hours) {
        $every = $hours[$s->scrape_interval] ?? 24;
        return !$s->last_scraped_at || $s->last_scraped_at->lt(now()->subHours($every));
    });

    $due->each(fn ($s) => CrawlCompetitorSource::dispatch($s->id));
    $this->info("Queued {$due->count()} competitor crawls.");
})->purpose('Queue crawls for competitor sources whose interval has elapsed');

Schedule::command('listings:expire')->hourly()->withoutOverlapping();
Schedule::command('subscriptions:expire')->hourly()->withoutOverlapping();
Schedule::command('listings:score-unscored')->everyFifteenMinutes()->withoutOverlapping();
Schedule::command('competitors:crawl-due')->hourly()->withoutOverlapping();

// Shared hosting (Hostinger) has no process supervisor for a long-running
// `queue:work`. With QUEUE_WORKER_VIA_SCHEDULER=true the single
// `schedule:run` cron drains the queue every minute instead.
if (config('queue.worker_via_scheduler')) {
    Schedule::command('queue:work --queue=default,ai,crawl --stop-when-empty --max-time=55 --tries=3')
        ->everyMinute()
        ->withoutOverlapping(5)
        ->runInBackground();
}

Schedule::command('sanctum:prune-expired --hours=24')->daily();
Schedule::command('queue:prune-failed --hours=168')->daily();
Schedule::command('auth:clear-resets')->everyFifteenMinutes();
