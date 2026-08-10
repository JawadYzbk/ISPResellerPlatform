<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Domain\Network\SubscriberReader;
use App\Models\ImportBatch;
use App\Models\Router;
use App\Models\Service;

final readonly class ImportRouterSubscribers implements Action
{
    public function __construct(private SubscriberReader $reader) {}

    public function handle(Router $router, bool $dryRun = false): ImportBatch
    {
        $rows = $this->reader->read($router);
        $services = Service::query()->where('router_id', $router->id)->get()->keyBy('username');
        $report = [];

        foreach ($rows as $index => $source) {
            $username = trim((string) ($source['name'] ?? ''));
            $comment = trim((string) ($source['comment'] ?? ''));
            $errors = $username === '' ? ['name is required'] : [];
            $service = $services->get($username);
            $data = [
                'router_id' => $router->public_id,
                'username' => $username,
                'comment' => $comment,
                'disabled' => $this->disabled($source['disabled'] ?? false),
                'profile' => trim((string) ($source['profile'] ?? '')),
                'remote_address' => trim((string) ($source['remote-address'] ?? '')),
                'service_id' => $service?->public_id,
                'match' => $service === null ? 'unmatched' : 'service_username',
            ];
            $report[] = [
                'row' => $index + 1,
                'status' => $errors === [] ? ($dryRun ? 'valid' : 'imported') : 'rejected',
                'errors' => $errors,
                'data' => $data,
            ];
        }

        $batch = ImportBatch::create([
            'type' => 'router_subscribers',
            'filename' => 'router-'.$router->public_id.'-ppp-secrets.json',
            'status' => $dryRun ? 'preview' : 'processing',
            'total_rows' => count($report),
        ]);
        $successful = count(array_filter($report, fn (array $row): bool => in_array($row['status'], ['valid', 'imported'], true)));
        $failed = count($report) - $successful;
        $batch->forceFill([
            'status' => $dryRun ? 'preview' : 'completed',
            'successful_rows' => $successful,
            'failed_rows' => $failed,
            'report' => $report,
            'completed_at' => $dryRun ? null : now(),
        ])->save();

        return $batch->refresh();
    }

    private function disabled(mixed $value): bool
    {
        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes'], true);
    }
}
