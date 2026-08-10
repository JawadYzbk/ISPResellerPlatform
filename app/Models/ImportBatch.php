<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/** @property Carbon|null $completed_at */
class ImportBatch extends Model
{
    use BelongsToTenant;

    protected $table = 'imports';

    protected $fillable = ['tenant_id', 'public_id', 'type', 'filename', 'status', 'total_rows', 'successful_rows', 'failed_rows', 'report', 'completed_at', 'rolled_back_at'];

    protected function casts(): array
    {
        return ['total_rows' => 'integer', 'successful_rows' => 'integer', 'failed_rows' => 'integer', 'report' => 'array', 'completed_at' => 'datetime', 'rolled_back_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(function (self $import): void {
            $import->public_id ??= (string) Str::ulid();
        });
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
