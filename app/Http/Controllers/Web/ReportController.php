<?php

namespace App\Http\Controllers\Web;

use App\Actions\ExportFinanceReportCsv;
use App\Actions\ExportFinanceReportXlsx;
use App\Actions\ExportOperationsReportCsv;
use App\Actions\ExportOperationsReportXlsx;
use App\Actions\GetFinanceReport;
use App\Actions\GetOperationsReport;
use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ReportController extends Controller
{
    public function finance(Request $request, GetFinanceReport $report, ExportFinanceReportCsv $export, ExportFinanceReportXlsx $xlsx): Response|StreamedResponse
    {
        abort_unless($request->user()?->can('reports.finance'), 403);
        $validated = $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date', 'after_or_equal:from']]);
        $from = CarbonImmutable::parse($validated['from'] ?? now()->startOfMonth()->toDateString());
        $to = CarbonImmutable::parse($validated['to'] ?? now()->toDateString());

        if ($request->string('format')->lower()->toString() === 'csv') {
            return response()->streamDownload(function () use ($export, $from, $to): void {
                echo $export->handle($from, $to);
            }, 'finance-report-'.$from->toDateString().'-'.$to->toDateString().'.csv', ['Content-Type' => 'text/csv']);
        }
        if ($request->string('format')->lower()->toString() === 'xlsx') {
            return response()->streamDownload(function () use ($xlsx, $from, $to): void {
                echo $xlsx->handle($from, $to);
            }, 'finance-report-'.$from->toDateString().'-'.$to->toDateString().'.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
        }

        return Inertia::render('Reports/Finance', ['report' => $report->handle($from, $to)]);
    }

    public function operations(Request $request, GetOperationsReport $report, ExportOperationsReportCsv $export, ExportOperationsReportXlsx $xlsx): Response|StreamedResponse
    {
        abort_unless($request->user()?->can('reports.operations'), 403);
        $validated = $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date', 'after_or_equal:from']]);
        $from = CarbonImmutable::parse($validated['from'] ?? now()->startOfMonth()->toDateString());
        $to = CarbonImmutable::parse($validated['to'] ?? now()->toDateString());

        if ($request->string('format')->lower()->toString() === 'csv') {
            return response()->streamDownload(function () use ($export, $from, $to): void {
                echo $export->handle($from, $to);
            }, 'operations-report-'.$from->toDateString().'-'.$to->toDateString().'.csv', ['Content-Type' => 'text/csv']);
        }
        if ($request->string('format')->lower()->toString() === 'xlsx') {
            return response()->streamDownload(function () use ($xlsx, $from, $to): void {
                echo $xlsx->handle($from, $to);
            }, 'operations-report-'.$from->toDateString().'-'.$to->toDateString().'.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
        }

        return Inertia::render('Reports/Operations', ['report' => $report->handle($from, $to)]);
    }
}
