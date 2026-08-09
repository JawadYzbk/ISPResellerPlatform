<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RadiusUser extends Model
{
    use BelongsToTenant;

    protected $table = 'radius_users';

    protected $fillable = ['tenant_id', 'service_id', 'username', 'attribute', 'op', 'value'];

    protected $hidden = ['value'];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}
