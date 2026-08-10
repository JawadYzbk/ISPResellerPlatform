<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Domain\Network\SubscriberReader;
use App\Domain\Network\SubscriberWriter;
use App\Models\Router;
use App\Models\Service;
use DomainException;

final readonly class ReconcileRouterSubscribers implements Action
{
    public function __construct(private SubscriberReader $reader, private SubscriberWriter $writer) {}

    /** @return array{router_id: int, status: string, platform_only: list<string>, router_only: list<string>, disabled_drift: list<string>, healed: list<string>, heal_errors: array<string, string>} */
    public function handle(Router $router, bool $heal = false): array
    {
        $subscribers = collect($this->reader->read($router))->keyBy(fn (array $subscriber): string => trim((string) ($subscriber['name'] ?? '')));
        $services = Service::query()->where('router_id', $router->id)->get()->keyBy('username');
        $platformOnly = $services->keys()->diff($subscribers->keys())->values()->all();
        $routerOnly = $subscribers->keys()->filter(fn (string $username): bool => $username !== '' && ! $services->has($username))->values()->all();
        $disabledDrift = $services->filter(function (Service $service) use ($subscribers): bool {
            $subscriber = $subscribers->get($service->username);
            if (! is_array($subscriber)) {
                return false;
            }

            $disabled = in_array(strtolower(trim((string) ($subscriber['disabled'] ?? ''))), ['1', 'true', 'yes'], true);

            return ($service->status->value === 'active') === $disabled;
        })->keys()->values()->all();

        $healed = [];
        $healErrors = [];
        if ($heal) {
            foreach ($disabledDrift as $username) {
                $subscriber = $subscribers->get($username);
                $deviceId = is_array($subscriber) ? trim((string) ($subscriber['.id'] ?? $subscriber['id'] ?? '')) : '';
                if ($deviceId === '') {
                    $healErrors[$username] = 'missing_router_subscriber_id';

                    continue;
                }

                try {
                    $this->writer->enable($router, $deviceId);
                    $healed[] = $username;
                } catch (DomainException $exception) {
                    $healErrors[$username] = $exception->getMessage();
                }
            }
        }

        return [
            'router_id' => $router->id,
            'status' => $platformOnly === [] && $routerOnly === [] && $disabledDrift === [] ? 'in_sync' : 'drifted',
            'platform_only' => $platformOnly,
            'router_only' => $routerOnly,
            'disabled_drift' => $disabledDrift,
            'healed' => $healed,
            'heal_errors' => $healErrors,
        ];
    }
}
