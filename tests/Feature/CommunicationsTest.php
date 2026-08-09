<?php

use App\Actions\QueueMessage;
use App\Domain\Communications\TemplateRenderer;
use App\Models\Message;
use App\Models\MessageTemplate;
use App\Models\Tenant;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('fails loudly in preview and safely queues idempotent messages in production', function (): void {
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
});
