<?php

namespace App\Notifications;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Plan lifecycle messages to the seller: a plan granted by staff, a trial or
 * paid period ending soon, or one that has ended.
 */
class SubscriptionUpdated extends Notification implements ShouldQueue
{
    use Queueable;

    /** @param 'granted'|'ending_soon'|'ended' $event */
    public function __construct(public Subscription $subscription, public string $event)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $plan = $this->subscription->plan?->name ?? 'your plan';
        $until = $this->subscription->current_period_end?->format('j F Y');
        $trial = $this->subscription->status === 'trial';
        $url = rtrim(config('app.frontend_url'), '/').'/dashboard/subscription';

        $mail = (new MailMessage)->greeting("Hi {$notifiable->name},");

        return match ($this->event) {
            'granted' => $mail->subject("Your {$plan} plan is active")
                ->line("Your AIPrice.app {$plan} plan is active".($until ? " until {$until}." : '.'))
                ->action('Open your dashboard', $url),
            'ending_soon' => $mail->subject($trial ? "Your free trial ends on {$until}" : "Your {$plan} plan ends on {$until}")
                ->line($trial
                    ? "Your free {$plan} trial ends on {$until}. Keep your listings, featured slots and AI tools by choosing a plan now."
                    : "Your {$plan} plan ends on {$until}. Renew to keep your listing slots and featured placements.")
                ->action('Keep my plan', $url),
            default => $mail->subject("Your {$plan} plan has ended")
                ->line('Your listings stay online until their own end date, but you can only add new ones within the free plan limit.')
                ->action('Choose a plan', $url),
        };
    }

    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'subscription_'.$this->event,
            'subscription_id' => $this->subscription->id,
            'plan' => $this->subscription->plan?->name,
            'status' => $this->subscription->status,
            'ends_at' => $this->subscription->current_period_end?->toIso8601String(),
        ];
    }
}
