<?php

namespace App\Http\Controllers\Web;

use App\Actions\CreatePartner;
use App\Actions\GetCurrencyCatalog;
use App\Actions\ResolvePartnerPrice;
use App\Http\Controllers\Controller;
use App\Models\Partner;
use App\Models\Plan;
use App\Models\Settlement;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final class PartnerController extends Controller
{
    public function commercial(Request $request, ResolvePartnerPrice $prices, GetCurrencyCatalog $currencyCatalog): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->can('wallets.view'), 403);
        $partners = $this->visible($user)->orderBy('path')->get();
        $userPartnerId = $user->partner_id === null ? null : $user->partner->public_id;
        $selectedId = $request->string('partner')->toString() ?: (string) ($userPartnerId ?? $partners->first()?->public_id);
        $partner = $partners->firstWhere('public_id', $selectedId);
        if (! $partner instanceof Partner && $user->partner_id !== null) {
            abort(404);
        }

        if (! $partner instanceof Partner) {
            return Inertia::render('Partners/Commercial', [
                'partners' => [],
                'selectedPartner' => null,
                'catalog' => [],
                'settlements' => [],
                'showCost' => false,
                'canManage' => $user->can('partners.manage'),
                'currencies' => $currencyCatalog->handle(),
            ]);
        }
        $showCost = $user->partner_id === null && $user->can('settlements.approve');
        $catalog = [];

        foreach (Plan::query()->where('status', 'active')->orderBy('name')->get() as $plan) {
            try {
                $item = $prices->handle($partner, $plan, $partner->currency);
            } catch (ModelNotFoundException) {
                continue;
            }

            $catalog[] = [
                'id' => $plan->public_id,
                'name' => $plan->name,
                'duration_days' => $plan->duration_days,
                'currency' => $item->currency,
                'sell_amount_minor' => $item->sell_amount_minor,
                'buy_amount_minor' => $showCost ? $item->buy_amount_minor : null,
                'price_book' => $item->priceBook()->value('name'),
            ];
        }

        $settlements = Settlement::query()->where('partner_id', $partner->id)->latest('period_end')->get()->map(fn (Settlement $settlement): array => [
            'id' => $settlement->public_id,
            'period_start' => $settlement->period_start->toDateString(),
            'period_end' => $settlement->period_end->toDateString(),
            'currency' => $settlement->currency,
            'opening_amount' => $settlement->opening_amount,
            'activity_amount' => $settlement->activity_amount,
            'closing_amount' => $settlement->closing_amount,
            'due_amount' => $settlement->due_amount,
            'status' => $settlement->status,
        ])->values();

        return Inertia::render('Partners/Commercial', [
            'partners' => $partners->map(fn (Partner $item): array => ['id' => $item->public_id, 'name' => $item->name, 'code' => $item->code])->values(),
            'selectedPartner' => ['id' => $partner->public_id, 'name' => $partner->name, 'code' => $partner->code, 'currency' => $partner->currency],
            'catalog' => $catalog,
            'settlements' => $settlements,
            'showCost' => $showCost,
            'canManage' => $user->can('partners.manage'),
            'currencies' => $currencyCatalog->handle(),
        ]);
    }

    public function store(Request $request, CreatePartner $create): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->can('partners.manage'), 403);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:32', 'regex:/^[A-Za-z0-9_-]+$/'],
            'currency' => ['required', 'string', 'size:3', 'regex:/^[A-Za-z]{3}$/'],
            'parent_id' => ['nullable', 'string', 'max:26'],
            'credit_limit' => ['nullable', 'integer', 'min:0'],
            'low_balance_threshold' => ['nullable', 'integer', 'min:0'],
        ]);
        $data['code'] = strtoupper(trim((string) $data['code']));
        $data['currency'] = strtoupper((string) $data['currency']);
        if (Partner::query()->whereRaw('UPPER(code) = ?', [$data['code']])->exists()) {
            throw ValidationException::withMessages(['code' => 'A partner with this code already exists.']);
        }

        $parentId = ($data['parent_id'] ?? null) ?: ($user->partner_id === null ? null : $user->partner->public_id);
        $parent = null;
        if ($parentId !== null) {
            $parent = $this->visible($user)->where('public_id', $parentId)->firstOrFail();
        }
        $partner = $create->handle(
            (string) $data['name'],
            $data['code'],
            $data['currency'],
            $parent,
            (int) ($data['credit_limit'] ?? 0),
            (int) ($data['low_balance_threshold'] ?? 0),
        );

        return redirect()->route('partners.commercial', ['partner' => $partner->public_id])->with('success', "Partner {$partner->name} created.");
    }

    /** @return Builder<Partner> */
    private function visible(User $user): Builder
    {
        $query = Partner::query();
        if ($user->partner_id !== null) {
            $partner = Partner::query()->whereKey($user->partner_id)->firstOrFail();
            $query->where('path', 'like', $partner->path.'%');
        }

        return $query;
    }
}
