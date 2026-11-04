<?php

namespace App\Notifications;

use App\Models\Inquiry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** A buyer used "Send to seller" on one of this user's listings. */
class NewInquiryForSeller extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Inquiry $inquiry)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $title = $this->inquiry->listing?->title ?? 'your listing';

        return (new MailMessage)
            ->subject("New message about {$title} — AIPrice.app")
            ->line("A buyer sent you a message about \"{$title}\".")
            ->line(str($this->inquiry->message)->limit(300)->toString())
            ->action('Open your inbox', rtrim(config('app.frontend_url'), '/').'/dashboard/messages')
            ->line('Never send deposits before seeing the vehicle or property in person.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'new_inquiry',
            'inquiry_id' => $this->inquiry->id,
            'listing_title' => $this->inquiry->listing?->title,
        ];
    }
}
