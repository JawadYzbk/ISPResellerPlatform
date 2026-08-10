<?php

namespace App\Http\Controllers\Web;

use App\Actions\AssignUpstreamCredential;
use App\Actions\ListUpstreamCredentials;
use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Models\UpstreamCredential;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;

final class CredentialOperationsController extends Controller
{
    public function index(Request $request, ListUpstreamCredentials $listCredentials): Response
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
        ]);
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
