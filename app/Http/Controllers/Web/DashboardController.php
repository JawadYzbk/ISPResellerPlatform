<?php

namespace App\Http\Controllers\Web;

use App\Actions\GetDashboardAttentionQueue;
use App\Actions\GetDashboardMetrics;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

final class DashboardController extends Controller
{
    public function __invoke(GetDashboardMetrics $getDashboardMetrics, GetDashboardAttentionQueue $getDashboardAttentionQueue): Response
    {
        return Inertia::render('Dashboard/Index', [
            'metrics' => Inertia::defer(fn (): array => $getDashboardMetrics->handle(), 'dashboard-metrics'),
            'attentionQueue' => Inertia::defer(fn (): array => $getDashboardAttentionQueue->handle(), 'dashboard-attention'),
        ]);
    }
}
