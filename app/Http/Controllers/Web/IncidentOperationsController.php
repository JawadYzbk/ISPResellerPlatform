<?php

namespace App\Http\Controllers\Web;

use App\Actions\ListIncidents;
use App\Http\Controllers\Controller;
use App\Models\Incident;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;

final class IncidentOperationsController extends Controller
{
    public function index(Request $request, ListIncidents $listIncidents): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->can('network.view'), 403);

        $incidents = $listIncidents->handle(
            $request->string('status')->toString() ?: null,
            $request->string('severity')->toString() ?: null,
            $request->string('search')->toString() ?: null,
        );
        $rows = $incidents->getCollection()->map(fn (Incident $incident): array => $this->incidentRow($incident))->values();
        $incidents = new LengthAwarePaginator(
            $rows,
            $incidents->total(),
            $incidents->perPage(),
            $incidents->currentPage(),
            ['path' => $request->url(), 'query' => $request->query()],
        );

        return Inertia::render('Operations/Incidents', [
            'incidents' => $incidents,
            'filters' => $request->only(['status', 'severity', 'search']),
        ]);
    }

    public function show(Request $request, Incident $incident): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->can('network.view'), 403);
        $incident->load(['router.pop', 'service.customer']);

        return Inertia::render('Operations/IncidentShow', [
            'incident' => $this->incidentRow($incident, includeDescription: true),
        ]);
    }

    /** @return array<string, mixed> */
    private function incidentRow(Incident $incident, bool $includeDescription = false): array
    {
        $router = $incident->router;
        $service = $incident->service;
        $customer = $service?->customer;
        $row = [
            'public_id' => $incident->public_id,
            'type' => $incident->type,
            'severity' => $incident->severity,
            'status' => $incident->status->value,
            'title' => $incident->title,
            'opened_at' => $this->isoDate($incident->opened_at),
            'resolved_at' => $this->isoDate($incident->resolved_at),
            'router' => $router === null ? null : ['public_id' => $router->public_id, 'name' => $router->name, 'host' => $router->host, 'pop' => $router->pop?->name],
            'service' => $service === null ? null : ['public_id' => $service->public_id, 'username' => $service->username],
            'customer' => $customer === null ? null : ['public_id' => $customer->public_id, 'code' => $customer->code, 'name' => $customer->full_name],
        ];

        if ($includeDescription) {
            $row['description'] = $incident->description;
            $row['metadata'] = $incident->metadata ?? [];
        }

        return $row;
    }

    private function isoDate(mixed $value): ?string
    {
        return $value === null ? null : CarbonImmutable::parse((string) $value)->toIso8601String();
    }
}
