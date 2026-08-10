<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * @property int $account_id
 * @property int $customer_id
 * @property string $currency
 * @property int $debit_amount
 * @property int $credit_amount
 */
class JournalLine extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'journal_entry_id', 'account_id', 'customer_id', 'currency', 'debit_amount', 'credit_amount', 'memo'];

    protected function casts(): array
    {
        return ['debit_amount' => 'integer', 'credit_amount' => 'integer'];
    }

    protected static function booted(): void
    {
        static::updating(fn (): never => throw new LogicException('Journal lines are append-only.'));
        static::deleting(fn (): never => throw new LogicException('Journal lines are append-only.'));
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(LedgerAccount::class, 'account_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
