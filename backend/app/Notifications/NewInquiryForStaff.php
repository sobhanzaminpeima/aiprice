<?php

namespace App\Notifications;

use App\Models\Inquiry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Inquiries are relayed by staff (buyer and owner never see each other), so
 * an unhandled inquiry is a lost lead — tell support as soon as one arrives.
 */
class NewInquiryForStaff extends Notification implements ShouldQueue
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
        $title = $this->inquiry->listing?->title ?? 'a listing';

        return (new MailMessage)
            ->subject("New buyer inquiry: {$title}")
            ->line("A buyer asked about \"{$title}\".")
            ->line('Relay contact details between buyer and owner, then mark it contacted.')
            ->action('Open inquiries', rtrim(config('app.frontend_url'), '/').'/admin/inquiries');
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
