<?php

use App\Actions\QueueMessage;
use App\Domain\Communications\FakeMessageProvider;
use App\Domain\Communications\MessageDeliveryResult;
use App\Domain\Communications\MessageProviderManager;
use App\Domain\Communications\TemplateRenderer;
use App\Jobs\DeliverMessage;
use App\Models\Message;
use App\Models\MessageTemplate;
use App\Models\Tenant;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

it('fails loudly in preview and safely queues idempotent messages in production', function (): void {
    Queue::fake();
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $template = MessageTemplate::create(['key' => 'payment.receipt', 'channel' => 'sms', 'locale' => 'en', 'body' => 'Hello {{ customer_name }}, receipt {{ receipt_number }}.']);

    expect(fn (): string => app(TemplateRenderer::class)->render($template, ['customer_name' => 'Rami'], preview: true))->toThrow(RuntimeException::class)
        ->and(app(TemplateRenderer::class)->render($template, ['customer_name' => 'Rami']))->toBe('Hello Rami, receipt .');

    $first = app(QueueMessage::class)->handle($template, '96170123456', 'sms', 'en', 'receipt-001', ['customer_name' => 'Rami', 'receipt_number' => 'RCT-00001']);
    $second = app(QueueMessage::class)->handle($template, '96170123456', 'sms', 'en', 'receipt-001', ['customer_name' => 'Rami', 'receipt_number' => 'RCT-00001']);

    expect($second->id)->toBe($first->id)
        ->and(Message::count())->toBe(1)
        ->and($first->body)->toContain('RCT-00001');

    Queue::assertPushed(DeliverMessage::class);
});

it('records provider delivery and retries failures without duplicating a message', function (): void {
    Queue::fake();
    $tenant = Tenant::create(['name' => 'Southline', 'slug' => 'southline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $template = MessageTemplate::create(['key' => 'outage.notice', 'channel' => 'sms', 'locale' => 'en', 'body' => 'Outage in {{ zone }}.']);
    $provider = new FakeMessageProvider;
    app()->instance(FakeMessageProvider::class, $provider);
    $message = app(QueueMessage::class)->handle($template, '96170123456', 'sms', 'en', 'outage-001', ['zone' => 'North']);
    $job = new DeliverMessage($message->id, $tenant->id);
    $job->handle(app(MessageProviderManager::class));

    expect($message->refresh()->status->value)->toBe('sent')
        ->and($message->delivery_attempts)->toBe(1);

    $provider->respondWith(MessageDeliveryResult::failed('fake', 'provider offline'));
    $failed = app(QueueMessage::class)->handle($template, '96170123456', 'sms', 'en', 'outage-002', ['zone' => 'North']);
    $failedJob = new DeliverMessage($failed->id, $tenant->id);

    expect(function () use ($failedJob): void {
        $failedJob->handle(app(MessageProviderManager::class));
    })->toThrow(RuntimeException::class);
    expect($failed->refresh()->delivery_attempts)->toBe(1)
        ->and($failed->status->value)->toBe('failed');
});
