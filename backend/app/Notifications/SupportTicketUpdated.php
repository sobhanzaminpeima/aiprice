<?php

namespace App\Notifications;

use App\Models\SupportTicket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent both ways: to the customer when staff reply, and to staff when a
 * customer opens a ticket or replies. $forStaff picks the link and wording.
 */
class SupportTicketUpdated extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public SupportTicket $ticket, public string $event, public bool $forStaff = false)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $base = rtrim(config('app.frontend_url'), '/');
        $url = $this->forStaff ? "{$base}/admin/support?ticket={$this->ticket->id}" : "{$base}/dashboard/support?ticket={$this->ticket->id}";

        $line = match ($this->event) {
            'opened' => 'A new support ticket was opened.',
            'customer_reply' => 'The customer replied to their ticket.',
            'staff_reply' => 'Our support team replied to your ticket.',
            'status' => "Your ticket status changed to \"{$this->ticket->status}\".",
            default => 'Your support ticket was updated.',
        };

        return (new MailMessage)
            ->subject("[{$this->ticket->ticket_number}] {$this->ticket->subject}")
            ->line($line)
            ->action('Open ticket', $url);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'support_'.$this->event,
            'ticket_id' => $this->ticket->id,
            'ticket_number' => $this->ticket->ticket_number,
            'title' => $this->ticket->subject,
            'for_staff' => $this->forStaff,
        ];
    }
}
