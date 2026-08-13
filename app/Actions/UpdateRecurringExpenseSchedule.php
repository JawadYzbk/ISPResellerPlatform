<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\RecurringExpenseSchedule;

final readonly class UpdateRecurringExpenseSchedule implements Action
{
    public function handle(RecurringExpenseSchedule $schedule, bool $active): RecurringExpenseSchedule
    {
        $schedule->update(['is_active' => $active]);

        return $schedule->refresh();
    }
}
