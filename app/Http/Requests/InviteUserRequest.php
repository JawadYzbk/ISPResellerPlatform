<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class InviteUserRequest extends FormRequest
{
    /** @var list<string> */
    public const INVITABLE_ROLES = [
        'operations_manager',
        'billing_manager',
        'cashier',
        'collector',
        'support_agent',
        'technician',
        'network_administrator',
        'reseller_owner',
        'reseller_staff',
        'auditor',
    ];

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email:rfc', 'max:255'],
            'role' => ['required', 'string', 'in:'.implode(',', self::INVITABLE_ROLES)],
        ];
    }
}
