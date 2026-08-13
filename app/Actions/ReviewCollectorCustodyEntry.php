<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\CollectorCustodyEntry;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

final readonly class ReviewCollectorCustodyEntry implements Action
{
    public function handle(User $manager, CollectorCustodyEntry $entry, string $decision, ?string $note = null): CollectorCustodyEntry
    {
        if (! $manager->can('reports.operations') || (int) $manager->tenant_id !== (int) $entry->tenant_id) {
            throw new DomainException('You are not allowed to review this custody entry.');
        }
        if (! in_array($decision, ['posted', 'rejected'], true)) {
            throw new DomainException('Choose approve or reject.');
        }

        return DB::transaction(function () use ($manager, $entry, $decision, $note): CollectorCustodyEntry {
            $locked = CollectorCustodyEntry::query()->lockForUpdate()->findOrFail($entry->id);
            if ($locked->status !== 'pending') {
                throw new DomainException('This custody entry has already been reviewed.');
            }
            $locked->forceFill([
                'status' => $decision,
                'reviewed_by_id' => $manager->id,
                'reviewed_at' => now(),
                'review_note' => filled($note) ? trim((string) $note) : null,
            ])->save();

            return $locked->refresh();
        });
    }
}
