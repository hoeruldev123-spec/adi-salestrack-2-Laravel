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
            ->with(['createdBy', 'result'])
            ->when($filters['forecast_type'] ?? null, fn ($query, $type) => $query->where('forecast_type', $type))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->orderByDesc('id')
            ->paginate($filters['per_page'] ?? 15);
    }

    public function create(array $data, User $user): ForecastRun
    {
        $data['created_by'] = $user->id;
        $data['status'] = 'PENDING';

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
