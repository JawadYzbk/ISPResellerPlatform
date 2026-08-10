<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Spatie\Activitylog\Models\Activity;

/**
 * @property int|null $tenant_id
 * @property string|null $ip_address
 * @property string|null $request_id
 */
class AuditEvent extends Activity
{
    use BelongsToTenant;

    protected $table = 'activity_log';

    protected function casts(): array
    {
        return ['properties' => 'collection'];
    }
}
