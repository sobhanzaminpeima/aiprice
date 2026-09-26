<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SupportTicket extends Model
{
    use HasUuids, SoftDeletes;

    public const STATUSES = ['open', 'in_progress', 'waiting', 'resolved', 'closed'];
    public const PRIORITIES = ['low', 'medium', 'high', 'urgent'];

    protected $fillable = [
        'user_id', 'guest_name', 'guest_email', 'source', 'assigned_to', 'ticket_number',
        'subject', 'description', 'category', 'priority', 'status',
    ];

    /**
     * Who to notify about this ticket: the account holder, or — for a guest
     * contact-form ticket — an on-demand mail route to the address they gave.
     */
    public function requester(): ?object
    {
        if ($this->user) {
            return $this->user;
        }

        return $this->guest_email
            ? \Illuminate\Support\Facades\Notification::route('mail', [$this->guest_email => $this->guest_name])
            : null;
    }

    protected static function booted(): void
    {
        static::creating(function (SupportTicket $t) {
            $t->ticket_number ??= 'T-'.now()->format('ymd').'-'.strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(SupportTicketReply::class, 'ticket_id')->orderBy('created_at');
    }
}
