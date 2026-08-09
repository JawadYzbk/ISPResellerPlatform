<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class LedgerEntry extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'customer_id', 'journal_line_id', 'currency', 'debit_amount', 'credit_amount', 'balance_after', 'occurred_at'];

    protected function casts(): array
    {
        return ['debit_amount' => 'integer', 'credit_amount' => 'integer', 'balance_after' => 'integer', 'occurred_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn (): never => throw new LogicException('Ledger projections are append-only.'));
        static::deleting(fn (): never => throw new LogicException('Ledger projections are append-only.'));
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function journalLine(): BelongsTo
    {
        return $this->belongsTo(JournalLine::class);
    }
}
