<?php

namespace App\Http\Controllers\Web;

use App\Actions\CloseCashShift;
use App\Actions\ListCashShifts;
use App\Actions\OpenCashShift;
use App\Http\Controllers\Controller;
use App\Http\Requests\CloseCashShiftRequest;
use App\Models\CashShift;
use App\Models\Tenant;
use App\Models\User;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;

final class CashShiftOperationsController extends Controller
{
    public function index(Request $request, ListCashShifts $listShifts): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->can('payments.collect'), 403);
        abort_unless($user->tenant instanceof Tenant, 403);
        $shifts = $listShifts->handle($user);
        $rows = $shifts->getCollection()->map(fn (CashShift $shift): array => $this->shift($shift))->values();
        $shifts = new LengthAwarePaginator(
            $rows,
            $shifts->total(),
            $shifts->perPage(),
            $shifts->currentPage(),
            ['path' => $request->url(), 'query' => $request->query()],
        );
        $current = CashShift::query()->where('user_id', $user->id)->where('status', 'open')->latest('opened_at')->first();
        $systemTotals = $current?->payments()->where('status', 'posted')->selectRaw('currency, sum(amount) as total')->groupBy('currency')->pluck('total', 'currency')->map(fn ($value): int => (int) $value)->all() ?? [];
        $currencies = array_values(array_unique([$user->tenant->base_currency, $user->tenant->collection_currency, ...array_keys($systemTotals)]));

        return Inertia::render('Billing/Shifts', [
            'shifts' => $shifts,
            'currentShift' => $current === null ? null : [...$this->shift($current), 'system_totals' => $systemTotals],
            'currencies' => $currencies,
        ]);
    }

    public function open(Request $request, OpenCashShift $open): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->can('payments.collect'), 403);
        $open->handle($user);

        return redirect()->route('billing.shifts')->with('success', 'Cash shift opened.');
    }

    public function close(CloseCashShiftRequest $request, CashShift $shift, CloseCashShift $close): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->can('payments.collect'), 403);
        abort_unless($shift->user_id === $user->id, 403);

        try {
            $close->handle($shift, $request->validated('declared_totals'), $request->validated('variance_note'), $user);
        } catch (DomainException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()->route('billing.shifts')->with('success', 'Cash shift closed and reconciled.');
    }

    /** @return array<string, mixed> */
    private function shift(CashShift $shift): array
    {
        return [
            'public_id' => $shift->public_id,
            'status' => $shift->status->value,
            'opened_at' => $shift->opened_at?->toIso8601String(),
            'closed_at' => $shift->closed_at?->toIso8601String(),
            'system_totals' => $shift->system_totals ?? [],
            'declared_totals' => $shift->declared_totals ?? [],
            'variance' => $shift->variance,
            'variance_note' => $shift->variance_note,
        ];
    }
}
