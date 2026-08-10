<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\Customer;

final readonly class GetCustomerDetails implements Action
{
    /** @return array<string, mixed> */
    public function handle(Customer $customer): array
    {
        $customer->load([
            'zone',
            'services.plan',
            'services.router',
            'services.events' => fn ($query) => $query->latest()->limit(10),
            'invoices' => fn ($query) => $query->latest('issued_at')->limit(20),
            'payments' => fn ($query) => $query->latest('received_at')->limit(20),
            'tickets' => fn ($query) => $query->latest('updated_at')->limit(10),
        ]);

        $timeline = collect([
            [
                'type' => 'customer_created',
                'title' => 'Customer record created',
                'detail' => 'Account is ready for operations.',
                'created_at' => $customer->created_at?->toIso8601String(),
            ],
        ]);

        foreach ($customer->services as $service) {
            foreach ($service->events as $event) {
                $timeline->push([
                    'type' => 'service_event',
                    'title' => str($event->event_type)->replace('_', ' ')->title()->toString(),
                    'detail' => $service->username.' · '.($event->to_status ?? 'recorded'),
                    'created_at' => $event->created_at?->toIso8601String(),
                ]);
            }
        }

        foreach ($customer->invoices as $invoice) {
            $timeline->push([
                'type' => 'invoice',
                'title' => 'Invoice '.$invoice->number,
                'detail' => 'Invoice '.$invoice->status->value,
                'created_at' => ($invoice->issued_at ?? $invoice->created_at)?->toIso8601String(),
                'amount' => $invoice->total_amount,
                'currency' => $invoice->currency,
            ]);
        }

        foreach ($customer->payments as $payment) {
            $timeline->push([
                'type' => 'payment',
                'title' => 'Payment '.$payment->number,
                'detail' => ucfirst($payment->method).' · '.$payment->status->value,
                'created_at' => ($payment->received_at ?? $payment->created_at)?->toIso8601String(),
                'amount' => $payment->amount,
                'currency' => $payment->currency,
            ]);
        }

        return [
            ...$customer->only(['id', 'public_id', 'code', 'first_name', 'last_name', 'phone', 'email', 'address', 'status', 'anonymized_at', 'balance_amount', 'balance_currency']),
            'zone' => $customer->zone?->only(['id', 'name', 'code']),
            'services' => $customer->services->map(fn ($service): array => [
                'id' => $service->id,
                'public_id' => $service->public_id,
                'username' => $service->username,
                'status' => $service->status->value,
                'network_state' => $service->network_state->value,
                'provisioning_mode' => $service->provisioning_mode->value,
                'suspension_reason' => $service->suspension_reason,
                'expires_at' => $service->expires_at?->toIso8601String(),
                'plan' => $service->plan?->only(['id', 'public_id', 'name', 'download_kbps', 'upload_kbps', 'amount_minor', 'currency']),
                'router' => $service->router?->only(['public_id', 'name']),
            ])->values()->all(),
            'invoices' => $customer->invoices->map(fn ($invoice): array => [
                'public_id' => $invoice->public_id,
                'number' => $invoice->number,
                'status' => $invoice->status->value,
                'currency' => $invoice->currency,
                'total_amount' => $invoice->total_amount,
                'due_at' => $invoice->due_at?->toIso8601String(),
                'issued_at' => $invoice->issued_at?->toIso8601String(),
            ])->values()->all(),
            'payments' => $customer->payments->map(fn ($payment): array => [
                'public_id' => $payment->public_id,
                'number' => $payment->number,
                'status' => $payment->status->value,
                'currency' => $payment->currency,
                'amount' => $payment->amount,
                'method' => $payment->method,
                'received_at' => $payment->received_at?->toIso8601String(),
            ])->values()->all(),
            'tickets' => $customer->tickets->map(fn ($ticket): array => [
                'public_id' => $ticket->public_id,
                'number' => $ticket->number,
                'subject' => $ticket->subject,
                'priority' => $ticket->priority,
                'status' => $ticket->status->value,
                'due_at' => $ticket->due_at?->toIso8601String(),
                'updated_at' => $ticket->updated_at?->toIso8601String(),
            ])->values()->all(),
            'timeline' => $timeline->sortByDesc('created_at')->values()->all(),
        ];
    }
}
