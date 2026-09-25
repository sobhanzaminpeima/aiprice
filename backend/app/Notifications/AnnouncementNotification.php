<?php

namespace App\Notifications;

use App\Models\Announcement;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AnnouncementNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Announcement $announcement)
    {
    }

    public function via(object $notifiable): array
    {
        return $this->announcement->channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)->subject($this->announcement->title)->line($this->announcement->body);

        return $this->announcement->link ? $mail->action('Learn more', $this->announcement->link) : $mail;
    }

    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'announcement',
            'announcement_id' => $this->announcement->id,
            'title' => $this->announcement->title,
            'body' => $this->announcement->body,
            'link' => $this->announcement->link,
        ];
    }
}
