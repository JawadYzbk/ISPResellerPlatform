<?php

namespace App\Http\Controllers\Web;

use App\Actions\AttachServiceAddon;
use App\Actions\CancelServiceAddon;
use App\Actions\CancelServiceBillingCycleChange;
use App\Actions\CancelServicePlanChange;
use App\Actions\ChangeServicePlan;
use App\Actions\CreateService;
use App\Actions\EnqueueNetworkCommand;
use App\Actions\GetTechnicianServiceDiagnostics;
use App\Actions\ListServices;
use App\Actions\PreviewServiceBillingCycle;
use App\Actions\PreviewServicePlanChange;
use App\Actions\ReturnInventoryUnit;
use App\Actions\ScheduleServiceBillingCycle;
use App\Actions\TransitionService;
use App\Enums\ServiceStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\CreateServiceRequest;
use App\Models\Addon;
use App\Models\CurrentSession;
use App\Models\Customer;
use App\Models\InventoryUnit;
use App\Models\Plan;
use App\Models\Router;
use App\Models\Service;
use App\Models\ServiceAddon;
use App\Models\User;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final class ServiceController extends Controller
{
    public function show(Service $service, GetTechnicianServiceDiagnostics $diagnostics): Response
    {
        $this->authorize('view', $service);
        $service->load(['customer', 'plan', 'router', 'assignedInventoryUnits.item', 'serviceAddons.addon']);
        $diagnosticData = $diagnostics->handle($service);

        return Inertia::render('Services/Show', [
            'service' => [
                'public_id' => $service->public_id,
                'username' => $service->username,
                'status' => $service->status->value,
                'network_state' => $service->network_state->value,
                'provisioning_mode' => $service->provisioning_mode->value,
                'expires_at' => $service->expires_at?->toIso8601String(),
                'billing_anchor_day' => $service->billing_anchor_day,
                'suspension_reason' => $service->suspension_reason,
                'paused_until' => $service->paused_until?->toIso8601String(),
                'customer' => $service->customer?->only(['public_id', 'code', 'first_name', 'last_name']),
                'plan' => $service->plan?->only(['id', 'public_id', 'name', 'download_kbps', 'upload_kbps', 'amount_minor', 'currency']),
                'router' => $service->router?->only(['public_id', 'name', 'status']),
                'usage' => [
                    'used_bytes' => $service->current_period_bytes,
                    'quota_bytes' => (int) ($service->plan?->metadata['quota_bytes'] ?? 0),
                ],
                'equipment' => $service->assignedInventoryUnits->map(fn ($unit): array => [
                    'id' => $unit->id,
                    'serial_number' => $unit->serial_number,
                    'assigned_at' => $unit->assigned_at?->toIso8601String(),
                    'item' => $unit->item?->only(['sku', 'name']),
                ])->values()->all(),
                'addons' => $service->serviceAddons->map(fn (ServiceAddon $serviceAddon): array => [
                    'public_id' => $serviceAddon->public_id,
                    'name' => $serviceAddon->addon?->name,
                    'description' => $serviceAddon->addon?->description,
                    'amount_minor' => $serviceAddon->addon?->amount_minor,
                    'currency' => $serviceAddon->addon?->currency,
                    'billing_period_days' => $serviceAddon->addon?->billing_period_days,
                    'quantity' => $serviceAddon->quantity,
                    'starts_at' => $serviceAddon->starts_at->toDateString(),
                    'ends_at' => $serviceAddon->ends_at?->toDateString(),
                    'status' => $serviceAddon->status,
                ])->values()->all(),
                'pending_plan_change' => $this->pendingPlanChange($service),
                'pending_billing_cycle' => $this->pendingBillingCycle($service),
            ],
            'liveSession' => $diagnosticData['live_session'],
            'usageLast24h' => $diagnosticData['usage_last_24h'],
            'routerHealth' => $diagnosticData['router_health'],
            'recentCommands' => $diagnosticData['recent_commands'],
            'canActivate' => request()->user()?->can('services.activate') === true,
            'canSuspend' => request()->user()?->can('services.suspend') === true,
            'canPause' => request()->user()?->can('services.pause') === true,
            'canTerminate' => request()->user()?->can('services.terminate') === true,
            'canChangePlan' => request()->user()?->can('services.change_plan') === true,
            'canChangeBillingCycle' => request()->user()?->can('services.change_plan') === true,
            'canDisconnectSession' => request()->user()?->can('network.disconnect') === true,
            'plans' => Plan::query()
                ->where('status', 'active')
                ->where('id', '<>', $service->plan_id)
                ->orderBy('name')
                ->get(['id', 'public_id', 'name', 'download_kbps', 'upload_kbps', 'duration_days', 'amount_minor', 'currency']),
            'availableAddons' => Addon::query()
                ->where('status', 'active')
                ->orderBy('name')
                ->get(['public_id', 'name', 'description', 'amount_minor', 'currency', 'billing_period_days'])
                ->values(),
        ]);
    }

    public function attachAddon(Request $request, Service $service, AttachServiceAddon $attach): RedirectResponse
    {
        $this->authorize('changePlan', $service);
        $validated = $request->validate([
            'addon_id' => ['required', 'string', 'max:26'],
            'quantity' => ['required', 'integer', 'min:1', 'max:1000'],
            'starts_at' => ['nullable', 'date_format:Y-m-d'],
            'ends_at' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:starts_at'],
        ]);
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $addon = Addon::query()->where('public_id', $validated['addon_id'])->firstOrFail();

        try {
            $attach->handle(
                $service,
                $addon,
                (int) $validated['quantity'],
                CarbonImmutable::createFromFormat('!Y-m-d', (string) ($validated['starts_at'] ?? now()->toDateString())),
                filled($validated['ends_at'] ?? null)
                    ? CarbonImmutable::createFromFormat('!Y-m-d', (string) $validated['ends_at'])
                    : null,
                $user,
            );
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['addon_id' => $exception->getMessage()]);
        }

        return redirect()->route('services.show', $service->public_id)
            ->with('success_title', 'Add-on added')
            ->with('success', 'Recurring add-on attached to the service.');
    }

    public function cancelAddon(Request $request, Service $service, ServiceAddon $serviceAddon, CancelServiceAddon $cancel): RedirectResponse
    {
        $this->authorize('changePlan', $service);
        abort_unless((int) $serviceAddon->service_id === (int) $service->id, 404);
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        try {
            $cancel->handle($serviceAddon, $user);
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['addon' => $exception->getMessage()]);
        }

        return redirect()->route('services.show', $service->public_id)
            ->with('success_title', 'Add-on cancelled')
            ->with('success', 'The recurring add-on will no longer renew.');
    }

    public function changePlan(Request $request, Service $service, ChangeServicePlan $change): RedirectResponse
    {
        $this->authorize('changePlan', $service);
        $validated = $request->validate([
            'plan_id' => ['required', 'integer', 'exists:plans,id'],
            'effective' => ['required', 'string', 'in:immediate,next_cycle'],
        ]);
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $plan = Plan::query()->whereKey((int) $validated['plan_id'])->where('status', 'active')->firstOrFail();

        try {
            $change->handle($service, $plan, (string) $validated['effective'], $user);
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['plan_id' => $exception->getMessage()]);
        }

        return $this->redirectToCustomer($service, $validated['effective'] === 'immediate'
            ? 'Service plan changed and network synchronization queued.'
            : 'Service plan change scheduled for the next renewal.');
    }

    public function planChangePreview(Request $request, Service $service, PreviewServicePlanChange $preview): JsonResponse
    {
        $this->authorize('changePlan', $service);
        $validated = $request->validate([
            'plan_id' => ['required', 'integer', 'exists:plans,id'],
            'effective' => ['required', 'string', 'in:immediate,next_cycle'],
        ]);
        $plan = Plan::query()->whereKey((int) $validated['plan_id'])->where('status', 'active')->firstOrFail();

        try {
            return response()->json($preview->handle($service, $plan, (string) $validated['effective']));
        } catch (DomainException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }

    public function cancelPlan(Request $request, Service $service, CancelServicePlanChange $cancel): RedirectResponse
    {
        $this->authorize('changePlan', $service);
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        try {
            $cancel->handle($service, $user);
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['plan_id' => $exception->getMessage()]);
        }

        return $this->redirectToCustomer($service, 'Scheduled service plan change cancelled.');
    }

    public function billingCyclePreview(Request $request, Service $service, PreviewServiceBillingCycle $preview): JsonResponse
    {
        $this->authorize('changePlan', $service);
        $validated = $request->validate(['anchor_day' => ['required', 'integer', 'between:1,31']]);

        try {
            return response()->json($preview->handle($service, (int) $validated['anchor_day'])->toArray());
        } catch (DomainException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }

    public function scheduleBillingCycle(Request $request, Service $service, ScheduleServiceBillingCycle $schedule): RedirectResponse
    {
        $this->authorize('changePlan', $service);
        $validated = $request->validate(['anchor_day' => ['required', 'integer', 'between:1,31']]);
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        try {
            $updated = $schedule->handle($service, (int) $validated['anchor_day'], $user);
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['anchor_day' => $exception->getMessage()]);
        }

        return redirect()->route('services.show', $updated->public_id)->with('success', $updated->expires_at === null
            ? 'Service billing anchor updated.'
            : 'Service billing-cycle change scheduled for the next renewal.');
    }

    public function cancelBillingCycle(Request $request, Service $service, CancelServiceBillingCycleChange $cancel): RedirectResponse
    {
        $this->authorize('changePlan', $service);
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        try {
            $cancel->handle($service, $user);
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['anchor_day' => $exception->getMessage()]);
        }

        return redirect()->route('services.show', $service->public_id)->with('success', 'Scheduled billing-cycle change cancelled.');
    }

    public function create(Customer $customer): Response
    {
        $this->authorize('create', Service::class);

        return Inertia::render('Services/Create', [
            'customer' => $customer->only(['public_id', 'code', 'first_name', 'last_name']),
            'plans' => Plan::query()->where('status', 'active')->orderBy('name')->get(['id', 'public_id', 'name', 'download_kbps', 'upload_kbps', 'duration_days', 'amount_minor', 'currency']),
            'routers' => Router::query()->orderBy('name')->get(['id', 'public_id', 'name']),
        ]);
    }

    public function store(CreateServiceRequest $request, Customer $customer, CreateService $createService): RedirectResponse
    {
        $this->authorize('create', Service::class);

        $service = $createService->handle($customer, $request->validated(), $request->user());

        return redirect()->route('customers.show', $customer->public_id)->with('success', "Service {$service->username} created and awaiting activation.");
    }

    public function activate(Request $request, Service $service, TransitionService $transition, EnqueueNetworkCommand $enqueue): RedirectResponse
    {
        $this->authorize('activate', $service);
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $updated = $transition->handle($service, ServiceStatus::Active, $user, ['reason' => 'manual_operator']);
        $enqueue->handle($updated, 'activate', ['reason' => 'manual_operator']);

        return $this->redirectToCustomer($service, 'Service activation queued.');
    }

    public function suspend(Request $request, Service $service, TransitionService $transition, EnqueueNetworkCommand $enqueue): RedirectResponse
    {
        $this->authorize('suspend', $service);
        $validated = $request->validate(['reason' => ['required', 'string', 'max:64'], 'note' => ['nullable', 'string', 'max:5000']]);
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $updated = $transition->handle($service, ServiceStatus::Suspended, $user, $validated);
        $enqueue->handle($updated, 'suspend', $validated);

        return $this->redirectToCustomer($service, 'Service suspension queued.');
    }

    public function pause(Request $request, Service $service, TransitionService $transition, EnqueueNetworkCommand $enqueue): RedirectResponse
    {
        $this->authorize('pause', $service);
        abort_unless($service->status === ServiceStatus::Active, 422, 'Only active services can be paused.');
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:64'],
            'resume_at' => ['nullable', 'date', 'after:now'],
        ]);
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $updated = $transition->handle($service, ServiceStatus::Paused, $user, $validated);
        $enqueue->handle($updated, 'pause', $validated);

        return $this->redirectToCustomer($service, 'Service pause queued.');
    }

    public function resume(Request $request, Service $service, TransitionService $transition, EnqueueNetworkCommand $enqueue): RedirectResponse
    {
        $this->authorize('activate', $service);
        if ($service->status !== ServiceStatus::Paused && $service->suspension_reason !== 'auto_overdue') {
            abort_unless($request->user()?->can('services.force_resume') === true, 403);
        }
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $updated = $transition->handle($service, ServiceStatus::Active, $user, ['reason' => 'manual_resume']);
        $enqueue->handle($updated, 'activate', ['reason' => 'manual_resume']);

        return $this->redirectToCustomer($service, 'Service reactivation queued.');
    }

    public function terminate(Request $request, Service $service, TransitionService $transition, EnqueueNetworkCommand $enqueue): RedirectResponse
    {
        $this->authorize('terminate', $service);
        $validated = $request->validate(['reason' => ['required', 'string', 'max:5000']]);
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $updated = $transition->handle($service, ServiceStatus::Terminated, $user, $validated);
        $enqueue->handle($updated, 'disconnect', ['reason' => 'service_terminated']);

        return $this->redirectToCustomer($service, 'Service terminated and network disconnect queued.');
    }

    public function resync(Request $request, Service $service, EnqueueNetworkCommand $enqueue): RedirectResponse
    {
        $action = match ($service->status) {
            ServiceStatus::Suspended => 'suspend',
            ServiceStatus::Paused => 'pause',
            default => 'activate',
        };
        $this->authorize($action, $service);
        abort_if($service->status === ServiceStatus::Terminated, 422, 'Terminated services cannot be re-synced.');

        $enqueue->handle($service, $action, ['reason' => 'manual_resync']);

        return redirect()->route('customers.show', $service->customer->public_id)->with('success', "Service {$service->username} queued for network re-sync.");
    }

    public function disconnectSession(Service $service, EnqueueNetworkCommand $enqueue): RedirectResponse
    {
        $this->authorize('disconnect', $service);
        $session = CurrentSession::query()
            ->where('service_id', $service->id)
            ->whereNull('stopped_at')
            ->latest('last_seen_at')
            ->first();
        $payload = array_filter([
            'reason' => 'operator_disconnect',
            'session_id' => $session?->acct_session_id,
        ], static fn (mixed $value): bool => $value !== null && $value !== '');
        $enqueue->handle($service, 'disconnect', $payload);

        return $this->redirectToCustomer($service, 'Current network session disconnect queued.');
    }

    public function returnEquipment(Request $request, Service $service, InventoryUnit $unit, ReturnInventoryUnit $return): RedirectResponse
    {
        $this->authorize('view', $service);
        $user = $request->user();
        abort_unless($user instanceof User && $user->can('inventory.assign'), 403);

        try {
            $return->handle($unit, $service, $user);
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['unit' => $exception->getMessage()]);
        }

        return $this->redirectToCustomer($service, "Equipment {$unit->serial_number} marked returned.");
    }

    private function redirectToCustomer(Service $service, string $message): RedirectResponse
    {
        return redirect()->route('customers.show', $service->customer->public_id)->with('success', $message);
    }

    /** @return array<string, mixed>|null */
    private function pendingPlanChange(Service $service): ?array
    {
        $pending = $service->metadata['pending_plan_change'] ?? null;
        if (! is_array($pending) || ! isset($pending['plan_id'])) {
            return null;
        }

        $plan = Plan::query()->whereKey((int) $pending['plan_id'])->first();
        if (! $plan instanceof Plan || $plan->tenant_id !== $service->tenant_id) {
            return null;
        }

        return [
            'plan' => $plan->only(['public_id', 'name', 'download_kbps', 'upload_kbps', 'duration_days']),
            'requested_at' => $pending['requested_at'] ?? null,
            'apply_at' => $service->expires_at?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed>|null */
    private function pendingBillingCycle(Service $service): ?array
    {
        $pending = $service->metadata['pending_billing_cycle'] ?? null;
        if (! is_array($pending) || ! isset($pending['anchor_day'], $pending['ends_at'], $pending['prorated_amount'], $pending['currency'])) {
            return null;
        }

        return [
            'anchor_day' => (int) $pending['anchor_day'],
            'starts_at' => $pending['starts_at'] ?? null,
            'ends_at' => $pending['ends_at'],
            'billable_days' => (int) ($pending['billable_days'] ?? 0),
            'cycle_days' => (int) ($pending['cycle_days'] ?? 0),
            'full_amount' => (int) ($pending['full_amount'] ?? 0),
            'prorated_amount' => (int) $pending['prorated_amount'],
            'currency' => (string) $pending['currency'],
            'requested_at' => $pending['requested_at'] ?? null,
        ];
    }

    public function index(Request $request, ListServices $listServices): Response
    {
        $this->authorize('viewAny', Service::class);

        return Inertia::render('Services/Index', [
            'services' => $listServices->handle($request->string('search')->toString() ?: null, $request->string('status')->toString() ?: null),
            'filters' => $request->only(['search', 'status']),
        ]);
    }
}
