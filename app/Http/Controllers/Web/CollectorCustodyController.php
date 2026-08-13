<?php

namespace App\Http\Controllers\Web;

use App\Actions\CreateCollectorCustodyEntry;
use App\Actions\GetCollectorCustodyPosition;
use App\Actions\GetCurrencyCatalog;
use App\Actions\ReviewCollectorCustodyEntry;
use App\Http\Controllers\Controller;
use App\Models\CashShift;
use App\Models\CollectorCustodyEntry;
use App\Models\User;
use App\Support\CollectorCustodyPresenter;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

final class CollectorCustodyController extends Controller
{
    public function index(Request $request, GetCollectorCustodyPosition $position, GetCurrencyCatalog $currencies, CollectorCustodyPresenter $presenter): Response
    {
        $manager = $this->manager($request);
        $validated = $request->validate([
            'collector' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::in(['all', ...CollectorCustodyEntry::STATUSES])],
        ]);
        $collectors = User::query()->where('role', 'collector')->orderBy('name')->get(['id', 'name', 'email']);
        $collectorId = isset($validated['collector']) ? (int) $validated['collector'] : $collectors->first()?->id;
        $status = (string) ($validated['status'] ?? 'all');
        $entries = CollectorCustodyEntry::query()
            ->with(['collector:id,name,email', 'requestedBy:id,name', 'reviewedBy:id,name', 'cashShift:id,public_id'])
            ->when($collectorId !== null, fn ($query) => $query->where('collector_id', $collectorId))
            ->when($status !== 'all', fn ($query) => $query->where('status', $status))
            ->latest('occurred_at')
            ->limit(250)
            ->get();

        return Inertia::render('Operations/CollectorCustody', [
            'filters' => ['collector' => $collectorId, 'status' => $status],
            'collectors' => $collectors->map(fn (User $collector): array => [
                'id' => $collector->id,
                'name' => $collector->name,
                'email' => $collector->email,
                'position' => $position->handle($collector),
            ])->values(),
            'entries' => $entries->map(fn (CollectorCustodyEntry $entry): array => $presenter->entry($entry))->values(),
            'currencies' => $currencies->handle(),
        ]);
    }

    public function storeManager(Request $request, CreateCollectorCustodyEntry $create): RedirectResponse
    {
        $manager = $this->manager($request);
        $data = $this->validateEntry($request, true);
        $collector = User::query()->where('role', 'collector')->findOrFail($data['collector_id']);
        $shift = CashShift::query()->where('user_id', $collector->id)->where('status', 'open')->latest('opened_at')->first();
        try {
            $create->handle($manager, $collector, $shift, $data);
        } catch (DomainException $exception) {
            return back()->withErrors(['description' => $exception->getMessage()]);
        }

        return back()->with('success', 'Custody entry posted.');
    }

    public function storeField(Request $request, CreateCollectorCustodyEntry $create, GetCollectorCustodyPosition $position, CollectorCustodyPresenter $presenter): JsonResponse
    {
        $collector = $this->collector($request);
        $data = $this->validateEntry($request, false);
        $shift = CashShift::query()->where('user_id', $collector->id)->where('status', 'open')->latest('opened_at')->first();
        try {
            $entry = $create->handle($collector, $collector, $shift, $data);
        } catch (DomainException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'message' => ucfirst($entry->type).' submitted for manager review.',
            'data' => ['entry' => $presenter->entry($entry), 'position' => $position->handle($collector)],
        ], 201);
    }

    public function review(Request $request, CollectorCustodyEntry $collectorCustodyEntry, ReviewCollectorCustodyEntry $review): RedirectResponse
    {
        $manager = $this->manager($request);
        $validated = $request->validate([
            'decision' => ['required', Rule::in(['posted', 'rejected'])],
            'review_note' => ['nullable', 'string', 'max:2000'],
        ]);
        try {
            $review->handle($manager, $collectorCustodyEntry, $validated['decision'], $validated['review_note'] ?? null);
        } catch (DomainException $exception) {
            return back()->withErrors(['review' => $exception->getMessage()]);
        }

        return back()->with('success', $validated['decision'] === 'posted' ? 'Custody entry approved.' : 'Custody entry rejected.');
    }

    /** @return array<string, mixed> */
    private function validateEntry(Request $request, bool $manager): array
    {
        return $request->validate([
            'collector_id' => [$manager ? 'required' : 'nullable', 'integer'],
            'type' => ['required', Rule::in($manager ? CollectorCustodyEntry::TYPES : ['expense', 'handover'])],
            'direction' => ['nullable', Rule::in(CollectorCustodyEntry::DIRECTIONS)],
            'amount' => ['required', 'integer', 'min:1'],
            'currency' => ['required', 'string', 'size:3', 'regex:/^[A-Za-z]{3}$/'],
            'description' => ['required', 'string', 'max:2000'],
            'reference' => ['nullable', 'string', 'max:120'],
            'occurred_at' => ['nullable', 'date'],
        ]);
    }

    private function manager(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->can('reports.operations'), 403);

        return $user;
    }

    private function collector(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->role === 'collector' && $user->can('payments.collect'), 403);

        return $user;
    }
}
