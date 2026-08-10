<?php

namespace App\Http\Controllers\Api;

use App\Actions\ApprovePartnerSettlement;
use App\Actions\FundPartnerWallet;
use App\Actions\GeneratePartnerSettlement;
use App\Actions\PayPartnerSettlement;
use App\Actions\ResolvePartnerPrice;
use App\Http\Controllers\Controller;
use App\Models\Partner;
use App\Models\PartnerWallet;
use App\Models\Plan;
use App\Models\Settlement;
use App\Models\User;
use App\Models\WalletTransaction;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PartnerApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $this->user($request);
        abort_unless($user->can('wallets.view'), 403);

        $partners = $this->visible($user)->with('wallet')->orderBy('path')->get()->map(fn (Partner $partner): array => $this->summary($partner))->values();

        return response()->json(['data' => $partners]);
    }

    public function show(Request $request, int $partner): JsonResponse
    {
        $user = $this->user($request);
        abort_unless($user->can('wallets.view'), 403);
        $model = $this->visible($user)->with(['wallet.transactions'])->whereKey($partner)->firstOrFail();

        return response()->json([...$this->summary($model), 'transactions' => $model->wallet->transactions->sortByDesc('created_at')->values()->map(fn (WalletTransaction $transaction): array => ['id' => $transaction->id, 'type' => $transaction->type, 'direction' => $transaction->direction, 'amount' => $transaction->amount, 'balance_after' => $transaction->balance_after, 'created_at' => $transaction->created_at?->toIso8601String()])->values()]);
    }

    public function topUp(Request $request, int $partner, FundPartnerWallet $fund): JsonResponse
    {
        $user = $this->user($request);
        abort_unless($user->can('wallets.fund'), 403);
        $validated = $request->validate(['amount' => ['required', 'integer', 'min:1']]);
        $model = $this->visible($user)->with('wallet')->whereKey($partner)->firstOrFail();
        $wallet = $model->wallet;
        abort_unless($wallet instanceof PartnerWallet, 422, 'The partner wallet is not available.');
        $transaction = $fund->handle($wallet, $validated['amount'], (string) $request->header('X-Idempotency-Key'), $user);

        return response()->json(['id' => $model->id, 'wallet_transaction_id' => $transaction->id, 'balance_after' => $transaction->balance_after, 'currency' => $wallet->currency], 201);
    }

    public function catalog(Request $request, int $partner, ResolvePartnerPrice $prices): JsonResponse
    {
        $user = $this->user($request);
        abort_unless($user->can('services.view'), 403);
        $model = $this->visible($user)->whereKey($partner)->firstOrFail();
        $showCost = $user->partner_id === null && $user->can('settlements.approve');
        $catalog = [];
        foreach (Plan::query()->where('status', 'active')->orderBy('name')->get() as $plan) {
            try {
                $item = $prices->handle($model, $plan, $model->currency);
            } catch (ModelNotFoundException) {
                continue;
            }

            $priceBook = $item->priceBook()->firstOrFail();
            $data = [
                'id' => $plan->public_id,
                'name' => $plan->name,
                'duration_days' => $plan->duration_days,
                'currency' => $item->currency,
                'sell_amount_minor' => $item->sell_amount_minor,
                'price_book' => $priceBook->name,
            ];
            if ($showCost) {
                $data['buy_amount_minor'] = $item->buy_amount_minor;
                $data['commission_rule_version'] = $item->commissionRule()->value('version');
            }

            $catalog[] = $data;
        }

        return response()->json(['partner_id' => $model->id, 'data' => $catalog]);
    }

    public function settlements(Request $request, int $partner): JsonResponse
    {
        $user = $this->user($request);
        abort_unless($user->can('wallets.view'), 403);
        $model = $this->visible($user)->whereKey($partner)->firstOrFail();
        $settlements = Settlement::query()->where('partner_id', $model->id)->latest('period_end')->get()->map(fn (Settlement $settlement): array => [
            'id' => $settlement->id,
            'period_start' => $settlement->period_start->toDateString(),
            'period_end' => $settlement->period_end->toDateString(),
            'currency' => $settlement->currency,
            'opening_amount' => $settlement->opening_amount,
            'activity_amount' => $settlement->activity_amount,
            'closing_amount' => $settlement->closing_amount,
            'due_amount' => $settlement->due_amount,
            'status' => $settlement->status,
        ])->values();

        return response()->json(['partner_id' => $model->id, 'data' => $settlements]);
    }

    public function createSettlement(Request $request, int $partner, GeneratePartnerSettlement $generate): JsonResponse
    {
        $user = $this->user($request);
        abort_unless($user->can('settlements.approve'), 403);
        $validated = $request->validate(['period_start' => ['required', 'date'], 'period_end' => ['required', 'date', 'after_or_equal:period_start'], 'currency' => ['required', 'string', 'size:3']]);
        $model = $this->visible($user)->whereKey($partner)->firstOrFail();
        $settlement = $generate->handle($model, CarbonImmutable::parse($validated['period_start']), CarbonImmutable::parse($validated['period_end']), strtoupper($validated['currency']));

        return response()->json($this->settlementPayload($settlement), 201);
    }

    public function approveSettlement(Request $request, int $settlement, ApprovePartnerSettlement $approve): JsonResponse
    {
        $user = $this->user($request);
        abort_unless($user->can('settlements.approve'), 403);
        $model = Settlement::query()->whereKey($settlement)->firstOrFail();
        abort_unless($this->visible($user)->whereKey($model->partner_id)->exists(), 404);
        $approved = $approve->handle($model, $user);

        return response()->json($this->settlementPayload($approved));
    }

    public function paySettlement(Request $request, int $settlement, PayPartnerSettlement $pay): JsonResponse
    {
        $user = $this->user($request);
        abort_unless($user->can('settlements.approve'), 403);
        $model = Settlement::query()->whereKey($settlement)->firstOrFail();
        abort_unless($this->visible($user)->whereKey($model->partner_id)->exists(), 404);
        $paid = $pay->handle($model, $user);

        return response()->json($this->settlementPayload($paid));
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

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }

    /** @return array<string, mixed> */
    private function summary(Partner $partner): array
    {
        return ['id' => $partner->id, 'name' => $partner->name, 'code' => $partner->code, 'parent_id' => $partner->parent_id, 'depth' => $partner->depth, 'currency' => $partner->currency, 'balance_amount' => $partner->wallet->balance_amount, 'low_balance_threshold' => $partner->low_balance_threshold];
    }

    /** @return array<string, mixed> */
    private function settlementPayload(Settlement $settlement): array
    {
        return ['id' => $settlement->id, 'partner_id' => $settlement->partner_id, 'period_start' => $settlement->period_start->toDateString(), 'period_end' => $settlement->period_end->toDateString(), 'currency' => $settlement->currency, 'opening_amount' => $settlement->opening_amount, 'activity_amount' => $settlement->activity_amount, 'closing_amount' => $settlement->closing_amount, 'due_amount' => $settlement->due_amount, 'status' => $settlement->status, 'journal_entry_id' => $settlement->journal_entry_id];
    }
}
