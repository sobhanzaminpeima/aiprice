<?php

use App\Jobs\AnalyzeListingPrice;
use App\Jobs\CrawlCompetitorSource;
use App\Models\CompetitorSource;
use App\Models\Listing;
use App\Models\Subscription;
use App\Notifications\SubscriptionUpdated;
use App\Services\Billing\PlanEntitlements;
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
    $ended = Subscription::with('plan', 'user')
        ->whereIn('status', Subscription::LIVE)
        ->whereNotNull('current_period_end')
        ->where('current_period_end', '<', now())
        ->get();

    foreach ($ended as $sub) {
        $sub->update(['status' => 'expired']);
        $user = $sub->user;
        if (!$user || app(PlanEntitlements::class)->subscription($user)) {
            continue; // already moved to another live plan
        }
        // Featured slots are a paid perk; listings themselves stay up until their own expiry.
        $keep = app(PlanEntitlements::class)->limits($user)['max_featured'];
        Listing::where('user_id', $user->id)->where('is_featured', true)
            ->orderByDesc('published_at')->get()->slice($keep)
            ->each(fn (Listing $l) => $l->update(['is_featured' => false]));
        $user->notify(new SubscriptionUpdated($sub, 'ended'));
    }

    $this->info("Expired {$ended->count()} subscriptions.");
})->purpose('End subscriptions and free trials whose period is over');

Artisan::command('subscriptions:remind', function () {
    // Manual billing has no automatic renewal, so remind sellers before the end.
    $due = Subscription::with('plan', 'user')
        ->whereIn('status', Subscription::LIVE)
        ->whereNull('gateway_subscription_id')
        ->whereNull('reminder_sent_at')
        ->whereNull('cancelled_at')
        ->whereBetween('current_period_end', [now(), now()->addDays(3)])
        ->get();

    foreach ($due as $sub) {
        $sub->user?->notify(new SubscriptionUpdated($sub, 'ending_soon'));
        $sub->update(['reminder_sent_at' => now()]);
    }

    $this->info("Sent {$due->count()} renewal reminders.");
})->purpose('Remind sellers three days before a trial or manually-paid plan ends');

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
Schedule::command('subscriptions:remind')->hourly()->withoutOverlapping();
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
