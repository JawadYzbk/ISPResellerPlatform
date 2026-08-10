<?php

namespace App\Support\Api;

use App\Models\WorkOrder;
use App\Models\WorkOrderMaterial;
use Carbon\CarbonImmutable;

final readonly class WorkOrderApiResource
{
    /** @return array<string, mixed> */
    public function make(WorkOrder $order, bool $includeDetails = true): array
    {
        $order->loadMissing(['customer', 'service', 'assignee']);
        if ($includeDetails) {
            $order->loadMissing(['events.actor', 'mediaUploads', 'signature.media', 'materials.item', 'materials.warehouse']);
        }

        return [
            'id' => $order->public_id,
            'number' => $order->number,
            'type' => $order->type,
            'status' => $order->status->value,
            'scheduled_at' => $this->isoDate($order->scheduled_at),
            'started_at' => $this->isoDate($order->started_at),
            'completed_at' => $this->isoDate($order->completed_at),
            'failure_reason' => $order->failure_reason,
            'checklist' => $order->checklist ?? [],
            'readings' => $order->readings ?? [],
            'completion_notes' => $order->completion_notes,
            'customer' => $order->customer === null ? null : [
                'id' => $order->customer->public_id,
                'code' => $order->customer->code,
                'name' => $order->customer->full_name,
                'phone' => $order->customer->phone,
            ],
            'service' => $order->service === null ? null : [
                'id' => $order->service->public_id,
                'username' => $order->service->username,
            ],
            'assignee' => $order->assignee === null ? null : [
                'id' => $order->assignee->id,
                'name' => $order->assignee->name,
            ],
            'events' => $order->relationLoaded('events')
                ? $order->events->map(fn ($event): array => [
                    'type' => $event->event_type,
                    'from_status' => $event->from_status,
                    'to_status' => $event->to_status,
                    'actor' => $event->actor?->name,
                    'created_at' => $this->isoDate($event->created_at),
                ])->values()->all()
                : [],
            'media' => $order->relationLoaded('mediaUploads')
                ? $order->mediaUploads->map(fn ($media): array => [
                    'id' => $media->public_id,
                    'filename' => $media->original_name,
                    'mime_type' => $media->mime_type,
                    'size_bytes' => $media->size_bytes,
                    'purpose' => $media->purpose,
                    'created_at' => $this->isoDate($media->created_at),
                ])->values()->all()
                : [],
            'signature' => $order->signature === null ? null : [
                'id' => $order->signature->id,
                'signer_name' => $order->signature->signer_name,
                'signed_at' => $this->isoDate($order->signature->signed_at),
                'media_id' => $order->signature->media?->public_id,
            ],
            'materials' => $order->relationLoaded('materials')
                ? $order->materials->map(fn (WorkOrderMaterial $material): array => [
                    'id' => $material->id,
                    'sku' => $material->item?->sku,
                    'name' => $material->item?->name,
                    'warehouse' => $material->warehouse?->code,
                    'quantity' => (string) $material->quantity,
                    'note' => $material->note,
                    'consumed_at' => $this->isoDate($material->consumed_at),
                ])->values()->all()
                : [],
        ];
    }

    private function isoDate(mixed $value): ?string
    {
        return $value === null ? null : CarbonImmutable::parse((string) $value)->toIso8601String();
    }
}
