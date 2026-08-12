<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\Branch;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class SaveBranchLocation implements Action
{
    /** @param array<string, mixed> $attributes */
    public function handle(Tenant $tenant, array $attributes, ?Branch $branch = null): Branch
    {
        return DB::transaction(function () use ($tenant, $attributes, $branch): Branch {
            $isDefault = $branch === null
                ? ! Branch::query()->exists() || (bool) ($attributes['is_default'] ?? false)
                : (bool) ($attributes['is_default'] ?? false);

            if ($branch instanceof Branch && ! $isDefault && $branch->is_default) {
                $replacement = Branch::query()->whereKeyNot($branch->id)->orderBy('name')->first();
                if (! $replacement instanceof Branch) {
                    throw ValidationException::withMessages(['is_default' => 'Keep at least one default branch configured.']);
                }

                $replacement->update(['is_default' => true]);
            }

            if ($isDefault) {
                $query = Branch::query();
                if ($branch instanceof Branch) {
                    $query->whereKeyNot($branch->id);
                }
                $query->update(['is_default' => false]);
            }

            $attributes['is_default'] = $isDefault;
            if (! $branch instanceof Branch) {
                $created = $tenant->branches()->create($attributes);

                return $created instanceof Branch ? $created : throw new \LogicException('Branch creation returned an unexpected model.');
            }

            $branch->update($attributes);

            return $branch->refresh();
        });
    }
}
