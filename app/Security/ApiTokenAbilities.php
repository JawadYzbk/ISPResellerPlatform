<?php

namespace App\Security;

use App\Models\User;
use DomainException;

final class ApiTokenAbilities
{
    /** @return list<string> */
    public function for(User $user): array
    {
        return match ($user->role) {
            'customer' => ['customer'],
            'collector' => ['staff:collector'],
            'technician' => ['staff:technician'],
            default => ['staff:operator'],
        };
    }

    /** @param list<string> $requested @return list<string> */
    public function resolve(User $user, array $requested): array
    {
        $available = $this->for($user);
        $abilities = $requested === [] ? $available : array_values(array_unique($requested));
        if (array_diff($abilities, $available) !== []) {
            throw new DomainException('The requested API token ability is not available for this user.');
        }

        return ['api', ...$abilities];
    }
}
