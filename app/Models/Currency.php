<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Currency extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'code', 'name', 'decimal_digits', 'is_base', 'is_collection', 'is_active'];

    protected function casts(): array
    {
        return ['decimal_digits' => 'integer', 'is_base' => 'boolean', 'is_collection' => 'boolean', 'is_active' => 'boolean'];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
