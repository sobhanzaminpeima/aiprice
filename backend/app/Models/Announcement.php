<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Announcement extends Model
{
    use HasUuids;

    public const AUDIENCES = ['all', 'buyers', 'sellers', 'staff'];

    protected $fillable = ['user_id', 'title', 'body', 'link', 'audience', 'channels', 'recipients_count', 'status', 'sent_at'];

    protected function casts(): array
    {
        return ['channels' => 'array', 'sent_at' => 'datetime'];
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** The active users an announcement goes to. */
    public function recipientsQuery(): Builder
    {
        return User::query()
            ->where('is_active', true)
            ->when($this->audience === 'buyers', fn ($q) => $q->whereIn('role', ['buyer', 'investor', 'premium']))
            ->when($this->audience === 'sellers', fn ($q) => $q->whereIn('role', ['seller', 'dealer', 'agency']))
            ->when($this->audience === 'staff', fn ($q) => $q->whereIn('role', User::STAFF_ROLES));
    }
}
