<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\Customer;
use App\Models\Service;
use App\Models\UsageDaily;
use Carbon\CarbonInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class GetPortalUsage implements Action
{
    /** @return list<array<string, mixed>> */
    public function handle(Customer $customer, Service $service, CarbonInterface $from, CarbonInterface $to): array
    {
        if ((int) $service->customer_id !== (int) $customer->id) {
            throw new NotFoundHttpException;
        }

        return UsageDaily::query()
            ->where('service_id', $service->id)
            ->whereBetween('usage_date', [$from->toDateString(), $to->toDateString()])
            ->orderBy('usage_date')
            ->get()
            ->map(fn (UsageDaily $usage): array => [
                'date' => $usage->usage_date->toDateString(),
                'input_octets' => $usage->input_octets,
                'output_octets' => $usage->output_octets,
                'total_octets' => $usage->total_octets,
            ])->all();
    }
}
