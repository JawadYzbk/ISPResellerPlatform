<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateUserRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'role' => ['required', 'string', 'in:'.implode(',', InviteUserRequest::INVITABLE_ROLES)],
        ];
    }
}
