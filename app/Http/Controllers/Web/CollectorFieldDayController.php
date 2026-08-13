<?php

namespace App\Http\Controllers\Web;

use App\Actions\EndCollectorFieldDay;
use App\Actions\StartCollectorFieldDay;
use App\Http\Controllers\Controller;
use App\Http\Requests\CollectorLocationRequest;
use App\Http\Requests\EndCollectorFieldDayRequest;
use App\Models\CollectorFieldDay;
use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class CollectorFieldDayController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->can('reports.operations'), 403);
        $tenant = Tenant::query()->findOrFail($user->tenant_id);
        $timezone = $tenant->settingsData()->timezone;
        $date = $request->validate(['date' => ['nullable', 'date_format:Y-m-d']])['date']
            ?? CarbonImmutable::now($timezone)->toDateString();
        $day = CarbonImmutable::createFromFormat('!Y-m-d', $date, $timezone);
        abort_unless($day instanceof CarbonImmutable, 422);
        $fieldDays = CollectorFieldDay::query()
            ->with('collector:id,name,email')
            ->whereBetween('checked_in_at', [$day->startOfDay()->utc(), $day->endOfDay()->utc()])
            ->latest('checked_in_at')
            ->get()
            ->map(fn (CollectorFieldDay $fieldDay): array => $this->resource($fieldDay, true))
            ->values();

        return Inertia::render('Operations/CollectorCheckIns', [
            'date' => $date,
            'fieldDays' => $fieldDays,
        ]);
    }

    public function checkIn(CollectorLocationRequest $request, StartCollectorFieldDay $start): JsonResponse
    {
        try {
            $fieldDay = $start->handle(
                $request->user(),
                (float) $request->validated('latitude'),
                (float) $request->validated('longitude'),
                $request->validated('accuracy_meters'),
            );
        } catch (DomainException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['message' => 'Field day started.', 'data' => $this->resource($fieldDay)], 201);
    }

    public function checkOut(EndCollectorFieldDayRequest $request, EndCollectorFieldDay $end): JsonResponse
    {
        try {
            $fieldDay = $end->handle(
                $request->user(),
                (float) $request->validated('latitude'),
                (float) $request->validated('longitude'),
                $request->validated('accuracy_meters'),
                $request->validated('summary_note'),
            );
        } catch (DomainException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['message' => 'Field day ended.', 'data' => $this->resource($fieldDay)]);
    }

    /** @return array<string, mixed> */
    public function resource(CollectorFieldDay $fieldDay, bool $includeCollector = false): array
    {
        return [
            'id' => $fieldDay->public_id,
            'status' => $fieldDay->checked_out_at === null ? 'active' : 'completed',
            'checked_in_at' => $fieldDay->checked_in_at?->toIso8601String(),
            'checked_out_at' => $fieldDay->checked_out_at?->toIso8601String(),
            'check_in' => $this->location($fieldDay->check_in_latitude, $fieldDay->check_in_longitude, $fieldDay->check_in_accuracy_meters),
            'check_out' => $fieldDay->check_out_latitude === null ? null : $this->location($fieldDay->check_out_latitude, $fieldDay->check_out_longitude, $fieldDay->check_out_accuracy_meters),
            'collector' => $includeCollector ? ['name' => $fieldDay->collector->name, 'email' => $fieldDay->collector->email] : null,
            'summary' => $fieldDay->summary,
            'summary_note' => $fieldDay->summary_note,
        ];
    }

    /** @return array{latitude: float, longitude: float, accuracy_meters: int|null, map_url: string} */
    private function location(mixed $latitude, mixed $longitude, ?int $accuracy): array
    {
        $lat = (float) $latitude;
        $lng = (float) $longitude;

        return [
            'latitude' => $lat,
            'longitude' => $lng,
            'accuracy_meters' => $accuracy,
            'map_url' => 'https://www.google.com/maps/search/?api=1&query='.rawurlencode($lat.','.$lng),
        ];
    }
}
