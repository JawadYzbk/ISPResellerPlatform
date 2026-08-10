<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property array<string, string> $filters
 * @property list<string> $columns
 */
class CustomerSavedView extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'user_id', 'name', 'filters', 'columns'];

    protected function casts(): array
    {
        return ['filters' => 'array', 'columns' => 'array'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
