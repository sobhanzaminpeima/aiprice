<?php

namespace App\Notifications;

use App\Models\Payment;
use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentFailed extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Payment $payment, public ?Subscription $subscription)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your AIPrice.app payment failed')
            ->greeting("Hi {$notifiable->name},")
            ->line('We could not charge your card for your '.($this->subscription?->plan?->name ?? 'AIPrice.app').' plan.')
            ->line('Your bank may have declined it, or the card may have expired. We will retry automatically over the next few days.')
            ->action('Check your subscription', rtrim(config('app.frontend_url'), '/').'/dashboard/subscription');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'payment_failed',
            'payment_id' => $this->payment->id,
            'plan' => $this->subscription?->plan?->name,
        ];
    }
}
