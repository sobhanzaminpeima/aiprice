<?php

namespace App\Notifications;

use App\Models\SupportTicket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent both ways: to the customer when staff reply, and to staff when a
 * customer opens a ticket or replies. $forStaff picks the link and wording.
 * Guests (contact-form tickets) get mail only, with the reply text inline,
 * because they have no account to read it in.
 */
class SupportTicketUpdated extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public SupportTicket $ticket, public string $event, public bool $forStaff = false)
    {
    }

    public function via(object $notifiable): array
    {
        return $notifiable instanceof AnonymousNotifiable ? ['mail'] : ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $base = rtrim(config('app.frontend_url'), '/');
        $guest = $notifiable instanceof AnonymousNotifiable;

        $line = match ($this->event) {
            'opened' => $this->ticket->source === 'contact_form' ? 'A visitor sent a message through the contact form.' : 'A new support ticket was opened.',
            'customer_reply' => 'The customer replied to their ticket.',
            'staff_reply' => 'Our support team replied to your message.',
            'status' => "Your request is now marked \"{$this->ticket->status}\".",
            default => 'Your support request was updated.',
        };

        $mail = (new MailMessage)
            ->subject("[{$this->ticket->ticket_number}] {$this->ticket->subject}")
            ->line($line);

        if ($guest) {
            $latest = $this->ticket->replies()->where('is_staff', true)->latest()->first();
            if ($latest && $this->event === 'staff_reply') {
                $mail->line('---')->line($latest->body)->line('---');
            }
            return $mail->line("To follow up, use the contact form and mention reference {$this->ticket->ticket_number}.")
                ->action('Contact us', "{$base}/contact");
        }

        $url = $this->forStaff ? "{$base}/admin/support?ticket={$this->ticket->id}" : "{$base}/dashboard/support?ticket={$this->ticket->id}";
        return $mail->action('Open ticket', $url);
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
