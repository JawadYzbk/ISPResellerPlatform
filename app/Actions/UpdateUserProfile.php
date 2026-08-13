<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\User;

final readonly class UpdateUserProfile implements Action
{
    /** @param array{name: string, locale: string|null, timezone: string|null} $data */
    public function handle(User $user, array $data): User
    {
        $attributes = ['name' => $data['name']];
        foreach (['locale', 'timezone', 'default_view'] as $field) {
            if (array_key_exists($field, $data)) {
                $attributes[$field] = $data[$field];
            }
        }

        $user->forceFill($attributes)->save();

        return $user->refresh();
    }
}
