<?php

namespace App\Domain\Network;

use App\Domain\Radius\CoaClient;
use App\Domain\Radius\CoaResult;
use App\Domain\Radius\RadiusSyncService;
use App\Models\NetworkCommand;
use App\Models\Service;
use Throwable;

final class RadiusDriver implements NetworkDriver
{
    public function __construct(private RadiusSyncService $sync, private CoaClient $coa) {}

    public function execute(Service $service, NetworkCommand $command): DriverResult
    {
        $service->loadMissing(['plan', 'router']);
        $this->sync->sync($service);

        if (! in_array($command->action, ['suspend', 'disconnect', 'throttle'], true)) {
            return DriverResult::success('RADIUS state synchronized.', ['action' => $command->action, 'coa_status' => 'not_required']);
        }
        if ($service->router === null) {
            return DriverResult::success('RADIUS state synchronized without live-session enforcement.', ['action' => $command->action, 'coa_status' => 'router_not_configured']);
        }

        try {
            $result = $command->action === 'throttle'
                ? $this->coa->changeOfAuthorization($service->router, $service->username, $command->payload['session_id'] ?? null, [['type' => 11, 'value' => (string) ($command->payload['fup_profile'] ?? 'fup')]])
                : $this->coa->disconnect($service->router, $service->username, $command->payload['session_id'] ?? null);
        } catch (Throwable $exception) {
            return DriverResult::failure('RADIUS CoA failed: '.$exception->getMessage());
        }

        return $this->result($result, $command->action);
    }

    private function result(CoaResult $result, string $action): DriverResult
    {
        if ($result->status !== 'ack') {
            return DriverResult::failure('RADIUS CoA was rejected.', ['action' => $action, 'coa_status' => 'nak', 'response_code' => $result->responseCode]);
        }

        return DriverResult::success('RADIUS CoA accepted.', ['action' => $action, 'coa_status' => 'ack', 'response_code' => $result->responseCode]);
    }
}
