<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'user_id', 'payment_id', 'invoice_number', 'subtotal', 'tax', 'total',
        'currency', 'status', 'due_date', 'paid_at', 'items',
    ];

    protected function casts(): array
    {
        return ['items' => 'array', 'due_date' => 'date', 'paid_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
