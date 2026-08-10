<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Builder;

final readonly class ExportCustomersCsv implements Action
{
    /** @param list<string> $selectedPublicIds */
    public function handle(?string $search, ?string $status, ?int $zoneId, ?string $expiresFrom, ?string $expiresTo, array $selectedPublicIds = []): string
    {
        $query = Customer::query()
            ->with(['zone', 'services'])
            ->search($search)
            ->when($status, fn (Builder $query): Builder => $query->where('status', $status))
            ->when($zoneId, fn (Builder $query): Builder => $query->where('zone_id', $zoneId))
            ->when($expiresFrom || $expiresTo, function (Builder $query) use ($expiresFrom, $expiresTo): void {
                $query->whereHas('services', function (Builder $services) use ($expiresFrom, $expiresTo): void {
                    $services->whereNotIn('status', ['terminated'])
                        ->whereNotNull('expires_at')
                        ->when($expiresFrom, fn (Builder $services): Builder => $services->whereDate('expires_at', '>=', $expiresFrom))
                        ->when($expiresTo, fn (Builder $services): Builder => $services->whereDate('expires_at', '<=', $expiresTo));
                });
            })
            ->when($selectedPublicIds !== [], fn (Builder $query): Builder => $query->whereIn('public_id', $selectedPublicIds))
            ->orderByDesc('created_at');

        $stream = fopen('php://temp', 'r+');
        if ($stream === false) {
            throw new \RuntimeException('Unable to create the customer export stream.');
        }

        fputcsv($stream, ['customer_id', 'customer_code', 'first_name', 'last_name', 'phone', 'email', 'status', 'zone', 'service_usernames', 'balance_minor', 'balance_currency']);
        $query->chunkById(500, function ($customers) use ($stream): void {
            foreach ($customers as $customer) {
                fputcsv($stream, [
                    $customer->public_id,
                    $customer->code,
                    $customer->first_name,
                    $customer->last_name,
                    $customer->phone,
                    $customer->email,
                    $customer->status->value,
                    $customer->zone?->name,
                    $customer->services->pluck('username')->implode('|'),
                    $customer->balance_amount,
                    $customer->balance_currency,
                ]);
            }
        });
        rewind($stream);
        $contents = stream_get_contents($stream);
        fclose($stream);

        return $contents === false ? '' : $contents;
    }
}
