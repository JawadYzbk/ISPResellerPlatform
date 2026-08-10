<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\Addon;

final readonly class ArchiveAddon implements Action
{
    public function handle(Addon $addon): Addon
    {
        $addon->forceFill(['status' => 'inactive'])->save();

        return $addon->refresh();
    }
}
