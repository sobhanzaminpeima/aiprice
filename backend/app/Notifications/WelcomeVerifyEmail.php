<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\URL;

/**
 * Laravel's VerifyEmail, but the link lands on the SPA, which forwards the
 * signed parameters to the API. The signature still covers id+hash+expiry.
 */
class WelcomeVerifyEmail extends VerifyEmail
{
    protected function verificationUrl($notifiable): string
    {
        // Relative signature: the SPA forwards these params to the API through
        // a proxy on another host, so the host must not be part of what's signed.
        $signed = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $notifiable->getKey(), 'hash' => sha1($notifiable->getEmailForVerification())],
            absolute: false,
        );

        $query = parse_url($signed, PHP_URL_QUERY);

        return rtrim(config('app.frontend_url'), '/')
            .'/verify-email?id='.$notifiable->getKey()
            .'&hash='.sha1($notifiable->getEmailForVerification())
            .'&'.$query;
    }
}
