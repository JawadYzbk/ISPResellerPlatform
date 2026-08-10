<?php

namespace App\Http\Controllers\Web;

use App\Actions\CreateExchangeRate;
use App\Actions\ListExchangeRates;
use App\Http\Controllers\Controller;
use App\Http\Requests\ExchangeRateRequest;
use App\Models\ExchangeRate;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;

final class ExchangeRateOperationsController extends Controller
{
    public function index(Request $request, ListExchangeRates $listRates): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->can('settings.manage'), 403);
        $rates = $listRates->handle(
            $request->string('base_currency')->toString() ?: null,
            $request->string('quote_currency')->toString() ?: null,
        );
        $rows = $rates->getCollection()->map(fn (ExchangeRate $rate): array => [
            'id' => $rate->id,
            'base_currency' => $rate->base_currency,
            'quote_currency' => $rate->quote_currency,
            'rate_numerator' => $rate->rate_numerator,
            'rate_denominator' => $rate->rate_denominator,
            'effective_from' => $rate->effective_from?->toIso8601String(),
            'source' => $rate->source,
        ])->values();
        $rates = new LengthAwarePaginator(
            $rows,
            $rates->total(),
            $rates->perPage(),
            $rates->currentPage(),
            ['path' => $request->url(), 'query' => $request->query()],
        );

        return Inertia::render('Billing/ExchangeRates', [
            'rates' => $rates,
            'filters' => $request->only(['base_currency', 'quote_currency']),
        ]);
    }

    public function store(ExchangeRateRequest $request, CreateExchangeRate $createRate): RedirectResponse
    {
        abort_unless($request->user()?->can('settings.manage') === true, 403);
        $createRate->handle($request->validated());

        return redirect()->route('billing.exchange-rates')->with('success', 'Exchange rate added to the effective-dated history.');
    }
}
