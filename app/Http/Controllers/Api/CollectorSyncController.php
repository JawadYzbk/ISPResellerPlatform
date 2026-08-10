<?php

namespace App\Http\Controllers\Api;

use App\Actions\GetCollectorSyncSnapshot;
use App\Actions\PushCollectorSync;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

final class CollectorSyncController extends Controller
{
    public function bootstrap(Request $request, GetCollectorSyncSnapshot $snapshot): JsonResponse
    {
        return $this->snapshot($request, $snapshot, null);
    }

    public function delta(Request $request, GetCollectorSyncSnapshot $snapshot): JsonResponse
    {
        $validated = $request->validate(['since' => ['required', 'string', 'max:2048'], 'zone' => ['nullable', 'string', 'max:100']]);

        return $this->snapshot($request, $snapshot, $validated['since'], $validated['zone'] ?? null);
    }

    public function push(Request $request, PushCollectorSync $push): JsonResponse
    {
        $payload = $request->all();
        $items = is_array($payload) && array_is_list($payload) ? $payload : $request->input('items');
        abort_unless(is_array($items) && count($items) > 0 && count($items) <= 100, 422, 'Provide between one and one hundred queued payments.');

        return response()->json($push->handle(array_values($items), $this->user($request)));
    }

    private function snapshot(Request $request, GetCollectorSyncSnapshot $snapshot, ?string $since, ?string $zone = null): JsonResponse
    {
        $validated = $zone === null ? $request->validate(['zone' => ['nullable', 'string', 'max:100']]) : ['zone' => $zone];

        try {
            return response()->json($snapshot->handle($this->tenant(), $this->user($request), $validated['zone'] ?? null, $since));
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }

    private function tenant(): Tenant
    {
        return Tenant::query()->findOrFail(app(Tenancy::class)->requireId());
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
