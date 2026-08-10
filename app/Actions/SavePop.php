<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\Pop;

final readonly class SavePop implements Action
{
    /** @param array<string, mixed> $data */
    public function handle(array $data, ?Pop $pop = null): Pop
    {
        $pop ??= new Pop;
        $pop->fill([
            'name' => trim((string) $data['name']),
            'code' => strtoupper(trim((string) $data['code'])),
            'address' => filled($data['address'] ?? null) ? trim((string) $data['address']) : null,
            'status' => $data['status'],
        ]);
        $pop->save();

        return $pop;
    }
}
