<?php

namespace App\Services\Forecast;

use App\Models\ForecastRun;
use App\Models\ForecastScenario;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

class ForecastRunService
{
    public function paginate(array $filters): LengthAwarePaginator
    {
        return ForecastRun::query()
            ->with(['user', 'createdBy', 'result', 'principal'])
            ->when($filters['forecast_type'] ?? null, fn ($query, $type) => $query->where('forecast_type', $type))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['principal_id'] ?? null, fn ($query, $principalId) => $query->where('principal_id', $principalId))
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('forecast_name', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('id')
            ->paginate($filters['per_page'] ?? 15);
    }

    public function create(array $data, User $user): ForecastRun
    {
        $data['created_by'] = $user->id;
        $data['user_id'] = $user->id;
        $data['forecast_name'] = $data['forecast_name'] ?? ($data['name'] ?? null);
        $data['name'] = $data['name'] ?? $data['forecast_name'];
        $data['forecast_type'] = $data['forecast_type'] ?? 'OPPORTUNITY';
        $data['forecast_scope'] = $data['forecast_scope'] ?? 'OPPORTUNITY';
        $data['revenue_category'] = strtoupper($data['revenue_category'] ?? 'ALL');
        $data['status'] = 'PENDING';

        $data['forecast_start_date'] = $data['forecast_start_date']
            ?? $data['period_start']
            ?? $data['start_period']
            ?? null;
        $data['forecast_end_date'] = $data['forecast_end_date']
            ?? $data['period_end']
            ?? $data['end_period']
            ?? null;

        $data['period_start'] = $data['period_start']
            ?? $data['start_period']
            ?? $data['forecast_start_date'];
        $data['period_end'] = $data['period_end']
            ?? $data['end_period']
            ?? $data['forecast_end_date'];
        $data['start_period'] = $data['start_period'] ?? $data['forecast_start_date'];
        $data['end_period'] = $data['end_period'] ?? $data['forecast_end_date'];

        $data['recommendation_thresholds'] = array_merge(
            config('salestrack.forecast.recommendations', []),
            $data['recommendation_thresholds'] ?? []
        );

        return ForecastRun::create($data);
    }

    public function destroy(ForecastRun $run): void
    {
        $run->delete();
    }

    public function scenarios(ForecastRun $run, array $filters): LengthAwarePaginator
    {
        return $run->scenarios()
            ->orderByDesc('id')
            ->paginate($filters['per_page'] ?? 15);
    }

    public function createScenario(ForecastRun $run, array $data): ForecastScenario
    {
        $data['forecast_run_id'] = $run->id;

        return ForecastScenario::create($data);
    }

    public function destroyScenario(ForecastRun $run, ForecastScenario $scenario): void
    {
        if ($scenario->forecast_run_id !== $run->id) {
            abort(404);
        }

        $scenario->delete();
    }
}
