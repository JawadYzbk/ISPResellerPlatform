<?php

namespace App\Domain\Communications;

use App\Models\Message;
use App\Models\WhatsAppAccount;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final readonly class WhatsAppDeliveryGuard
{
    public function claim(WhatsAppAccount $account): WhatsAppDeliveryDecision
    {
        if (! (bool) config('services.whatsapp.safety.enabled', true)) {
            return WhatsAppDeliveryDecision::allowed();
        }

        return DB::transaction(function () use ($account): WhatsAppDeliveryDecision {
            $locked = WhatsAppAccount::query()->lockForUpdate()->findOrFail($account->id);
            $now = now();

            $blockedUntil = $this->latestDate($locked->next_send_at, $locked->cooldown_until);
            if ($blockedUntil instanceof Carbon && $blockedUntil->isFuture()) {
                return WhatsAppDeliveryDecision::deferred($blockedUntil->getTimestamp() - $now->getTimestamp() + 1, 'account_pacing');
            }

            $hourlyLimit = (int) config('services.whatsapp.safety.hourly_limit', 60);
            $hourStart = $now->copy()->subHour();
            $hourlyCount = Message::query()
                ->where('whatsapp_account_id', $locked->id)
                ->where('channel', 'whatsapp')
                ->whereIn('status', ['sent', 'delivered'])
                ->where('sent_at', '>=', $hourStart)
                ->count();
            if ($hourlyCount >= $hourlyLimit) {
                return WhatsAppDeliveryDecision::deferred(60, 'hourly_account_limit');
            }

            $dailyLimit = (int) config('services.whatsapp.safety.daily_limit', 500);
            $dayStart = $now->copy()->startOfDay();
            $dailyCount = Message::query()
                ->where('whatsapp_account_id', $locked->id)
                ->where('channel', 'whatsapp')
                ->whereIn('status', ['sent', 'delivered'])
                ->where('sent_at', '>=', $dayStart)
                ->count();
            if ($dailyCount >= $dailyLimit) {
                return WhatsAppDeliveryDecision::deferred(900, 'daily_account_limit');
            }

            $interval = (int) config('services.whatsapp.safety.min_interval_seconds', 8);
            $jitter = (int) config('services.whatsapp.safety.jitter_seconds', 4);
            $reservedUntil = $now->copy()->addSeconds($interval + ($jitter > 0 ? random_int(0, $jitter) : 0));
            $locked->forceFill(['next_send_at' => $reservedUntil])->save();

            return WhatsAppDeliveryDecision::allowed();
        });
    }

    public function recordSuccess(WhatsAppAccount $account): void
    {
        WhatsAppAccount::query()->whereKey($account->id)->update([
            'failure_streak' => 0,
            'cooldown_until' => null,
        ]);
    }

    public function recordFailure(WhatsAppAccount $account): void
    {
        DB::transaction(function () use ($account): void {
            $locked = WhatsAppAccount::query()->lockForUpdate()->findOrFail($account->id);
            $streak = min(12, ((int) $locked->failure_streak) + 1);
            $base = (int) config('services.whatsapp.safety.failure_cooldown_seconds', 30);
            $maximum = (int) config('services.whatsapp.safety.max_failure_cooldown_seconds', 900);
            $cooldown = min($maximum, $base * (2 ** ($streak - 1)));
            $locked->forceFill([
                'failure_streak' => $streak,
                'cooldown_until' => now()->addSeconds($cooldown),
            ])->save();
        });
    }

    private function latestDate(?Carbon $first, ?Carbon $second): ?Carbon
    {
        if (! $first instanceof Carbon) {
            return $second;
        }
        if (! $second instanceof Carbon) {
            return $first;
        }

        return $first->greaterThan($second) ? $first : $second;
    }
}
