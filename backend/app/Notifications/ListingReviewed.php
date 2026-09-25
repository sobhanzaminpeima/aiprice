<?php

namespace App\Notifications;

use App\Models\Listing;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ListingReviewed extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Listing $listing, public bool $approved, public ?string $reason = null)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = rtrim(config('app.frontend_url'), '/');

        if ($this->approved) {
            return (new MailMessage)
                ->subject("Your listing is live: {$this->listing->title}")
                ->greeting("Good news, {$notifiable->name}!")
                ->line("\"{$this->listing->title}\" has been approved and is now visible to buyers.")
                ->line('Our AI is scoring its price against the market — the result will appear on the listing shortly.')
                ->action('View listing', "{$url}/listings/{$this->listing->slug}");
        }

        return (new MailMessage)
            ->subject("Your listing needs changes: {$this->listing->title}")
            ->greeting("Hi {$notifiable->name},")
            ->line("\"{$this->listing->title}\" was not approved.")
            ->line('Reason: '.($this->reason ?: 'not specified'))
            ->line('You can edit the listing and submit it again.')
            ->action('Edit listing', "{$url}/dashboard/listings/{$this->listing->id}/edit");
    }

    public function toArray(object $notifiable): array
    {
        return [
            'kind' => $this->approved ? 'listing_approved' : 'listing_rejected',
            'listing_id' => $this->listing->id,
            'title' => $this->listing->title,
            'reason' => $this->reason,
        ];
    }
}
