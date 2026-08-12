<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

final readonly class UpdateTenantUserRole implements Action
{
    /** @param array{role: string} $data */
    public function handle(User $actor, User $member, array $data): User
    {
        if ($actor->tenant_id === null || $actor->tenant_id !== $member->tenant_id) {
            throw new DomainException('The operator does not belong to this workspace.');
        }

        if (! $actor->can('roles.manage')) {
            throw new DomainException('You are not allowed to change operator roles.');
        }

        if ($actor->is($member)) {
            throw new DomainException('You cannot change your own role.');
        }

        if (in_array((string) $member->role, ['admin', 'platform_operator', 'tenant_owner'], true)) {
            throw new DomainException('Protected workspace roles must be changed through a break-glass procedure.');
        }

        $role = (string) $data['role'];
        if (! in_array($role, $this->editableRoles(), true)) {
            throw new DomainException('The selected operator role is not available.');
        }

        return DB::transaction(function () use ($member, $role): User {
            $locked = User::query()->lockForUpdate()->findOrFail($member->id);
            $locked->forceFill(['role' => $role])->save();
            $locked->syncRoles([$role]);

            return $locked->refresh();
        });
    }

    /** @return list<string> */
    private function editableRoles(): array
    {
        return [
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
    }
}
