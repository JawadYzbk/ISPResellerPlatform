<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RadiusGroupReply extends Model
{
    use BelongsToTenant;

    protected $table = 'radgroupreply';

    protected $fillable = ['tenant_id', 'groupname', 'attribute', 'op', 'value'];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
