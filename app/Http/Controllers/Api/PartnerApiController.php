<?php

namespace App\Http\Controllers\Api;

use App\Actions\FundPartnerWallet;
use App\Http\Controllers\Controller;
use App\Models\Partner;
use App\Models\PartnerWallet;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Database\Eloquent\Builder;
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
}
