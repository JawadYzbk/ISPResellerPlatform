<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommissionEntry extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'partner_id', 'source_type', 'source_id', 'rule_version', 'amount_minor', 'currency', 'journal_entry_id', 'status'];

    protected function casts(): array
    {
        return ['rule_version' => 'integer', 'amount_minor' => 'integer'];
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }
}
