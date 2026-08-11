<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RadiusNas extends Model
{
    use BelongsToTenant;

    protected $table = 'nas';

    protected $fillable = ['tenant_id', 'nasname', 'shortname', 'type', 'ports', 'secret', 'server', 'community', 'description', 'coa_port'];

    protected $hidden = ['secret'];

    protected function casts(): array
    {
        return ['coa_port' => 'integer', 'ports' => 'integer'];
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
