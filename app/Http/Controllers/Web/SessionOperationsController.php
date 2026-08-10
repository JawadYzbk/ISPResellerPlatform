<?php

namespace App\Http\Controllers\Web;

use App\Actions\ListCurrentSessions;
use App\Http\Controllers\Controller;
use App\Models\CurrentSession;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;

final class SessionOperationsController extends Controller
{
    public function index(Request $request, ListCurrentSessions $listCurrentSessions): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->can('network.view'), 403);

        $sessions = $listCurrentSessions->handle($request->string('search')->toString() ?: null);
        $rows = $sessions->getCollection()->map(fn (CurrentSession $session): array => $this->sessionRow($session))->values();
        $sessions = new LengthAwarePaginator(
            $rows,
            $sessions->total(),
            $sessions->perPage(),
            $sessions->currentPage(),
            ['path' => $request->url(), 'query' => $request->query()],
        );

        return Inertia::render('Operations/Sessions', [
            'sessions' => $sessions,
            'filters' => $request->only(['search']),
            'canDisconnect' => $user->can('network.disconnect'),
        ]);
    }

    /** @return array<string, mixed> */
    private function sessionRow(CurrentSession $session): array
    {
        $service = $session->service;
        $customer = $service?->customer;

        return [
            'session_id' => $session->acct_session_id,
            'username' => $session->username,
            'nasname' => $session->nasname,
            'framed_ip' => $session->framed_ip,
            'started_at' => $this->isoDate($session->acct_start_time),
            'last_seen_at' => $this->isoDate($session->last_seen_at),
            'input_octets' => $session->input_octets,
            'output_octets' => $session->output_octets,
            'service' => $service === null ? null : [
                'public_id' => $service->public_id,
                'username' => $service->username,
                'plan' => $service->plan?->name,
            ],
            'customer' => $customer === null ? null : [
                'public_id' => $customer->public_id,
                'code' => $customer->code,
                'name' => $customer->full_name,
            ],
            'router' => $service?->router?->name,
        ];
    }

    private function isoDate(mixed $value): ?string
    {
        return $value === null ? null : CarbonImmutable::parse((string) $value)->toIso8601String();
    }
}
