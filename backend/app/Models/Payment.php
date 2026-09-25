<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payment extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'user_id', 'subscription_id', 'payment_number', 'amount', 'currency', 'status',
        'gateway', 'gateway_payment_id', 'gateway_receipt', 'gateway_response', 'notes',
    ];

    protected $hidden = ['gateway_response'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'gateway_response' => 'array'];
    }

    protected static function booted(): void
    {
        static::creating(function (Payment $p) {
            $p->payment_number ??= 'PAY-'.now()->format('ymd').'-'.strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class);
    }
}
