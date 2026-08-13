<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\CashShift;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\FieldInventorySale;
use App\Models\InventoryItem;
use App\Models\Invoice;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\DocumentNumberGenerator;
use App\Support\StockQuantity;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use DomainException;
use Illuminate\Support\Facades\DB;

final readonly class RecordFieldInventorySale implements Action
{
    public function __construct(private DocumentNumberGenerator $numbers, private IssueInvoice $issueInvoice, private RecordPayment $recordPayment) {}

    /** @param list<array{inventory_item_id: int, quantity: string, unit_amount: int}> $lines */
    public function handle(User $collector, Customer $customer, Warehouse $warehouse, array $lines, string $currency, string $method, string $idempotencyKey, ?string $note = null): FieldInventorySale
    {
        $existing = FieldInventorySale::query()->where('idempotency_key', $idempotencyKey)->first();
        if ($existing instanceof FieldInventorySale) {
            return $existing->load(['lines.item', 'invoice', 'payment']);
        }
        if ((int) $collector->tenant_id !== (int) $customer->tenant_id || (int) $warehouse->tenant_id !== (int) $collector->tenant_id || (int) $warehouse->assigned_user_id !== $collector->id) {
            throw new DomainException('The customer and assigned stock location must belong to this workspace.');
        }
        if ($lines === []) {
            throw new DomainException('Add at least one sale item.');
        }
        $currency = strtoupper(trim($currency));
        if (! Currency::query()->where('code', $currency)->where('is_active', true)->exists()) {
            throw new DomainException('Choose an active workspace currency.');
        }
        if (! in_array($method, ['cash', 'card', 'bank_transfer', 'mobile_wallet'], true)) {
            throw new DomainException('Choose a supported payment method.');
        }
        $shift = $method === 'cash' ? CashShift::query()->where('user_id', $collector->id)->where('status', 'open')->latest('opened_at')->first() : null;
        if ($method === 'cash' && ! $shift instanceof CashShift) {
            throw new DomainException('Open a cash shift before recording a cash sale.');
        }

        return DB::transaction(function () use ($collector, $customer, $warehouse, $lines, $currency, $method, $idempotencyKey, $note, $shift): FieldInventorySale {
            $prepared = [];
            $total = 0;
            foreach ($lines as $line) {
                $item = InventoryItem::query()->findOrFail($line['inventory_item_id']);
                $quantity = StockQuantity::normalize($line['quantity']);
                if ($item->is_serialized || ! $item->is_active || $line['unit_amount'] < 1) {
                    throw new DomainException('Every sale line needs active bulk stock, a positive quantity, and a positive unit price.');
                }
                $balance = StockBalance::query()->where('warehouse_id', $warehouse->id)->where('inventory_item_id', $item->id)->lockForUpdate()->first();
                if (! $balance instanceof StockBalance || StockQuantity::greaterThan($quantity, (string) $balance->quantity)) {
                    throw new DomainException('Insufficient stock for '.$item->name.'.');
                }
                $lineTotal = BigDecimal::of($quantity)->multipliedBy($line['unit_amount'])->toScale(0, RoundingMode::HalfUp)->toInt();
                if ($lineTotal < 1) {
                    throw new DomainException('The calculated sale line total must be positive.');
                }
                $total += $lineTotal;
                $prepared[] = compact('item', 'quantity', 'balance', 'lineTotal') + ['unitAmount' => $line['unit_amount']];
            }

            $invoice = Invoice::create(['number' => $this->numbers->next('invoice', 'INV'), 'customer_id' => $customer->id, 'status' => 'draft', 'currency' => $currency, 'subtotal_amount' => $total, 'tax_amount' => 0, 'total_amount' => $total, 'due_at' => now(), 'metadata' => ['source' => 'field_inventory_sale']]);
            foreach ($prepared as $row) {
                $invoice->lines()->create(['description' => $row['item']->name.' × '.$row['quantity'], 'quantity' => 1, 'unit_amount' => $row['lineTotal'], 'total_amount' => $row['lineTotal'], 'currency' => $currency, 'price_snapshot' => ['inventory_item_id' => $row['item']->id, 'sku' => $row['item']->sku, 'stock_quantity' => $row['quantity'], 'unit_amount_minor' => $row['unitAmount']]]);
            }
            $invoice = $this->issueInvoice->handle($invoice, $collector);
            $sale = FieldInventorySale::create(['customer_id' => $customer->id, 'warehouse_id' => $warehouse->id, 'collector_id' => $collector->id, 'invoice_id' => $invoice->id, 'status' => 'posted', 'currency' => $currency, 'total_amount' => $total, 'payment_method' => $method, 'idempotency_key' => $idempotencyKey, 'note' => filled($note) ? trim((string) $note) : null, 'sold_at' => now()]);
            foreach ($prepared as $row) {
                $row['balance']->forceFill(['quantity' => StockQuantity::subtract((string) $row['balance']->quantity, $row['quantity'])])->save();
                $sale->lines()->create(['inventory_item_id' => $row['item']->id, 'quantity' => $row['quantity'], 'unit_amount' => $row['unitAmount'], 'total_amount' => $row['lineTotal']]);
                StockMovement::create(['inventory_item_id' => $row['item']->id, 'warehouse_id' => $warehouse->id, 'actor_id' => $collector->id, 'movement_type' => 'field_sale', 'quantity' => StockQuantity::subtract('0.000', $row['quantity']), 'note' => $note, 'occurred_at' => now(), 'metadata' => ['field_sale_id' => $sale->public_id, 'customer_id' => $customer->public_id]]);
            }
            $payment = $this->recordPayment->handle($customer, $total, $currency, $method, 'field-sale:'.$idempotencyKey, $invoice, $collector, $shift, reference: $sale->public_id, metadata: ['field_inventory_sale_id' => $sale->public_id]);
            $sale->forceFill(['payment_id' => $payment->id])->save();

            return $sale->refresh()->load(['lines.item', 'invoice', 'payment']);
        });
    }
}
