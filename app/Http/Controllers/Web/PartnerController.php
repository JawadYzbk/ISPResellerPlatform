<?php

namespace App\Http\Controllers\Web;

use App\Actions\ApprovePartnerSettlement;
use App\Actions\CreatePartner;
use App\Actions\FundPartnerWallet;
use App\Actions\GeneratePartnerSettlement;
use App\Actions\GetCurrencyCatalog;
use App\Actions\PayPartnerSettlement;
use App\Actions\ResolvePartnerPrice;
use App\Actions\SavePartnerPriceBookItem;
use App\Actions\UpdatePartner;
use App\Http\Controllers\Controller;
use App\Models\Partner;
use App\Models\PartnerWallet;
use App\Models\Plan;
use App\Models\PriceBookItem;
use App\Models\Settlement;
use App\Models\User;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
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
                'pricingPlans' => [],
                'settlements' => [],
                'showCost' => false,
                'canManage' => $user->can('partners.manage'),
                'canFund' => $user->can('wallets.fund'),
                'canApprove' => $user->can('settlements.approve'),
                'currencies' => $currencyCatalog->handle(),
            ]);
        }
        $wallet = $partner->wallet;
        $showCost = $user->partner_id === null && $user->can('settlements.approve');
        $now = now();
        $partnerItems = PriceBookItem::query()
            ->where('currency', $partner->currency)
            ->where('effective_from', '<=', $now)
            ->where(fn ($query) => $query->whereNull('effective_to')->orWhere('effective_to', '>', $now))
            ->whereHas('priceBook', fn ($query) => $query->where('partner_id', $partner->id)->where('status', 'active'))
            ->with('priceBook')
            ->orderByDesc('effective_from')
            ->get()
            ->unique('plan_id')
            ->keyBy('plan_id');
        $pricingPlans = Plan::query()
            ->where('status', 'active')
            ->with(['prices' => fn ($query) => $query
                ->where('currency', $partner->currency)
                ->where('effective_from', '<=', $now)
                ->where(fn ($price) => $price->whereNull('effective_to')->orWhere('effective_to', '>', $now))
                ->latest('effective_from')])
            ->orderBy('name')
            ->get()
            ->map(function (Plan $plan) use ($partner, $partnerItems): array {
                $price = $plan->prices->first();
                $override = $partnerItems->get($plan->id);

                return [
                    'id' => $plan->public_id,
                    'name' => $plan->name,
                    'duration_days' => $plan->duration_days,
                    'currency' => $partner->currency,
                    'base_amount_minor' => $price?->amount_minor,
                    'override' => $override instanceof PriceBookItem ? [
                        'id' => $override->id,
                        'buy_amount_minor' => $override->buy_amount_minor,
                        'sell_amount_minor' => $override->sell_amount_minor,
                        'min_amount_minor' => $override->min_amount_minor,
                        'max_amount_minor' => $override->max_amount_minor,
                        'effective_from' => $override->effective_from->toDateString(),
                    ] : null,
                ];
            })->values();
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
            'selectedPartner' => [
                'id' => $partner->public_id,
                'name' => $partner->name,
                'code' => $partner->code,
                'currency' => $partner->currency,
                'credit_limit' => $partner->credit_limit,
                'low_balance_threshold' => $partner->low_balance_threshold,
                'status' => $partner->status,
                'wallet' => $wallet instanceof PartnerWallet ? [
                    'currency' => $wallet->currency,
                    'balance_amount' => $wallet->balance_amount,
                    'available_amount' => $wallet->balance_amount + $partner->credit_limit,
                ] : null,
            ],
            'catalog' => $catalog,
            'pricingPlans' => $user->can('partners.manage') ? $pricingPlans : [],
            'settlements' => $settlements,
            'showCost' => $showCost,
            'canManage' => $user->can('partners.manage'),
            'canFund' => $user->can('wallets.fund'),
            'canApprove' => $user->can('settlements.approve'),
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

    public function update(Request $request, Partner $partner, UpdatePartner $update): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->can('partners.manage'), 403);
        abort_unless($this->visible($user)->whereKey($partner->id)->exists(), 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'required',
                'string',
                'max:32',
                'regex:/^[A-Za-z0-9_-]+$/',
                'not_regex:/^$/',
            ],
            'credit_limit' => ['required', 'integer', 'min:0'],
            'low_balance_threshold' => ['required', 'integer', 'min:0'],
            'status' => ['required', 'string', Rule::in(['active', 'suspended'])],
        ]);
        $data['code'] = strtoupper(trim((string) $data['code']));
        if (Partner::query()->whereRaw('UPPER(code) = ?', [$data['code']])->whereKeyNot($partner->id)->exists()) {
            throw ValidationException::withMessages(['code' => 'A partner with this code already exists.']);
        }

        $update->handle($partner, $data);

        return redirect()->route('partners.commercial', ['partner' => $partner->public_id])->with('success', "Partner {$partner->name} updated.");
    }

    public function savePriceBookItem(Request $request, Partner $partner, SavePartnerPriceBookItem $save): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->can('partners.manage'), 403);
        $partner = $this->visible($user)->whereKey($partner->id)->firstOrFail();
        $data = $request->validate([
            'plan_id' => ['required', 'string', 'max:26', Rule::exists('plans', 'public_id')->where('tenant_id', $user->tenant_id)],
            'currency' => ['required', 'string', 'size:3', Rule::in([$partner->currency])],
            'buy_amount_minor' => ['required', 'integer', 'min:0'],
            'sell_amount_minor' => ['required', 'integer', 'min:0', 'gte:buy_amount_minor'],
            'min_amount_minor' => ['nullable', 'integer', 'min:0'],
            'max_amount_minor' => ['nullable', 'integer', 'gte:min_amount_minor'],
            'effective_from' => ['required', 'date'],
        ]);
        if (($data['min_amount_minor'] ?? null) !== null && (int) $data['sell_amount_minor'] < (int) $data['min_amount_minor']) {
            throw ValidationException::withMessages(['sell_amount_minor' => 'The sell price cannot be below the configured floor.']);
        }
        if (($data['max_amount_minor'] ?? null) !== null && (int) $data['sell_amount_minor'] > (int) $data['max_amount_minor']) {
            throw ValidationException::withMessages(['sell_amount_minor' => 'The sell price cannot exceed the configured ceiling.']);
        }
        $plan = Plan::query()->where('public_id', $data['plan_id'])->firstOrFail();

        try {
            $save->handle($partner, $plan, $data);
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['buy_amount_minor' => $exception->getMessage()]);
        }

        return redirect()->route('partners.commercial', ['partner' => $partner->public_id])->with('success', 'Partner price updated.');
    }

    public function fundWallet(Request $request, Partner $partner, FundPartnerWallet $fund): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->can('wallets.fund'), 403);
        $partner = $this->visible($user)->whereKey($partner->id)->firstOrFail();
        $wallet = $partner->wallet;
        abort_unless($wallet instanceof PartnerWallet, 422, 'The partner wallet is not available.');
        $data = $request->validate([
            'amount' => ['required', 'integer', 'min:1'],
            'idempotency_key' => ['required', 'uuid'],
        ]);

        $fund->handle($wallet, (int) $data['amount'], (string) $data['idempotency_key'], $user);

        return redirect()->route('partners.commercial', ['partner' => $partner->public_id])->with('success', 'Partner wallet funded.');
    }

    public function storeSettlement(Request $request, Partner $partner, GeneratePartnerSettlement $generate): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->can('settlements.approve'), 403);
        $partner = $this->visible($user)->whereKey($partner->id)->firstOrFail();
        $data = $request->validate([
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'currency' => ['required', 'string', 'size:3', Rule::in([$partner->currency])],
        ]);

        $generate->handle(
            $partner,
            CarbonImmutable::parse((string) $data['period_start']),
            CarbonImmutable::parse((string) $data['period_end']),
            strtoupper((string) $data['currency']),
        );

        return redirect()->route('partners.commercial', ['partner' => $partner->public_id])->with('success', 'Settlement statement created.');
    }

    public function approveSettlement(Request $request, Settlement $settlement, ApprovePartnerSettlement $approve): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->can('settlements.approve'), 403);
        $settlement = $this->visibleSettlement($user, $settlement);

        try {
            $approve->handle($settlement, $user);
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['status' => $exception->getMessage()]);
        }

        return redirect()->route('partners.commercial', ['partner' => $settlement->partner()->value('public_id')])->with('success', 'Settlement approved.');
    }

    public function paySettlement(Request $request, Settlement $settlement, PayPartnerSettlement $pay): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->can('settlements.approve'), 403);
        $settlement = $this->visibleSettlement($user, $settlement);

        try {
            $pay->handle($settlement, $user);
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['status' => $exception->getMessage()]);
        }

        return redirect()->route('partners.commercial', ['partner' => $settlement->partner()->value('public_id')])->with('success', 'Settlement paid.');
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

    private function visibleSettlement(User $user, Settlement $settlement): Settlement
    {
        abort_unless($this->visible($user)->whereKey($settlement->partner_id)->exists(), 404);

        return $settlement;
    }
}
