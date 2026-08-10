<?php

namespace App\Http\Controllers\Api;

use App\Actions\ChangeServicePlan;
use App\Actions\EnqueueNetworkCommand;
use App\Actions\ListServicesApi;
use App\Actions\PreviewServicePlanChange;
use App\Actions\TransitionService;
use App\Enums\ServiceStatus;
use App\Http\Controllers\Controller;
use App\Models\CurrentSession;
use App\Models\NetworkCommand;
use App\Models\Plan;
use App\Models\Service;
use App\Models\User;
use App\Support\Api\NetworkCommandApiResource;
use App\Support\Api\ServiceApiResource;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ServiceApiController extends Controller
{
    public function index(Request $request, ListServicesApi $listServices): JsonResponse
    {
        abort_unless($request->user()?->can('services.view'), 403);

        return response()->json($listServices->handle($request, $request->integer('per_page', 20)));
    }

    public function show(Request $request, string $service, ServiceApiResource $resource): JsonResponse
    {
        $service = $this->find($service);
        $this->authorize('view', $service);

        return response()->json($resource->make($service));
    }

    public function networkCommands(Request $request, string $service, NetworkCommandApiResource $resource): JsonResponse
    {
        $service = $this->find($service);
        $this->authorize('view', $service);
        $limit = min(max($request->integer('limit', 20), 1), 50);
        $commands = NetworkCommand::query()
            ->where('service_id', $service->id)
            ->latest('created_at')
            ->limit($limit)
            ->get()
            ->map(fn (NetworkCommand $command): array => $resource->make($command))
            ->values();

        return response()->json(['data' => $commands]);
    }

    public function activate(Request $request, string $service, TransitionService $transition, EnqueueNetworkCommand $enqueue): JsonResponse
    {
        $service = $this->find($service);
        $this->authorize('activate', $service);

        return $this->transition($service, ServiceStatus::Active, 'activate', $request->user(), $transition, $enqueue);
    }

    public function suspend(Request $request, string $service, TransitionService $transition, EnqueueNetworkCommand $enqueue): JsonResponse
    {
        $service = $this->find($service);
        $this->authorize('suspend', $service);
        $validated = $request->validate(['reason' => ['required', 'string', 'max:64'], 'note' => ['nullable', 'string', 'max:5000']]);

        return $this->transition($service, ServiceStatus::Suspended, 'suspend', $request->user(), $transition, $enqueue, $validated);
    }

    public function resume(Request $request, string $service, TransitionService $transition, EnqueueNetworkCommand $enqueue): JsonResponse
    {
        $service = $this->find($service);
        $this->authorize('activate', $service);
        if ($service->suspension_reason !== 'auto_overdue') {
            abort_unless($request->user()?->can('services.force_resume'), 403);
        }

        return $this->transition($service, ServiceStatus::Active, 'activate', $request->user(), $transition, $enqueue, ['reason' => 'manual_resume']);
    }

    public function terminate(Request $request, string $service, TransitionService $transition, EnqueueNetworkCommand $enqueue): JsonResponse
    {
        $service = $this->find($service);
        $this->authorize('terminate', $service);
        $validated = $request->validate(['reason' => ['required', 'string', 'max:5000']]);

        return $this->transition($service, ServiceStatus::Terminated, 'disconnect', $request->user(), $transition, $enqueue, $validated);
    }

    public function disconnectSession(Request $request, string $service, EnqueueNetworkCommand $enqueue): JsonResponse
    {
        $service = $this->find($service);
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
        $command = $enqueue->handle($service, 'disconnect', $payload);

        return response()->json([
            'id' => $service->public_id,
            'status' => 'disconnect_queued',
            'network_state' => $service->network_state->value,
            'command_id' => $command->public_id,
            'session_id' => $session?->acct_session_id,
        ], 202);
    }

    public function planChangePreview(Request $request, string $service, PreviewServicePlanChange $preview): JsonResponse
    {
        $service = $this->find($service);
        $this->authorize('changePlan', $service);
        $validated = $request->validate([
            'plan_uuid' => ['required', 'string', 'exists:plans,public_id'],
            'effective' => ['required', 'string', 'in:immediate,next_cycle'],
        ]);
        $plan = Plan::query()->where('public_id', $validated['plan_uuid'])->firstOrFail();

        try {
            return response()->json($preview->handle($service, $plan, (string) $validated['effective']));
        } catch (DomainException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }

    public function changePlan(Request $request, string $service, ChangeServicePlan $change): JsonResponse
    {
        $service = $this->find($service);
        $this->authorize('changePlan', $service);
        $validated = $request->validate([
            'plan_uuid' => ['required', 'string', 'exists:plans,public_id'],
            'effective' => ['required', 'string', 'in:immediate,next_cycle'],
        ]);
        $plan = Plan::query()->where('public_id', $validated['plan_uuid'])->firstOrFail();

        try {
            $updated = $change->handle($service, $plan, (string) $validated['effective'], $request->user());
        } catch (DomainException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
        $command = (string) $validated['effective'] === 'immediate'
            ? NetworkCommand::query()->where('service_id', $updated->id)->where('action', 'change_plan')->latest('id')->first()
            : null;

        return response()->json([
            'id' => $updated->public_id,
            'status' => $updated->status->value,
            'network_state' => $updated->network_state->value,
            'plan_id' => $plan->public_id,
            'command_id' => $command?->public_id,
        ], 202);
    }

    /** @param array<string, mixed> $metadata */
    private function transition(Service $service, ServiceStatus $target, string $action, ?User $actor, TransitionService $transition, EnqueueNetworkCommand $enqueue, array $metadata = []): JsonResponse
    {
        $updated = $transition->handle($service, $target, $actor, $metadata);
        $command = $enqueue->handle($updated, $action, $metadata);

        return response()->json(['id' => $updated->public_id, 'status' => $updated->status->value, 'network_state' => $updated->network_state->value, 'command_id' => $command->public_id], 202);
    }

    private function find(string $publicId): Service
    {
        return Service::query()->where('public_id', $publicId)->firstOrFail();
    }
}
