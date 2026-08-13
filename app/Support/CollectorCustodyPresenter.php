<?php

namespace App\Support;

use App\Models\CollectorCustodyEntry;

final class CollectorCustodyPresenter
{
    /** @return array<string, mixed> */
    public function entry(CollectorCustodyEntry $entry): array
    {
        $entry->loadMissing(['collector:id,name,email', 'requestedBy:id,name', 'reviewedBy:id,name', 'cashShift:id,public_id']);

        return [
            'id' => $entry->public_id,
            'type' => $entry->type,
            'direction' => $entry->direction,
            'status' => $entry->status,
            'amount' => $entry->amount,
            'currency' => $entry->currency,
            'description' => $entry->description,
            'reference' => $entry->reference,
            'occurred_at' => $entry->occurred_at->toIso8601String(),
            'reviewed_at' => $entry->reviewed_at?->toIso8601String(),
            'review_note' => $entry->review_note,
            'collector' => ['id' => $entry->collector->id, 'name' => $entry->collector->name, 'email' => $entry->collector->email],
            'requested_by' => ['name' => $entry->requestedBy->name],
            'reviewed_by' => $entry->reviewedBy === null ? null : ['name' => $entry->reviewedBy->name],
            'cash_shift_id' => $entry->cashShift?->public_id,
        ];
    }
}
