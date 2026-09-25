<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class KycReviewed extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public bool $approved, public ?string $reason = null)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = rtrim(config('app.frontend_url'), '/').'/dashboard/kyc';

        return $this->approved
            ? (new MailMessage)
                ->subject('Your identity is verified')
                ->greeting("Hi {$notifiable->name},")
                ->line('Your KYC verification was approved. Your listings now carry the verified-seller badge.')
                ->action('Go to dashboard', $url)
            : (new MailMessage)
                ->subject('Your identity verification needs attention')
                ->greeting("Hi {$notifiable->name},")
                ->line('We could not verify your identity from the documents you sent.')
                ->line('Reason: '.($this->reason ?: 'not specified'))
                ->action('Submit again', $url);
    }

    public function toArray(object $notifiable): array
    {
        return ['kind' => $this->approved ? 'kyc_approved' : 'kyc_rejected', 'reason' => $this->reason];
    }
}
