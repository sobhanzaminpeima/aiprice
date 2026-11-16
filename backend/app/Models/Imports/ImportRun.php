<?php

namespace App\Models\Imports;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportRun extends Model
{
    use HasUuids;

    public const COUNTERS = [
        'candidates_discovered', 'candidates_fetched', 'eligible', 'selected', 'imported',
        'skipped_duplicates', 'rejected', 'images_stored', 'contacts_pending', 'errors_count',
    ];

    protected $fillable = [
        'import_source_id', 'triggered_by', 'mode', 'status', 'target',
        'candidates_discovered', 'candidates_fetched', 'eligible', 'selected', 'imported',
        'skipped_duplicates', 'rejected', 'images_stored', 'contacts_pending', 'errors_count',
        'report', 'error', 'started_at', 'finished_at',
    ];

    protected function casts(): array
    {
        return ['report' => 'array', 'started_at' => 'datetime', 'finished_at' => 'datetime'];
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(ImportSource::class, 'import_source_id');
    }

    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by');
    }
}
