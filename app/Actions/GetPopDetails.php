<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\Pop;

final readonly class GetPopDetails implements Action
{
    public function handle(Pop $pop): Pop
    {
        return $pop->load(['routers', 'upstreamLinks']);
    }
}
