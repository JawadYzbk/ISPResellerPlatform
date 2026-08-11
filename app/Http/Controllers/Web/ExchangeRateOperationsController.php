<?php

namespace App\Http\Controllers\Web;

use App\Actions\CreateExchangeRate;
use App\Actions\GetCurrencyCatalog;
use App\Actions\ImportFrankfurterExchangeRates;
use App\Actions\ListExchangeRates;
use App\Http\Controllers\Controller;
use App\Http\Requests\ExchangeRateRequest;
use App\Models\ExchangeRate;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

final class ExchangeRateOperationsController extends Controller
{
    public function index(Request $request, ListExchangeRates $listRates, GetCurrencyCatalog $currencyCatalog): Response
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
        $tenant = Tenant::query()->find($user->tenant_id);

        return Inertia::render('Billing/ExchangeRates', [
            'rates' => $rates,
            'filters' => $request->only(['base_currency', 'quote_currency']),
            'frankfurterEnabled' => (bool) config('services.frankfurter.enabled', false),
            'workspaceCurrencies' => [
                'base' => $tenant instanceof Tenant ? $tenant->base_currency : null,
                'collection' => $tenant instanceof Tenant ? $tenant->collection_currency : null,
            ],
            'currencies' => $currencyCatalog->handle(),
        ]);
    }

    public function store(ExchangeRateRequest $request, CreateExchangeRate $createRate): RedirectResponse
    {
        abort_unless($request->user()?->can('settings.manage') === true, 403);
        $createRate->handle($request->validated());

        return redirect()->route('billing.exchange-rates')->with('success', 'Exchange rate added to the effective-dated history.');
    }

    public function sync(Request $request, ImportFrankfurterExchangeRates $import): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->can('settings.manage'), 403);
        abort_unless((bool) config('services.frankfurter.enabled', false), 422, 'Frankfurter sync is disabled.');

        $tenant = Tenant::query()->find($user->tenant_id);
        abort_unless($tenant instanceof Tenant, 403);
        $quotes = array_values(array_unique([
            ...array_map('trim', array_filter(config('services.frankfurter.quotes', []), is_string(...))),
            $tenant->collection_currency,
        ]));

        try {
            $count = $import->handle($tenant, $quotes);
        } catch (\Throwable $exception) {
            Log::warning('Frankfurter web sync failed.', ['tenant_id' => $tenant->id, 'exception' => $exception]);

            return back()->with('error', 'Frankfurter could not provide rates right now. Existing rates were not changed.');
        }

        return back()->with('success', $count.' Frankfurter rate(s) imported.');
    }
}
