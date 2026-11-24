<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class AiProviderConnection extends Model
{
    use HasUuids;

    protected $fillable = ['provider', 'api_key', 'model', 'enabled', 'is_active', 'last_http_status', 'last_test_result', 'tested_at'];

    protected $hidden = ['api_key'];

    protected function casts(): array
    {
        return ['api_key' => 'encrypted', 'enabled' => 'boolean', 'is_active' => 'boolean', 'tested_at' => 'datetime'];
    }
}
