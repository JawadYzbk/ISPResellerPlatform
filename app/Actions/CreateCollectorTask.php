<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\CollectorTask;
use App\Models\Customer;
use App\Models\User;
use App\Support\Tenancy;
use Carbon\CarbonImmutable;
use DomainException;

final readonly class CreateCollectorTask implements Action
{
    /** @param array{title: string, description?: string|null, priority: string, due_at?: string|null} $data */
    public function handle(User $actor, User $collector, ?Customer $customer, array $data): CollectorTask
    {
        $tenantId = app(Tenancy::class)->requireId();
        if (! $actor->can('reports.operations')) {
            throw new DomainException('You are not allowed to assign collector tasks.');
        }
        if ((int) $collector->tenant_id !== $tenantId || $collector->role !== 'collector') {
            throw new DomainException('Choose a collector from this workspace.');
        }
        if ($customer !== null && (int) $customer->tenant_id !== $tenantId) {
            throw new DomainException('Choose a customer from this workspace.');
        }
        if (! in_array($data['priority'], CollectorTask::PRIORITIES, true)) {
            throw new DomainException('Choose a valid task priority.');
        }

        $timezone = $actor->tenant()->value('timezone') ?: 'UTC';

        return CollectorTask::create([
            'tenant_id' => $tenantId,
            'collector_id' => $collector->id,
            'created_by_id' => $actor->id,
            'customer_id' => $customer?->id,
            'title' => trim($data['title']),
            'description' => filled($data['description'] ?? null) ? trim((string) $data['description']) : null,
            'priority' => $data['priority'],
            'status' => 'assigned',
            'due_at' => filled($data['due_at'] ?? null)
                ? CarbonImmutable::parse((string) $data['due_at'], $timezone)->utc()
                : null,
        ]);
    }
}
