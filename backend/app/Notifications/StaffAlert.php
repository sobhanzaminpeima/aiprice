<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * One alert to staff for anything a user asks for: a message to a seller,
 * the contact form, a support ticket or reply, a new listing waiting for
 * review, a KYC submission or a new account. The full text and the sender's
 * details are stored, so the admin inbox shows the whole request without
 * opening another page.
 */
class StaffAlert extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array{name?:?string,email?:?string,phone?:?string}  $from
     * @param  array<string,string|int|float|null>  $details
     */
    public function __construct(
        public string $category,
        public string $title,
        public ?string $body = null,
        public array $from = [],
        public ?string $link = null,
        public array $details = [],
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)->subject('[AIPrice.app] '.$this->title)->line($this->title);
        $who = trim(implode(' · ', array_filter([$this->from['name'] ?? null, $this->from['email'] ?? null, $this->from['phone'] ?? null])));
        if ($who !== '') {
            $mail->line('From: '.$who);
        }
        foreach ($this->details as $label => $value) {
            if ($value !== null && $value !== '') {
                $mail->line("{$label}: {$value}");
            }
        }
        if ($this->body) {
            $mail->line('---')->line($this->body)->line('---');
        }

        return $mail->action('Open in admin', rtrim((string) config('app.frontend_url'), '/').($this->link ?: '/admin/inbox'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'staff_alert',
            'category' => $this->category,
            'title' => $this->title,
            'body' => $this->body,
            'from' => array_filter($this->from, fn ($v) => $v !== null && $v !== ''),
            'details' => array_filter($this->details, fn ($v) => $v !== null && $v !== ''),
            'link' => $this->link,
        ];
    }
}
