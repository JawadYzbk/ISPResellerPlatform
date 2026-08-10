<?php

namespace App\Http\Controllers\Web;

use App\Actions\ResolvePartnerPrice;
use App\Http\Controllers\Controller;
use App\Models\Partner;
use App\Models\Plan;
use App\Models\Settlement;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class PartnerController extends Controller
{
    public function commercial(Request $request, ResolvePartnerPrice $prices): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->can('wallets.view'), 403);
        $partners = $this->visible($user)->orderBy('path')->get();
        $selectedId = (int) ($request->integer('partner') ?: ($user->partner_id ?? $partners->first()?->id));
        $partner = $partners->firstWhere('id', $selectedId);
        abort_unless($partner instanceof Partner, 404);
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

        return Inertia::render('Partners/Commercial', [
            'partners' => $partners->map(fn (Partner $item): array => ['id' => $item->id, 'name' => $item->name, 'code' => $item->code])->values(),
            'selectedPartner' => ['id' => $partner->id, 'name' => $partner->name, 'code' => $partner->code, 'currency' => $partner->currency],
            'catalog' => $catalog,
            'settlements' => $settlements,
            'showCost' => $showCost,
        ]);
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
