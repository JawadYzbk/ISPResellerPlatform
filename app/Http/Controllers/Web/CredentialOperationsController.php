<?php

namespace App\Http\Controllers\Web;

use App\Actions\AssignUpstreamCredential;
use App\Actions\GetCurrencyCatalog;
use App\Actions\ImportCredentialCsv;
use App\Actions\ListUpstreamCredentials;
use App\Actions\RevealUpstreamCredential;
use App\Enums\ProvisioningMode;
use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Models\Supplier;
use App\Models\SupplierContract;
use App\Models\Tenant;
use App\Models\UpstreamCredential;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

final class CredentialOperationsController extends Controller
{
    public function index(Request $request, ListUpstreamCredentials $listCredentials, GetCurrencyCatalog $currencyCatalog): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->can('suppliers.view'), 403);
        $credentials = $listCredentials->handle(
            $request->string('status')->toString() ?: null,
            $request->string('search')->toString() ?: null,
        );
        $rows = $credentials->getCollection()->map(function (mixed $credential): array {
            if (! $credential instanceof UpstreamCredential) {
                throw new \LogicException('Credential paginator contained an invalid record.');
            }

            return [
                'id' => $credential->id,
                'identifier' => $credential->identifier,
                'status' => $credential->status->value,
                'expires_at' => $this->isoDate($credential->expires_at),
                'supplier' => $credential->batch?->supplier === null ? null : [
                    'name' => $credential->batch->supplier->name,
                    'code' => $credential->batch->supplier->code,
                ],
                'batch_reference' => $credential->batch?->reference,
                'supplier_contract' => $credential->batch?->supplierContract === null ? null : [
                    'id' => $credential->batch->supplierContract->id,
                    'service_type' => $credential->batch->supplierContract->service_type,
                    'status' => $credential->batch->supplierContract->status,
                ],
                'assigned_service' => $credential->assignedService === null ? null : [
                    'public_id' => $credential->assignedService->public_id,
                    'username' => $credential->assignedService->username,
                    'customer_public_id' => $credential->assignedService->customer?->public_id,
                    'customer' => $credential->assignedService->customer?->full_name,
                ],
            ];
        })->values();
        $credentials = new LengthAwarePaginator(
            $rows,
            $credentials->total(),
            $credentials->perPage(),
            $credentials->currentPage(),
            ['path' => $request->url(), 'query' => $request->query()],
        );

        $canAssign = $user->can('credentials.assign');
        $assignableServices = $canAssign
            ? Service::query()
                ->with('customer')
                ->where('provisioning_mode', ProvisioningMode::UpstreamCredential)
                ->whereNotIn('id', UpstreamCredential::query()->whereNotNull('assigned_service_id')->select('assigned_service_id'))
                ->orderBy('username')
                ->get(['id', 'public_id', 'customer_id', 'username'])
                ->map(fn (Service $service): array => [
                    'public_id' => $service->public_id,
                    'username' => $service->username,
                    'customer' => $service->customer?->full_name,
                ])
                ->values()
                ->all()
            : [];

        return Inertia::render('Operations/Credentials', [
            'credentials' => $credentials,
            'filters' => $request->only(['status', 'search']),
            'canAssign' => $canAssign,
            'assignableServices' => $assignableServices,
            'canImport' => $user->can('credentials.import'),
            'canReveal' => $user->can('credentials.reveal'),
            'suppliers' => $user->can('credentials.import')
                ? Supplier::query()->with(['contracts' => fn ($query) => $query->whereIn('status', ['active', 'suspended'])->orderBy('effective_from', 'desc')])->where('is_active', true)->orderBy('name')->get(['id', 'name', 'code'])->map(fn (Supplier $supplier): array => [
                    'id' => $supplier->id,
                    'name' => $supplier->name,
                    'code' => $supplier->code,
                    'contracts' => $supplier->contracts->map(fn (SupplierContract $contract): array => [
                        'id' => $contract->id,
                        'service_type' => $contract->service_type,
                        'wholesale_currency' => $contract->wholesale_currency,
                        'status' => $contract->status,
                    ])->values()->all(),
                ])->values()->all()
                : [],
            'currencies' => $user->can('credentials.import') ? $currencyCatalog->handle() : [],
        ]);
    }

    public function import(Request $request, ImportCredentialCsv $import): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->can('credentials.import') && $user->tenant instanceof Tenant, 403);
        $validated = $request->validate([
            'supplier_id' => [Rule::exists('suppliers', 'id')->where('tenant_id', $user->tenant->id)],
            'supplier_contract_id' => ['nullable', Rule::exists('supplier_contracts', 'id')->where('tenant_id', $user->tenant->id)],
            'reference' => ['required', 'string', 'max:64'],
            'contract_reference' => ['nullable', 'string', 'max:64'],
            'unit_cost_amount' => ['nullable', 'integer', 'min:0'],
            'total_cost_amount' => ['nullable', 'integer', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3', Rule::exists('currencies', 'code')->where('is_active', true)],
            'expires_at' => ['nullable', 'date'],
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
        ]);
        $file = $validated['file'];
        abort_unless($file instanceof UploadedFile, 422, 'A credential CSV file is required.');
        $supplier = Supplier::query()->findOrFail((int) $validated['supplier_id']);
        $contract = isset($validated['supplier_contract_id'])
            ? SupplierContract::query()->where('supplier_id', $supplier->id)->findOrFail((int) $validated['supplier_contract_id'])
            : null;
        $batch = $import->handle($supplier, (string) $validated['reference'], $validated['expires_at'] ?? null, $file->get(), [
            'supplier_contract_id' => $contract?->id,
            'contract_reference' => $validated['contract_reference'] ?? null,
            'unit_cost_amount' => $validated['unit_cost_amount'] ?? null,
            'total_cost_amount' => $validated['total_cost_amount'] ?? null,
            'currency' => $validated['currency'] ?? null,
        ]);

        return redirect()->route('operations.credentials')->with('success', "Imported credentials into batch {$batch->reference}.");
    }

    public function reveal(Request $request, UpstreamCredential $credential, RevealUpstreamCredential $reveal): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return response()->json(['secret' => $reveal->handle($user, $credential)]);
    }

    public function assign(Request $request, UpstreamCredential $credential, AssignUpstreamCredential $assign): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->can('credentials.assign'), 403);
        $validated = $request->validate(['service_public_id' => ['required', 'string']]);
        $service = Service::query()->where('public_id', $validated['service_public_id'])->firstOrFail();
        $assign->handle($credential, $service, $user);

        return redirect()->route('operations.credentials')->with('success', "Credential {$credential->identifier} assigned.");
    }

    private function isoDate(mixed $value): ?string
    {
        return $value === null ? null : CarbonImmutable::parse((string) $value)->toIso8601String();
    }
}
