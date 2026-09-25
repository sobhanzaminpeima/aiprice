<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class UserSearchPreference extends Model
{
    use HasUuids;

    protected $fillable = ['user_id', 'asset_type', 'budget_max', 'city', 'priorities', 'source'];

    protected function casts(): array
    {
        return [
            'priorities' => 'array',
            'budget_max' => 'decimal:2',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
