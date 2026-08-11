<?php

namespace App\Http\Controllers\Web;

use App\Actions\GetCollectorShift;
use App\Actions\GetCollectorSummary;
use App\Actions\GetCollectorSyncSnapshot;
use App\Actions\GetCurrencyCatalog;
use App\Actions\PushCollectorSync;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

final class FieldController extends Controller
{
    public function index(
        Request $request,
        GetCollectorShift $shift,
        GetCollectorSummary $summary,
        GetCollectorSyncSnapshot $snapshot,
        GetCurrencyCatalog $currencyCatalog,
    ): Response {
        $user = $this->collector($request);
        $tenant = $this->tenant();

        return Inertia::render('Field/Index', [
            'snapshot' => $snapshot->handle($tenant, $user, null, null),
            'shift' => $shift->handle($user),
            'summary' => $summary->handle($user, now()->toDateString()),
            'currencies' => $currencyCatalog->handle(),
            'defaultCurrency' => $tenant->collection_currency,
            'storageKey' => 'field:'.$tenant->public_id.':'.$user->id,
        ]);
    }

    public function sync(Request $request, GetCollectorSyncSnapshot $snapshot): JsonResponse
    {
        $user = $this->collector($request);
        $validated = $request->validate([
            'since' => ['nullable', 'string', 'max:2048'],
            'zone' => ['nullable', 'string', 'max:100'],
        ]);

        try {
            return response()->json($snapshot->handle(
                $this->tenant(),
                $user,
                $validated['zone'] ?? null,
                $validated['since'] ?? null,
            ));
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }

    public function push(Request $request, PushCollectorSync $push): JsonResponse
    {
        $user = $this->collector($request);
        $items = $request->input('items');
        abort_unless(is_array($items) && count($items) > 0 && count($items) <= 100, 422, 'Provide between one and one hundred queued payments.');

        return response()->json($push->handle(array_values($items), $user));
    }

    private function collector(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        abort_unless($user->can('customers.view') && $user->can('payments.collect'), 403);

        return $user;
    }

    private function tenant(): Tenant
    {
        return Tenant::query()->findOrFail(app(Tenancy::class)->requireId());
    }
}
