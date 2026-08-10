<?php

namespace App\Http\Controllers\Api;

use App\Actions\EnqueueNetworkCommand;
use App\Actions\ListServicesApi;
use App\Actions\TransitionService;
use App\Enums\ServiceStatus;
use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ServiceApiController extends Controller
{
    public function index(Request $request, ListServicesApi $listServices): JsonResponse
    {
        abort_unless($request->user()?->can('services.view'), 403);

        return response()->json($listServices->handle($request, $request->integer('per_page', 20)));
    }

    public function show(Request $request, string $service): JsonResponse
    {
        $service = $this->find($service);
        $this->authorize('view', $service);

        return response()->json($service->load(['customer', 'plan', 'router', 'events']));
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

    /** @param array<string, mixed> $metadata */
    private function transition(Service $service, ServiceStatus $target, string $action, ?User $actor, TransitionService $transition, EnqueueNetworkCommand $enqueue, array $metadata = []): JsonResponse
    {
        $updated = $transition->handle($service, $target, $actor, $metadata);
        $command = $enqueue->handle($updated, $action, $metadata);

        return response()->json(['id' => $updated->public_id, 'status' => $updated->status->value, 'network_state' => $updated->network_state->value, 'command_id' => $command->id], 202);
    }

    private function find(string $publicId): Service
    {
        return Service::query()->where('public_id', $publicId)->firstOrFail();
    }
}
