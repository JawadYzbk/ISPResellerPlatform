<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\CurrentSession;
use App\Models\Service;
use App\Support\Tenancy;
use Carbon\CarbonImmutable;
use DomainException;

final readonly class UpsertCurrentSession implements Action
{
    public function handle(Service $service, string $sessionId, string $nasname, CarbonImmutable $lastSeen, int $inputOctets = 0, int $outputOctets = 0, ?string $framedIp = null, ?CarbonImmutable $startedAt = null): CurrentSession
    {
        if ($service->tenant_id !== app(Tenancy::class)->requireId()) {
            throw new DomainException('The accounting session belongs to a different tenant.');
        }

        return CurrentSession::updateOrCreate(
            ['acct_session_id' => $sessionId],
            [
                'service_id' => $service->id,
                'username' => $service->username,
                'nasname' => $nasname,
                'framed_ip' => $framedIp,
                'acct_start_time' => $startedAt,
                'last_seen_at' => $lastSeen,
                'stopped_at' => null,
                'terminate_cause' => null,
                'input_octets' => max(0, $inputOctets),
                'output_octets' => max(0, $outputOctets),
            ],
        );
    }
}
