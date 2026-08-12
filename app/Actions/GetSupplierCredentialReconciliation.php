<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Enums\CredentialStatus;
use App\Models\CredentialBatch;
use App\Models\UpstreamCredential;
use Carbon\CarbonImmutable;

final readonly class GetSupplierCredentialReconciliation implements Action
{
    /** @return array<string, mixed> */
    public function handle(CarbonImmutable $from, CarbonImmutable $to, int $expiringDays = 30): array
    {
        $asOf = $to->endOfDay();
        $expiryEnd = $asOf->addDays($expiringDays);
        $batches = CredentialBatch::query()
            ->with(['supplier', 'credentials:id,credential_batch_id,status,expires_at'])
            ->get();
        $totals = $this->emptyCounts();
        $suppliers = [];

        foreach ($batches as $batch) {
            $supplier = $batch->supplier;
            $supplierKey = (string) ($supplier->id ?? 'unknown');
            $suppliers[$supplierKey] ??= $this->supplierRow($supplier->name, $supplier->code);
            $supplierRow = &$suppliers[$supplierKey];
            $importedAt = CarbonImmutable::parse((string) $batch->getAttribute('imported_at'));
            $purchased = $importedAt->betweenIncluded($from->startOfDay(), $to->endOfDay());
            $batchPurchased = $purchased ? $batch->credentials->count() : 0;

            if ($purchased) {
                $supplierRow['purchased'] += $batchPurchased;
                $this->addCost($supplierRow['cost_by_currency'], $batch->currency, $this->batchCost($batch, $batchPurchased));
                $contractKey = (string) ($batch->contract_reference ?: 'unspecified');
                $supplierRow['contracts'][$contractKey] ??= [
                    'reference' => $batch->contract_reference,
                    'purchased' => 0,
                    'cost_by_currency' => [],
                ];
                $supplierRow['contracts'][$contractKey]['purchased'] += $batchPurchased;
                $this->addCost(
                    $supplierRow['contracts'][$contractKey]['cost_by_currency'],
                    $batch->currency,
                    $this->batchCost($batch, $batchPurchased),
                );
                $totals['purchased'] += $batchPurchased;
            }

            /** @var UpstreamCredential $credential */
            foreach ($batch->credentials as $credential) {
                $status = $credential->status;
                if ($status === CredentialStatus::Assigned || $status === CredentialStatus::Active) {
                    $supplierRow['assigned']++;
                    $totals['assigned']++;
                }
                if ($status === CredentialStatus::Available) {
                    $supplierRow['available']++;
                    $totals['available']++;
                }
                $expiresAt = $credential->getAttribute('expires_at');
                if ($expiresAt !== null && CarbonImmutable::parse((string) $expiresAt)->betweenIncluded($asOf, $expiryEnd)) {
                    $supplierRow['expiring']++;
                    $totals['expiring']++;
                }
                if ($status === CredentialStatus::Revoked || $status === CredentialStatus::Invalid) {
                    $supplierRow['revoked_invalid']++;
                    $totals['revoked_invalid']++;
                }
            }
            unset($supplierRow);
        }

        return [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'expiring_days' => $expiringDays,
            'totals' => $totals,
            'by_supplier' => collect($suppliers)->map(function (array $supplier): array {
                $supplier['contracts'] = array_values($supplier['contracts']);

                return $supplier;
            })->values()->all(),
        ];
    }

    /** @return array{purchased: int, assigned: int, available: int, expiring: int, revoked_invalid: int} */
    private function emptyCounts(): array
    {
        return ['purchased' => 0, 'assigned' => 0, 'available' => 0, 'expiring' => 0, 'revoked_invalid' => 0];
    }

    /** @return array<string, mixed> */
    private function supplierRow(?string $name, ?string $code): array
    {
        return [
            'name' => $name ?? 'Unknown supplier',
            'code' => $code,
            'purchased' => 0,
            'assigned' => 0,
            'available' => 0,
            'expiring' => 0,
            'revoked_invalid' => 0,
            'cost_by_currency' => [],
            'contracts' => [],
        ];
    }

    private function batchCost(CredentialBatch $batch, int $quantity): int
    {
        if ($batch->total_cost_amount !== null) {
            return (int) $batch->total_cost_amount;
        }

        return $batch->unit_cost_amount === null ? 0 : (int) $batch->unit_cost_amount * $quantity;
    }

    /** @param array<string, int> $costs */
    private function addCost(array &$costs, ?string $currency, int $amount): void
    {
        $currency = strtoupper(trim((string) $currency));
        if ($currency === '' || $amount === 0) {
            return;
        }
        $costs[$currency] = ($costs[$currency] ?? 0) + $amount;
    }
}
