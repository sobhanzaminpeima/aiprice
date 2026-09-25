<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiUsageLog extends Model
{
    use HasUuids;

    protected $fillable = [
        'model_id', 'purpose', 'entity_type', 'entity_id', 'prompt_tokens',
        'completion_tokens', 'total_tokens', 'cost', 'latency_ms', 'success', 'error_message',
    ];

    protected function casts(): array
    {
        return ['success' => 'boolean', 'cost' => 'decimal:6'];
    }

    public function model(): BelongsTo
    {
        return $this->belongsTo(AiModel::class, 'model_id');
    }
}
