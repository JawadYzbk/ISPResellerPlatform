<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** @property int $balance_amount */
class PartnerWallet extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'partner_id', 'currency', 'balance_amount'];

    protected function casts(): array
    {
        return ['balance_amount' => 'integer'];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** @return BelongsTo<Partner, $this> */
    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    /** @return HasMany<WalletTransaction, $this> */
    public function transactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class, 'wallet_id');
    }
}
