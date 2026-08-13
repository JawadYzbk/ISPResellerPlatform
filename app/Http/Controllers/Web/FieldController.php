<?php

namespace App\Http\Controllers\Web;

use App\Actions\GetCollectorCustodyPosition;
use App\Actions\GetCollectorShift;
use App\Actions\GetCollectorSummary;
use App\Actions\GetCollectorSyncSnapshot;
use App\Actions\GetCurrencyCatalog;
use App\Actions\PushCollectorSync;
use App\Http\Controllers\Controller;
use App\Models\CollectorCustodyEntry;
use App\Models\CollectorFieldDay;
use App\Models\CollectorRoute;
use App\Models\CollectorTask;
use App\Models\Tenant;
use App\Models\User;
use App\Support\CollectorCustodyPresenter;
use App\Support\CollectorRoutePresenter;
use App\Support\CollectorTaskPresenter;
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
        CollectorRoutePresenter $routePresenter,
        CollectorTaskPresenter $taskPresenter,
        GetCollectorCustodyPosition $custodyPosition,
        CollectorCustodyPresenter $custodyPresenter,
    ): Response {
        $user = $this->collector($request);
        $tenant = $this->tenant();
        $storageEncryptionKey = $request->session()->get('field_storage_encryption_key');
        if (! is_string($storageEncryptionKey) || $storageEncryptionKey === '') {
            $storageEncryptionKey = base64_encode(random_bytes(SODIUM_CRYPTO_AEAD_AES256GCM_KEYBYTES));
            $request->session()->put('field_storage_encryption_key', $storageEncryptionKey);
        }

        return Inertia::render('Field/Index', [
            'snapshot' => $snapshot->handle($tenant, $user, null, null),
            'shift' => $shift->handle($user),
            'summary' => $summary->handle($user, now()->toDateString()),
            'fieldDay' => $this->fieldDay($user),
            'route' => $this->route($user, $tenant, $routePresenter),
            'tasks' => CollectorTask::query()
                ->where('collector_id', $user->id)
                ->whereNotIn('status', ['completed', 'cancelled'])
                ->with(['collector:id,name,email', 'createdBy:id,name', 'customer:id,public_id,code,first_name,last_name,phone,address', 'messages.author:id,name,role', 'messages.attachments', 'reads'])
                ->orderByRaw("case priority when 'urgent' then 1 when 'high' then 2 when 'normal' then 3 else 4 end")
                ->orderBy('due_at')
                ->limit(50)
                ->get()
                ->map(fn (CollectorTask $task): array => $taskPresenter->make($task, $user))
                ->values(),
            'custody' => [
                'position' => $custodyPosition->handle($user),
                'entries' => CollectorCustodyEntry::query()
                    ->where('collector_id', $user->id)
                    ->with(['collector:id,name,email', 'requestedBy:id,name', 'reviewedBy:id,name', 'cashShift:id,public_id'])
                    ->latest('occurred_at')
                    ->limit(20)
                    ->get()
                    ->map(fn (CollectorCustodyEntry $entry): array => $custodyPresenter->entry($entry))
                    ->values(),
            ],
            'currencies' => $currencyCatalog->handle(),
            'defaultCurrency' => $tenant->collection_currency,
            'storageKey' => 'field:'.$tenant->public_id.':'.$user->id,
            'storageEncryptionKey' => $storageEncryptionKey,
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

    /** @return array<string, mixed>|null */
    private function fieldDay(User $user): ?array
    {
        $fieldDay = CollectorFieldDay::query()
            ->where('user_id', $user->id)
            ->whereNull('checked_out_at')
            ->latest('checked_in_at')
            ->first();

        return $fieldDay instanceof CollectorFieldDay ? [
            'id' => $fieldDay->public_id,
            'status' => 'active',
            'checked_in_at' => $fieldDay->checked_in_at?->toIso8601String(),
            'checked_out_at' => null,
            'check_in' => [
                'latitude' => (float) $fieldDay->check_in_latitude,
                'longitude' => (float) $fieldDay->check_in_longitude,
                'accuracy_meters' => $fieldDay->check_in_accuracy_meters,
            ],
            'check_out' => null,
        ] : null;
    }

    /** @return array<string, mixed>|null */
    private function route(User $user, Tenant $tenant, CollectorRoutePresenter $presenter): ?array
    {
        $date = now($tenant->settingsData()->timezone)->toDateString();
        $route = CollectorRoute::query()
            ->where('user_id', $user->id)
            ->whereDate('route_date', $date)
            ->with(['stops.customer.zone', 'stops.customer.services'])
            ->first();

        return $route instanceof CollectorRoute ? $presenter->make($route) : null;
    }
}
