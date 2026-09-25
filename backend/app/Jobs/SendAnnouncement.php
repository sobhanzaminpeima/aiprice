<?php

namespace App\Jobs;

use App\Models\Announcement;
use App\Notifications\AnnouncementNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Notification;

/** Fans an announcement out in chunks so a large audience never loads into memory at once. */
class SendAnnouncement implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 900;

    public function __construct(public Announcement $announcement)
    {
    }

    public function handle(): void
    {
        $this->announcement->update(['status' => 'sending']);
        $count = 0;

        $this->announcement->recipientsQuery()->chunkById(200, function ($users) use (&$count) {
            Notification::send($users, new AnnouncementNotification($this->announcement));
            $count += $users->count();
        });

        $this->announcement->update(['status' => 'sent', 'recipients_count' => $count, 'sent_at' => now()]);
    }

    public function failed(\Throwable $e): void
    {
        $this->announcement->update(['status' => 'failed']);
    }
}
