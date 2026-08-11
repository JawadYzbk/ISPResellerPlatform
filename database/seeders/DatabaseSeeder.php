<?php

namespace Database\Seeders;

use App\Actions\IssueInvoice;
use App\Actions\RecordPayment;
use App\Domain\Ledger\JournalLineInput;
use App\Domain\Ledger\PostJournalEntry;
use App\Enums\CustomerStatus;
use App\Enums\InvoiceStatus;
use App\Enums\NetworkState;
use App\Enums\ProvisioningMode;
use App\Enums\ServiceStatus;
use App\Enums\TicketStatus;
use App\Enums\WorkOrderStatus;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\InventoryItem;
use App\Models\InventoryUnit;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\LedgerAccount;
use App\Models\LedgerEntry;
use App\Models\Plan;
use App\Models\PlanPrice;
use App\Models\Pop;
use App\Models\Router;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WorkOrder;
use App\Models\Zone;
use App\Support\Tenancy;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /** @var list<array{email: string, name: string, role: string}> */
    private const DEMO_STAFF_ACCOUNTS = [
        ['email' => 'admin@example.com', 'name' => 'Maya Haddad', 'role' => 'tenant_owner'],
        ['email' => 'operations.manager@example.com', 'name' => 'Karim Nasser', 'role' => 'operations_manager'],
        ['email' => 'billing.manager@example.com', 'name' => 'Rita Khoury', 'role' => 'billing_manager'],
        ['email' => 'cashier@example.com', 'name' => 'Hadi Salem', 'role' => 'cashier'],
        ['email' => 'collector@example.com', 'name' => 'Nadia Haddad', 'role' => 'collector'],
        ['email' => 'support.agent@example.com', 'name' => 'Tarek Mansour', 'role' => 'support_agent'],
        ['email' => 'technician@example.com', 'name' => 'Fadi Saad', 'role' => 'technician'],
        ['email' => 'network.admin@example.com', 'name' => 'Jad Youssef', 'role' => 'network_administrator'],
        ['email' => 'reseller.owner@example.com', 'name' => 'Samer Khalil', 'role' => 'reseller_owner'],
        ['email' => 'reseller.staff@example.com', 'name' => 'Lara Bitar', 'role' => 'reseller_staff'],
        ['email' => 'auditor@example.com', 'name' => 'Mira Farah', 'role' => 'auditor'],
    ];

    public function run(): void
    {
        $tenant = Tenant::updateOrCreate(['slug' => 'northline'], ['name' => 'Northline Broadband', 'base_currency' => 'USD', 'collection_currency' => 'USD', 'timezone' => 'Asia/Beirut', 'locale' => 'en']);

        $this->call(CapabilitySeeder::class);

        $staff = [];
        foreach (self::DEMO_STAFF_ACCOUNTS as $account) {
            $user = User::updateOrCreate(
                ['email' => $account['email']],
                ['tenant_id' => $tenant->id, 'name' => $account['name'], 'password' => Hash::make('password'), 'role' => $account['role'], 'locale' => 'en', 'email_verified_at' => now()],
            );
            app(Tenancy::class)->run($tenant, fn (): mixed => $user->syncRoles([$account['role']]));
            $staff[$account['role']] = $user;
        }

        $admin = $staff['tenant_owner'];

        app(Tenancy::class)->run($tenant, function () use ($admin): void {
            $postJournalEntry = app(PostJournalEntry::class);
            $zones = collect([
                ['name' => 'Central District', 'code' => 'CENTRAL'],
                ['name' => 'Hillside', 'code' => 'HILL'],
                ['name' => 'Coastal Road', 'code' => 'COAST'],
            ])->mapWithKeys(function (array $data): array {
                $zone = Zone::updateOrCreate(['code' => $data['code']], $data);

                return [$data['code'] => $zone];
            });
            Branch::updateOrCreate(['code' => 'HQ'], ['name' => 'Main Office', 'is_default' => true, 'address' => '12 Cedar Street']);

            $pops = collect([
                ['name' => 'Central POP', 'code' => 'POP-CENTRAL', 'address' => '12 Cedar Street'],
                ['name' => 'Coastal POP', 'code' => 'POP-COASTAL', 'address' => '88 Coastal Road'],
            ])->mapWithKeys(function (array $data): array {
                $pop = Pop::updateOrCreate(['code' => $data['code']], $data + ['status' => 'active']);

                return [$data['code'] => $pop];
            });

            $routers = collect([
                ['name' => 'Central MikroTik', 'host' => '10.20.0.1', 'pop_id' => $pops['POP-CENTRAL']->id],
                ['name' => 'Coastal MikroTik', 'host' => '10.20.1.1', 'pop_id' => $pops['POP-COASTAL']->id],
            ])->mapWithKeys(function (array $data): array {
                $router = Router::updateOrCreate(
                    ['host' => $data['host'], 'api_port' => 8728],
                    $data + ['api_port' => 8728, 'username' => 'demo-api', 'password_encrypted' => 'demo-router-password', 'tls_verify' => false, 'status' => 'online', 'last_seen_at' => now(), 'metadata' => ['demo' => true, 'driver' => 'mikrotik']],
                );

                return [$data['host'] => $router];
            });
            $routerIds = $routers->pluck('id')->values()->all();

            $warehouse = Warehouse::updateOrCreate(['code' => 'MAIN'], ['name' => 'Main Warehouse', 'type' => 'warehouse', 'assigned_user_id' => $admin->id, 'is_active' => true]);
            foreach ([
                ['sku' => 'CPE-ROUTER', 'name' => 'Customer Wi-Fi Router', 'category' => 'router', 'units' => 20],
                ['sku' => 'CPE-ONU', 'name' => 'Fiber ONU', 'category' => 'onu', 'units' => 20],
                ['sku' => 'CPE-ANTENNA', 'name' => 'Outdoor Antenna', 'category' => 'antenna', 'units' => 10],
            ] as $definition) {
                $item = InventoryItem::updateOrCreate(
                    ['sku' => $definition['sku']],
                    ['name' => $definition['name'], 'category' => $definition['category'], 'is_serialized' => true, 'reorder_level' => 5, 'is_active' => true],
                );
                for ($unit = 1; $unit <= $definition['units']; $unit++) {
                    InventoryUnit::updateOrCreate(
                        ['serial_number' => $definition['sku'].'-'.str_pad((string) $unit, 4, '0', STR_PAD_LEFT)],
                        ['inventory_item_id' => $item->id, 'warehouse_id' => $warehouse->id, 'status' => 'available'],
                    );
                }
            }

            $historyStart = now()->subMonths(6)->startOfMonth();
            $currentStart = now()->startOfDay();
            $plans = collect([
                ['name' => 'Home 25', 'slug' => 'home-25', 'download_kbps' => 25_000, 'upload_kbps' => 5_000, 'duration_days' => 30, 'amount_minor' => 2500, 'currency' => 'USD'],
                ['name' => 'Home 50', 'slug' => 'home-50', 'download_kbps' => 50_000, 'upload_kbps' => 10_000, 'duration_days' => 30, 'amount_minor' => 3500, 'currency' => 'USD'],
                ['name' => 'Business 100', 'slug' => 'business-100', 'download_kbps' => 100_000, 'upload_kbps' => 25_000, 'duration_days' => 30, 'amount_minor' => 6500, 'currency' => 'USD'],
                ['name' => 'Starter 10', 'slug' => 'starter-10', 'download_kbps' => 10_000, 'upload_kbps' => 2_000, 'duration_days' => 30, 'amount_minor' => 1800, 'currency' => 'USD'],
            ])->mapWithKeys(function (array $data) use ($historyStart, $currentStart): array {
                $plan = Plan::updateOrCreate(['slug' => $data['slug']], $data);
                PlanPrice::firstOrCreate(
                    ['plan_id' => $plan->id, 'currency' => $data['currency'], 'effective_from' => $historyStart],
                    ['amount_minor' => $data['amount_minor'], 'effective_to' => $currentStart],
                );
                PlanPrice::firstOrCreate(
                    ['plan_id' => $plan->id, 'currency' => $data['currency'], 'effective_from' => $currentStart],
                    ['amount_minor' => $data['amount_minor']],
                );

                return [$data['slug'] => $plan];
            });

            $customers = [
                ['first_name' => 'Rami', 'last_name' => 'Saad', 'phone' => '+961 70 123 456', 'email' => 'rami@example.test', 'zone' => 'CENTRAL', 'plan' => 'home-50', 'status' => CustomerStatus::Active, 'service_status' => ServiceStatus::Active, 'expires' => now()->addDays(18)],
                ['first_name' => 'Lina', 'last_name' => 'Khoury', 'phone' => '+961 71 234 567', 'email' => 'lina@example.test', 'zone' => 'HILL', 'plan' => 'business-100', 'status' => CustomerStatus::Active, 'service_status' => ServiceStatus::Active, 'expires' => now()->addDays(4)],
                ['first_name' => 'Omar', 'last_name' => 'Nasser', 'phone' => '+961 76 345 678', 'email' => null, 'zone' => 'COAST', 'plan' => 'starter-10', 'status' => CustomerStatus::Active, 'service_status' => ServiceStatus::Suspended, 'expires' => now()->subDays(3)],
                ['first_name' => 'Nour', 'last_name' => 'Mansour', 'phone' => '+961 70 456 789', 'email' => 'nour@example.test', 'zone' => 'CENTRAL', 'plan' => 'home-25', 'status' => CustomerStatus::Active, 'service_status' => ServiceStatus::Pending, 'expires' => now()->addDays(1)],
                ['first_name' => 'Tarek', 'last_name' => 'Fadel', 'phone' => '+961 70 567 890', 'email' => null, 'zone' => 'HILL', 'plan' => 'home-50', 'status' => CustomerStatus::Active, 'service_status' => ServiceStatus::Active, 'expires' => now()->addDays(22)],
                ['first_name' => 'Sara', 'last_name' => 'Haddad', 'phone' => '+961 71 678 901', 'email' => 'sara@example.test', 'zone' => 'COAST', 'plan' => 'home-25', 'status' => CustomerStatus::Active, 'service_status' => ServiceStatus::Active, 'expires' => now()->addDays(9)],
            ];

            for ($index = count($customers); $index < 200; $index++) {
                $number = $index + 1;
                $serviceStatus = $number % 11 === 0 ? ServiceStatus::Suspended : ($number % 17 === 0 ? ServiceStatus::Pending : ServiceStatus::Active);
                $customers[] = [
                    'first_name' => 'Demo',
                    'last_name' => 'Customer '.$number,
                    'phone' => '+961 70 '.str_pad((string) (800000 + $number), 6, '0', STR_PAD_LEFT),
                    'email' => 'demo.customer.'.$number.'@example.test',
                    'zone' => ['CENTRAL', 'HILL', 'COAST'][$number % 3],
                    'plan' => ['home-25', 'home-50', 'business-100', 'starter-10'][$number % 4],
                    'status' => CustomerStatus::Active,
                    'service_status' => $serviceStatus,
                    'expires' => $serviceStatus === ServiceStatus::Suspended ? now()->subDays(3 + ($number % 5)) : now()->addDays(7 + ($number % 30)),
                ];
            }

            foreach ($customers as $index => $data) {
                $openingBalance = $data['service_status'] === ServiceStatus::Suspended ? 3500 : 0;
                $customer = Customer::firstOrNew(['code' => 'CUS-'.str_pad((string) ($index + 1), 5, '0', STR_PAD_LEFT)]);
                $customer->fill(['zone_id' => $zones[$data['zone']]->id, 'first_name' => $data['first_name'], 'last_name' => $data['last_name'], 'phone' => $data['phone'], 'phone_normalized' => preg_replace('/\D+/', '', $data['phone']), 'email' => $data['email'], 'address' => ($index + 10).' Cedar Street', 'status' => $data['status'], 'balance_currency' => 'USD']);
                $hasLedgerEntries = LedgerEntry::query()->where('customer_id', $customer->id)->exists();
                if (! $hasLedgerEntries) {
                    $customer->balance_amount = 0;
                }
                $customer->save();

                if ($openingBalance > 0 && ! $hasLedgerEntries) {
                    $postJournalEntry->post('Demo opening customer balance', [
                        new JournalLineInput(LedgerAccount::query()->where('code', '1100')->firstOrFail()->id, 'USD', debitAmount: $openingBalance, customerId: $customer->id),
                        new JournalLineInput(LedgerAccount::query()->where('code', '4000')->firstOrFail()->id, 'USD', creditAmount: $openingBalance),
                    ]);
                }

                Service::updateOrCreate(['username' => strtolower($data['first_name']).'.'.($index + 1)], ['customer_id' => $customer->id, 'plan_id' => $plans[$data['plan']]->id, 'router_id' => $routerIds[$index % count($routerIds)], 'password_encrypted' => 'demo-secret-not-for-production', 'status' => $data['service_status'], 'provisioning_mode' => ProvisioningMode::Manual, 'network_state' => $data['service_status'] === ServiceStatus::Active ? NetworkState::InSync : NetworkState::PendingSync, 'suspension_reason' => $data['service_status'] === ServiceStatus::Suspended ? 'auto_overdue' : null, 'activated_at' => now()->subMonths(4), 'expires_at' => $data['expires']]);
            }

            $services = Service::query()->with(['customer', 'plan'])->orderBy('id')->get();
            $issueInvoice = app(IssueInvoice::class);
            $recordPayment = app(RecordPayment::class);
            foreach ($services as $serviceIndex => $service) {
                foreach (range(5, 0) as $monthOffset) {
                    $period = now()->subMonths($monthOffset)->startOfMonth();
                    $price = $service->plan->priceAt($period);
                    $amount = $price === null ? $service->plan->amount_minor : $price->amount_minor;
                    $invoiceNumber = 'INV-DEMO-'.str_pad((string) ($serviceIndex + 1), 3, '0', STR_PAD_LEFT).'-'.$period->format('Ym');
                    $invoice = Invoice::firstOrNew(['number' => $invoiceNumber]);
                    if (! $invoice->exists) {
                        $invoice->fill(['customer_id' => $service->customer_id, 'status' => InvoiceStatus::Draft, 'currency' => 'USD', 'subtotal_amount' => $amount, 'tax_amount' => 0, 'total_amount' => $amount, 'due_at' => $period->copy()->addDays(14), 'issued_at' => $period->copy()->addDay()]);
                        $invoice->save();
                    }
                    InvoiceLine::firstOrCreate(['invoice_id' => $invoice->id, 'service_id' => $service->id], ['plan_id' => $service->plan_id, 'description' => $service->plan->name, 'quantity' => 1, 'unit_amount' => $amount, 'total_amount' => $amount, 'currency' => 'USD', 'price_snapshot' => ['amount_minor' => $amount, 'currency' => 'USD', 'period' => $period->toDateString()]]);
                    if ($invoice->status === InvoiceStatus::Draft) {
                        $invoice = $issueInvoice->handle($invoice, $admin);
                    }
                    $invoice->forceFill(['issued_at' => $period->copy()->addDay(), 'due_at' => $period->copy()->addDays(14)])->saveQuietly();

                    if (($serviceIndex + $monthOffset) % 11 === 0) {
                        continue;
                    }
                    $payment = $recordPayment->handle($service->customer, $invoice->total_amount, 'USD', 'cash', 'demo-payment:'.$invoiceNumber, $invoice, $admin);
                    $payment->forceFill(['received_at' => $period->copy()->addDays(3)])->saveQuietly();
                }
            }

            foreach ($services->take(12) as $index => $service) {
                Ticket::updateOrCreate(
                    ['number' => 'TKT-DEMO-'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT)],
                    ['customer_id' => $service->customer_id, 'service_id' => $service->id, 'subject' => ['Slow connection', 'Router replacement', 'Billing question'][$index % 3], 'description' => 'Demo ticket for acceptance walkthrough and operator triage.', 'priority' => $index % 4 === 0 ? 'high' : 'normal', 'status' => $index % 3 === 0 ? TicketStatus::InProgress : TicketStatus::Open, 'assigned_to' => $admin->id, 'due_at' => now()->addDays(1 + ($index % 3))],
                );
            }

            foreach ($services->take(24) as $index => $service) {
                WorkOrder::updateOrCreate(
                    ['number' => 'WO-DEMO-'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT)],
                    ['type' => $index % 2 === 0 ? 'installation' : 'maintenance', 'customer_id' => $service->customer_id, 'service_id' => $service->id, 'assigned_to' => $admin->id, 'status' => $index % 3 === 0 ? WorkOrderStatus::Assigned : WorkOrderStatus::Pending, 'scheduled_at' => now()->addDays(1 + ($index % 14))->setTime(9 + ($index % 6), 0), 'checklist' => ['signal_test' => false, 'customer_confirmation' => false], 'metadata' => ['demo' => true]],
                );
            }
        });
    }
}
