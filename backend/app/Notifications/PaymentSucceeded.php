<?php

namespace App\Notifications;

use App\Models\Payment;
use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentSucceeded extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Payment $payment, public Subscription $subscription)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $amount = number_format((float) $this->payment->amount, 2).' '.$this->payment->currency;
        $until = $this->subscription->current_period_end?->format('j F Y');
        $invoiceId = $this->payment->invoice?->id;

        return (new MailMessage)
            ->subject('Payment received — thank you')
            ->greeting("Hi {$notifiable->name},")
            ->line("We received {$amount} for AIPrice.app {$this->subscription->plan?->name}.")
            ->line($until ? "Your plan is active until {$until}." : 'Your plan is active.')
            ->action('View invoice', rtrim(config('app.frontend_url'), '/').($invoiceId ? "/dashboard/invoices/{$invoiceId}" : '/dashboard/subscription'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'payment_succeeded',
            'payment_id' => $this->payment->id,
            'plan' => $this->subscription->plan?->name,
            'amount' => (float) $this->payment->amount,
            'currency' => $this->payment->currency,
        ];
    }
}
