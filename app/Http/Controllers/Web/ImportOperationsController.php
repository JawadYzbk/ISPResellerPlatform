<?php

namespace App\Http\Controllers\Web;

use App\Actions\ImportRouterSubscribers;
use App\Actions\ImportTabularData;
use App\Actions\RollbackImport;
use App\Http\Controllers\Controller;
use App\Models\ImportBatch;
use App\Models\Router;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Api\ImportBatchApiResource;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

final class ImportOperationsController extends Controller
{
    /** @var array<string, array{label: string, permission: string, columns: string}> */
    private const IMPORT_TYPES = [
        'customers' => [
            'label' => 'Customers',
            'permission' => 'customers.create',
            'columns' => 'first_name, phone, last_name, email, address, code',
        ],
        'plans' => [
            'label' => 'Plans',
            'permission' => 'plans.manage',
            'columns' => 'name, download_kbps, upload_kbps, duration_days, amount_minor, currency, slug, status',
        ],
        'services' => [
            'label' => 'Services',
            'permission' => 'services.create',
            'columns' => 'customer_code, plan_slug, username, password, status, provisioning_mode, network_state, activated_at, expires_at',
        ],
        'equipment' => [
            'label' => 'Serialized equipment',
            'permission' => 'inventory.receive',
            'columns' => 'sku, warehouse_code, serial_number, status, service_username, assigned_at',
        ],
        'balances' => [
            'label' => 'Opening balances',
            'permission' => 'billing.adjustments.create',
            'columns' => 'customer_code, amount_minor, currency, effective_at, memo',
        ],
        'router_subscribers' => [
            'label' => 'Router subscriber discovery',
            'permission' => 'network.provision',
            'columns' => 'RouterOS PPP secrets; no file required',
        ],
    ];

    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);
        $types = $this->availableTypes($user);
        abort_if($types === [], 403);

        $batches = ImportBatch::query()
            ->latest('created_at')
            ->limit(25)
            ->get()
            ->map(function (mixed $batch): array {
                if (! $batch instanceof ImportBatch) {
                    throw new \LogicException('Import history contained an invalid record.');
                }

                return [
                    'id' => $batch->public_id,
                    'type' => $batch->type,
                    'filename' => $batch->filename,
                    'status' => $batch->status,
                    'total_rows' => $batch->total_rows,
                    'successful_rows' => $batch->successful_rows,
                    'failed_rows' => $batch->failed_rows,
                    'created_at' => $batch->created_at?->toIso8601String(),
                    'completed_at' => $batch->completed_at?->toIso8601String(),
                    'rolled_back_at' => $batch->rolled_back_at?->toIso8601String(),
                ];
            })
            ->values()
            ->all();

        $routers = $user->can('network.view')
            ? Router::query()->orderBy('name')->get(['public_id', 'name', 'host'])->map(fn (Router $router): array => [
                'public_id' => $router->public_id,
                'name' => $router->name,
                'host' => $router->host,
            ])->values()->all()
            : [];

        return Inertia::render('Operations/Imports', [
            'types' => $types,
            'routers' => $routers,
            'batches' => $batches,
        ]);
    }

    public function store(Request $request, ImportTabularData $import, ImportRouterSubscribers $routerSubscribers, ImportBatchApiResource $resource): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->tenant instanceof Tenant, 403);
        $type = $request->string('type')->toString();
        $request->validate(['type' => ['required', Rule::in(array_keys(self::IMPORT_TYPES))]]);
        abort_unless($user->can(self::IMPORT_TYPES[$type]['permission']), 403);

        if ($type === 'router_subscribers') {
            $validated = $request->validate(['router_public_id' => ['required', 'string']]);
            $router = Router::query()->where('public_id', $validated['router_public_id'])->firstOrFail();
            $batch = $routerSubscribers->handle($router, $request->boolean('dry_run'));
        } else {
            $validated = $request->validate([
                'file' => ['required', 'file', 'mimes:csv,txt,xlsx', 'max:10240'],
                'dry_run' => ['sometimes', 'boolean'],
            ]);
            $file = $validated['file'];
            abort_unless($file instanceof UploadedFile, 422, 'A CSV or XLSX file is required.');
            $batch = $import->handle($user->tenant, $type, $file->get(), $file->getClientOriginalName(), $request->boolean('dry_run'));
        }

        $result = $resource->make($batch);
        $message = $batch->status === 'preview'
            ? "Preview ready: {$batch->successful_rows} row(s) can be imported."
            : "{$batch->successful_rows} row(s) imported; {$batch->failed_rows} row(s) rejected.";

        return redirect()->route('operations.imports')->with('success', $message)->with('importResult', $result);
    }

    public function rollback(Request $request, string $import, RollbackImport $rollback): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);
        $batch = ImportBatch::query()->where('public_id', $import)->firstOrFail();
        $permission = self::IMPORT_TYPES[$batch->type]['permission'] ?? null;
        abort_unless($permission !== null && $user->can($permission), 403);
        $deleted = $rollback->handle($batch);

        return redirect()->route('operations.imports')->with('success', "Import rolled back. {$deleted} record(s) reversed or removed.");
    }

    /** @return list<array{value: string, label: string, columns: string}> */
    private function availableTypes(User $user): array
    {
        return collect(self::IMPORT_TYPES)
            ->filter(fn (array $definition): bool => $user->can($definition['permission']))
            ->map(fn (array $definition, string $value): array => [
                'value' => $value,
                'label' => $definition['label'],
                'columns' => $definition['columns'],
            ])
            ->values()
            ->all();
    }
}
