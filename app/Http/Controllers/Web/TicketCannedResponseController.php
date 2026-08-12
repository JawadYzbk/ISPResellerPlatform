<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\TicketCannedResponse;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

final class TicketCannedResponseController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorizeSettings($request);

        return Inertia::render('Settings/TicketResponses', [
            'responses' => TicketCannedResponse::query()
                ->orderByDesc('is_active')
                ->orderBy('title')
                ->get(['public_id', 'title', 'body', 'category', 'is_active'])
                ->values()
                ->all(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeSettings($request);
        $validated = $this->validated($request);
        TicketCannedResponse::create($validated + ['is_active' => true]);

        return redirect()->route('settings.ticket-responses')->with('success', 'Ticket response created.');
    }

    public function update(Request $request, TicketCannedResponse $ticketCannedResponse): RedirectResponse
    {
        $this->authorizeSettings($request);
        abort_unless($ticketCannedResponse->tenant_id === $request->user()->tenant_id, 404);
        $ticketCannedResponse->update($this->validated($request, $ticketCannedResponse));

        return redirect()->route('settings.ticket-responses')->with('success', 'Ticket response updated.');
    }

    public function archive(Request $request, TicketCannedResponse $ticketCannedResponse): RedirectResponse
    {
        $this->authorizeSettings($request);
        abort_unless($ticketCannedResponse->tenant_id === $request->user()->tenant_id, 404);
        $ticketCannedResponse->update(['is_active' => false]);

        return redirect()->route('settings.ticket-responses')->with('success', 'Ticket response archived.');
    }

    /** @return array{title: string, body: string, category: string, is_active?: bool} */
    private function validated(Request $request, ?TicketCannedResponse $current = null): array
    {
        $user = $request->user();
        $tenantId = $user instanceof User ? $user->tenant_id : null;

        return $request->validate([
            'title' => [
                'required',
                'string',
                'max:120',
                Rule::unique('ticket_canned_responses', 'title')
                    ->where(fn ($query) => $query->where('tenant_id', $tenantId))
                    ->ignore($current?->id),
            ],
            'body' => ['required', 'string', 'max:5000'],
            'category' => ['required', 'string', 'max:64'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }

    private function authorizeSettings(Request $request): void
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->can('settings.manage'), 403);
    }
}
