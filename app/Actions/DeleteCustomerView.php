<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\CustomerSavedView;
use App\Models\User;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final readonly class DeleteCustomerView implements Action
{
    public function handle(User $user, CustomerSavedView $view): void
    {
        if ($view->user_id !== $user->id) {
            throw new AccessDeniedHttpException;
        }

        $view->delete();
    }
}
