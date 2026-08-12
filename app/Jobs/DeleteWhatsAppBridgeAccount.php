<?php

namespace App\Jobs;

use App\Domain\Communications\WhatsAppBridgeClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class DeleteWhatsAppBridgeAccount implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 8;

    /** @var list<int> */
    public array $backoff = [60, 300, 900, 1800];

    public function __construct(public readonly string $bridgeId) {}

    public function handle(WhatsAppBridgeClient $bridge): void
    {
        $bridge->removeByBridgeId($this->bridgeId);
    }
}
