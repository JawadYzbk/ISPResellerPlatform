<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/** @property string $public_id */
class WalletTransaction extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'public_id', 'wallet_id', 'journal_entry_id', 'type', 'direction', 'amount', 'balance_after', 'idempotency_key', 'actor_id', 'metadata'];

    protected function casts(): array
    {
        return ['amount' => 'integer', 'balance_after' => 'integer', 'metadata' => 'array'];
    }

    protected static function booted(): void
    {
        static::creating(function (self $transaction): void {
            $transaction->public_id ??= (string) Str::ulid();
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(PartnerWallet::class, 'wallet_id');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }
}
