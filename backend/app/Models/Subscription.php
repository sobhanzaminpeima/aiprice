<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Subscription extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'user_id', 'plan_id', 'status', 'billing_cycle', 'amount', 'currency',
        'trial_ends_at', 'current_period_start', 'current_period_end',
        'cancelled_at', 'payment_gateway', 'gateway_subscription_id', 'notes', 'reminder_sent_at',
    ];

    protected function casts(): array
    {
        return [
            'trial_ends_at' => 'datetime',
            'current_period_start' => 'datetime',
            'current_period_end' => 'datetime',
            'cancelled_at' => 'datetime',
            'reminder_sent_at' => 'datetime',
        ];
    }

    /** Statuses that grant the plan's limits. 'pending' means waiting for a manual payment. */
    public const LIVE = ['active', 'trial'];

    public function isLive(): bool
    {
        return in_array($this->status, self::LIVE, true)
            && (!$this->current_period_end || $this->current_period_end->isFuture());
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function plan()
    {
        return $this->belongsTo(SubscriptionPlan::class);
    }
}
