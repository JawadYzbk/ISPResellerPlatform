<?php

namespace App\Http\Controllers\Web;

use App\Actions\ListNetworkCommands;
use App\Actions\RetryNetworkCommand;
use App\Http\Controllers\Controller;
use App\Models\NetworkCommand;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;

final class NetworkOperationsController extends Controller
{
    public function index(Request $request, ListNetworkCommands $listNetworkCommands): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->can('network.view'), 403);
        $commands = $listNetworkCommands->handle(
            $request->string('status')->toString() ?: null,
            $request->string('network_state')->toString() ?: null,
        );

        $commandRows = $commands->getCollection()->map(function (NetworkCommand $command): array {
            $service = $command->service;
            $customer = $service?->customer;

            return [
                'public_id' => $command->public_id,
                'action' => $command->action,
                'status' => $command->status,
                'attempts' => $command->attempts,
                'desired_state_version' => $command->desired_state_version,
                'available_at' => $command->available_at?->toIso8601String(),
                'started_at' => $command->started_at?->toIso8601String(),
                'completed_at' => $command->completed_at?->toIso8601String(),
                'last_error' => $command->last_error,
                'service' => $service === null ? null : [
                    'public_id' => $service->public_id,
                    'username' => $service->username,
                    'network_state' => $service->network_state->value,
                    'customer' => $customer === null ? null : [
                        'public_id' => $customer->public_id,
                        'name' => $customer->full_name,
                    ],
                ],
            ];
        })->values();
        $commands = new LengthAwarePaginator(
            $commandRows,
            $commands->total(),
            $commands->perPage(),
            $commands->currentPage(),
            ['path' => $request->url(), 'query' => $request->query()],
        );

        return Inertia::render('Operations/NetworkCommands', [
            'commands' => $commands,
            'filters' => $request->only(['status', 'network_state']),
            'canRetry' => $user->can('network.provision'),
        ]);
    }

    public function retry(Request $request, NetworkCommand $command, RetryNetworkCommand $retry): RedirectResponse
    {
        abort_unless($request->user()?->can('network.provision') === true, 403);
        $retry->handle($command);

        return redirect()->route('operations.network-commands')->with('success', 'Network command queued for retry.');
    }
}
