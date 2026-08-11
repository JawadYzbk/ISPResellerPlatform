<?php

namespace App\Http\Controllers\Web;

use App\Actions\GetDashboardAttentionQueue;
use App\Actions\GetDashboardMetrics;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class DashboardController extends Controller
{
    public function __invoke(Request $request, GetDashboardMetrics $getDashboardMetrics, GetDashboardAttentionQueue $getDashboardAttentionQueue): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return Inertia::render('Dashboard/Index', [
            'metrics' => Inertia::defer(fn (): array => $getDashboardMetrics->handle(), 'dashboard-metrics'),
            'attentionQueue' => Inertia::defer(fn (): array => $getDashboardAttentionQueue->handle(user: $user), 'dashboard-attention'),
        ]);
    }
}
