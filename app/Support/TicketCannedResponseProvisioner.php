<?php

namespace App\Support;

use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

final class TicketCannedResponseProvisioner
{
    /** @var array<string, array{category: string, body: string}> */
    private const DEFAULTS = [
        'Payment received' => [
            'category' => 'billing',
            'body' => 'Thanks for your payment. It has been recorded on your account. We will let you know if anything else is needed.',
        ],
        'Investigation in progress' => [
            'category' => 'support',
            'body' => 'Thanks for reporting this. Our team is investigating the issue and will update you as soon as we have a confirmed next step.',
        ],
        'Service restored' => [
            'category' => 'operations',
            'body' => 'The service issue has been resolved. Please try your connection again and reply here if the problem continues.',
        ],
    ];

    public function provision(Tenant $tenant): void
    {
        app(Tenancy::class)->run($tenant, function (): void {
            $tenantId = app(Tenancy::class)->requireId();
            $timestamp = now();
            $responses = [];

            foreach (self::DEFAULTS as $title => $definition) {
                $responses[] = [
                    'tenant_id' => $tenantId,
                    'public_id' => (string) \Illuminate\Support\Str::ulid(),
                    'title' => $title,
                    'body' => $definition['body'],
                    'category' => $definition['category'],
                    'is_active' => true,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ];
            }

            DB::table('ticket_canned_responses')->insertOrIgnore($responses);
        });
    }
}
