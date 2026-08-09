<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use LogicException;

class JournalEntry extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'public_id', 'occurred_at', 'description', 'source_type', 'source_id', 'actor_id', 'posted_at'];

    protected function casts(): array
    {
        return ['occurred_at' => 'datetime', 'posted_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(function (self $entry): void {
            $entry->public_id ??= (string) Str::ulid();
        });
        static::updating(fn (): never => throw new LogicException('Journal entries are append-only.'));
        static::deleting(fn (): never => throw new LogicException('Journal entries are append-only.'));
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
