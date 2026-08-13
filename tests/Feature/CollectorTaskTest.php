<?php

use App\Models\CollectorTask;
use App\Models\CollectorTaskMessage;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy;
use Database\Seeders\CapabilitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

/** @return array{Tenant, User, User, User, Customer} */
function collectorTaskWorkspace(): array
{
    $tenant = Tenant::factory()->create(['name' => 'Taskline', 'slug' => 'taskline']);
    app(Tenancy::class)->set($tenant);
    $manager = User::create([
        'tenant_id' => $tenant->id,
        'name' => 'Task Manager',
        'email' => 'task-manager@example.test',
        'password' => Hash::make('password'),
        'role' => 'operations_manager',
    ]);
    $collector = User::create([
        'tenant_id' => $tenant->id,
        'name' => 'Nadia Collector',
        'email' => 'task-collector@example.test',
        'password' => Hash::make('password'),
        'role' => 'collector',
    ]);
    $otherCollector = User::create([
        'tenant_id' => $tenant->id,
        'name' => 'Other Collector',
        'email' => 'task-other@example.test',
        'password' => Hash::make('password'),
        'role' => 'collector',
    ]);
    app(CapabilitySeeder::class)->run();
    app(Tenancy::class)->set($tenant);
    $manager->assignRole('operations_manager');
    $collector->assignRole('collector');
    $otherCollector->assignRole('collector');
    $customer = Customer::factory()->create(['first_name' => 'Route', 'last_name' => 'Customer']);

    return [$tenant, $manager, $collector, $otherCollector, $customer];
}

it('lets an operations manager assign customer-linked collector work', function (): void {
    [$tenant, $manager, $collector, , $customer] = collectorTaskWorkspace();

    $this->actingAs($manager)
        ->get(route('operations.collector-tasks'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Operations/CollectorTasks')
            ->where('collectors.0.name', $collector->name)
            ->where('customers.0.name', $customer->full_name));

    $this->actingAs($manager)
        ->post(route('operations.collector-tasks.store'), [
            'collector_id' => $collector->id,
            'customer_id' => $customer->id,
            'title' => 'Confirm roof access',
            'description' => 'Call before arriving and report the access condition.',
            'priority' => 'high',
            'due_at' => now($tenant->timezone)->addDay()->format('Y-m-d H:i:s'),
        ])
        ->assertRedirect()
        ->assertSessionHas('success', 'Collector task created.');

    app(Tenancy::class)->set($tenant);
    $task = CollectorTask::query()->firstOrFail();
    expect($task->collector_id)->toBe($collector->id)
        ->and($task->customer_id)->toBe($customer->id)
        ->and($task->priority)->toBe('high')
        ->and($task->status)->toBe('assigned');
});

it('supports ordered collector status transitions and a two-way task conversation', function (): void {
    [$tenant, $manager, $collector, , $customer] = collectorTaskWorkspace();
    $task = CollectorTask::create([
        'collector_id' => $collector->id,
        'created_by_id' => $manager->id,
        'customer_id' => $customer->id,
        'title' => 'Verify signal level',
        'priority' => 'urgent',
    ]);
    CollectorTaskMessage::create([
        'collector_task_id' => $task->id,
        'author_id' => $manager->id,
        'body' => 'Please send the result before checkout.',
    ]);

    $this->actingAs($collector)
        ->get(route('field.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('tasks.0.id', $task->public_id)
            ->where('tasks.0.unread', true)
            ->where('tasks.0.messages.0.body', 'Please send the result before checkout.'));

    $this->actingAs($collector)
        ->patchJson(route('field.tasks.status', $task), ['status' => 'in_progress'])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Complete the task workflow in order.');
    $this->actingAs($collector)->patchJson(route('field.tasks.status', $task), ['status' => 'acknowledged'])->assertOk();
    $this->actingAs($collector)->patchJson(route('field.tasks.status', $task), ['status' => 'in_progress'])->assertOk();
    $this->actingAs($collector)
        ->postJson(route('field.tasks.messages.store', $task), ['body' => 'Signal is -58 dBm.'])
        ->assertCreated()
        ->assertJsonPath('data.messages.1.author.is_viewer', true);
    $this->actingAs($collector)->patchJson(route('field.tasks.status', $task), ['status' => 'completed'])->assertOk();

    app(Tenancy::class)->set($tenant);
    expect($task->refresh()->status)->toBe('completed')
        ->and($task->completed_at)->not->toBeNull()
        ->and($task->messages()->count())->toBe(2);

    $this->actingAs($manager)
        ->get(route('operations.collector-tasks', ['status' => 'completed', 'task' => $task->public_id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('selectedTask.status', 'completed')
            ->where('selectedTask.messages.1.body', 'Signal is -58 dBm.'));
});

it('does not expose a task to another collector', function (): void {
    [, $manager, $collector, $otherCollector] = collectorTaskWorkspace();
    $task = CollectorTask::create([
        'collector_id' => $collector->id,
        'created_by_id' => $manager->id,
        'title' => 'Private assignment',
        'priority' => 'normal',
    ]);

    $this->actingAs($otherCollector)
        ->postJson(route('field.tasks.messages.store', $task), ['body' => 'Not mine'])
        ->assertNotFound();
    $this->actingAs($otherCollector)
        ->patchJson(route('field.tasks.status', $task), ['status' => 'acknowledged'])
        ->assertNotFound();
});
