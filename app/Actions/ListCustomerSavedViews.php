<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\CustomerSavedView;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

final readonly class ListCustomerSavedViews implements Action
{
    /** @return Collection<int, CustomerSavedView> */
    public function handle(User $user): Collection
    {
        return CustomerSavedView::query()
            ->where('user_id', $user->id)
            ->orderBy('name')
            ->get();
    }
}
