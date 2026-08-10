<?php

namespace App\Http\Controllers\Web;

use App\Actions\GetFinanceReport;
use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class ReportController extends Controller
{
    public function finance(Request $request, GetFinanceReport $report): Response
    {
        abort_unless($request->user()?->can('reports.finance'), 403);
        $validated = $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date', 'after_or_equal:from']]);
        $from = CarbonImmutable::parse($validated['from'] ?? now()->startOfMonth()->toDateString());
        $to = CarbonImmutable::parse($validated['to'] ?? now()->toDateString());

        return Inertia::render('Reports/Finance', ['report' => $report->handle($from, $to)]);
    }
}
