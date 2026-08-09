<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Spatie\Activitylog\Models\Activity;

class AuditEvent extends Activity
{
    use BelongsToTenant;

    protected $table = 'activity_log';

    protected function casts(): array
    {
        return ['properties' => 'collection'];
    }
}
