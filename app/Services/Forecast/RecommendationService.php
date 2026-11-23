<?php

namespace App\Services\Forecast;

use App\Models\ForecastRecommendation;
use App\Models\ForecastRun;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;

class RecommendationService
{
    public function __construct(
        private readonly OpportunityForecastService $opportunities,
    ) {}

    /**
     * Generate rule-based recommendations for a forecast run.
     */
    public function generateForRun(ForecastRun $run): int
    {
        $run->recommendations()->delete();

        $opportunities = $this->opportunities->opportunities($run);
        $thresholds = $this->thresholds($run);
        $now = now();
        $rows = [];

        foreach ($opportunities as $opportunity) {
            $value = (float) $opportunity['value'];
            $probability = (float) $opportunity['probability_percent'];
            $closingDate = $opportunity['closing_date']
                ? Carbon::parse($opportunity['closing_date'])->startOfDay()
                : null;
            $lastActivity = $opportunity['last_activity_at']
                ? Carbon::parse($opportunity['last_activity_at'])
                : null;

            $daysToClose = $closingDate ? $now->copy()->startOfDay()->diffInDays($closingDate, false) : null;
            $daysSinceActivity = $lastActivity ? $lastActivity->diffInDays($now) : null;

            $isHighValue = $value >= $thresholds['high_value_threshold'];
            $isLowValue = $value <= $thresholds['low_value_threshold'];
            $isHighProbability = $probability >= $thresholds['high_probability_threshold'];
            $isLowProbability = $probability <= $thresholds['low_probability_threshold'];
            $isClosingSoon = $daysToClose !== null
                && $daysToClose >= 0
                && $daysToClose <= $thresholds['closing_soon_days'];
            $isInactive = $daysSinceActivity === null
                || $daysSinceActivity > $thresholds['inactive_days'];

            if ($isHighValue && $isHighProbability) {
                $rows[] = $this->row($run, $opportunity, [
                    'recommendation_type' => 'HIGH_POTENTIAL',
                    'priority' => 'HIGH',
                    'title' => 'Opportunity Potensial Tinggi',
                    'description' => 'Opportunity memiliki nilai transaksi tinggi dengan probabilitas keberhasilan tinggi.',
                    'action' => 'Prioritaskan opportunity ini, percepat proses negosiasi, dan pastikan kebutuhan klien telah terpenuhi.',
                ]);
            }

            if ($isHighValue && $isLowProbability) {
                $rows[] = $this->row($run, $opportunity, [
                    'recommendation_type' => 'HIGH_VALUE_LOW_PROBABILITY',
                    'priority' => 'MEDIUM',
                    'title' => 'Nilai Tinggi dengan Probabilitas Rendah',
                    'description' => 'Opportunity bernilai tinggi namun memiliki peluang keberhasilan yang rendah.',
                    'action' => 'Evaluasi kebutuhan klien, identifikasi hambatan penjualan, dan libatkan Solution Architect jika diperlukan.',
                ]);
            }

            if ($isClosingSoon) {
                $rows[] = $this->row($run, $opportunity, [
                    'recommendation_type' => 'CLOSING_SOON',
                    'priority' => 'HIGH',
                    'title' => 'Closing Date Mendekat',
                    'description' => "Closing date opportunity tinggal {$daysToClose} hari lagi.",
                    'action' => 'Tingkatkan frekuensi follow-up, konfirmasi kesiapan pembelian klien, serta percepat proses administrasi dan negosiasi.',
                ]);
            }

            if ($isInactive) {
                $rows[] = $this->row($run, $opportunity, [
                    'recommendation_type' => 'INACTIVE_OPPORTUNITY',
                    'priority' => 'MEDIUM',
                    'title' => 'Opportunity Tidak Aktif',
                    'description' => 'Tidak terdapat aktivitas sales dalam periode pemantauan.',
                    'action' => 'Lakukan follow-up kepada klien, perbarui status opportunity, dan evaluasi kembali kemungkinan keberhasilan.',
                ]);
            }

            if ($isLowValue && $isLowProbability) {
                $rows[] = $this->row($run, $opportunity, [
                    'recommendation_type' => 'LOW_POTENTIAL',
                    'priority' => 'LOW',
                    'title' => 'Opportunity Potensial Rendah',
                    'description' => 'Nilai transaksi dan probabilitas keberhasilan opportunity relatif rendah.',
                    'action' => 'Evaluasi biaya dan waktu yang diperlukan, pertimbangkan alokasi sumber daya sales, dan tentukan strategi tindak lanjut yang sesuai.',
                ]);
            }
        }

        if (empty($rows)) {
            return 0;
        }

        $run->recommendations()->createMany($rows);

        return count($rows);
    }

    public function paginate(array $filters): LengthAwarePaginator
    {
        return ForecastRecommendation::query()
            ->with([
                'forecastRun:id,forecast_name,name,forecast_start_date,forecast_end_date',
                'opportunity' => fn ($query) => $query
                    ->select([
                        'id', 'title', 'project_name', 'client_name', 'stage', 'status',
                        'closing_date', 'expected_close_date', 'estimated_value', 'value',
                        'principal_id', 'user_id',
                    ])
                    ->withMax('activities', 'activity_date'),
                'opportunity.principal:id,name',
            ])
            ->when($filters['forecast_run_id'] ?? null, fn ($query, $runId) => $query->where('forecast_run_id', $runId))
            ->when($filters['priority'] ?? null, fn ($query, $priority) => $query->where('priority', strtoupper($priority)))
            ->when($filters['recommendation_type'] ?? null, fn ($query, $type) => $query->where('recommendation_type', strtoupper($type)))
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('recommendation_title', 'like', "%{$search}%")
                        ->orWhere('recommendation_description', 'like', "%{$search}%")
                        ->orWhereHas('opportunity', function ($query) use ($search) {
                            $query->where('title', 'like', "%{$search}%")
                                ->orWhere('project_name', 'like', "%{$search}%")
                                ->orWhere('client_name', 'like', "%{$search}%");
                        });
                });
            })
            ->orderByRaw("CASE priority WHEN 'HIGH' THEN 1 WHEN 'MEDIUM' THEN 2 ELSE 3 END")
            ->orderByDesc('estimated_value')
            ->orderByDesc('id')
            ->paginate($filters['per_page'] ?? 15);
    }

    /**
     * Priority summary for the recommendation page.
     *
     * @return array<string, int>
     */
    public function summary(array $filters = []): array
    {
        $query = ForecastRecommendation::query()
            ->when($filters['forecast_run_id'] ?? null, fn ($query, $runId) => $query->where('forecast_run_id', $runId))
            ->when($filters['recommendation_type'] ?? null, fn ($query, $type) => $query->where('recommendation_type', strtoupper($type)))
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('recommendation_title', 'like', "%{$search}%")
                        ->orWhereHas('opportunity', function ($query) use ($search) {
                            $query->where('title', 'like', "%{$search}%")
                                ->orWhere('project_name', 'like', "%{$search}%");
                        });
                });
            });

        $counts = $query->selectRaw('priority, COUNT(*) as total')
            ->groupBy('priority')
            ->pluck('total', 'priority')
            ->all();

        return [
            'HIGH' => (int) ($counts['HIGH'] ?? 0),
            'MEDIUM' => (int) ($counts['MEDIUM'] ?? 0),
            'LOW' => (int) ($counts['LOW'] ?? 0),
            'TOTAL' => array_sum(array_map('intval', $counts)),
        ];
    }

    public function thresholds(ForecastRun $run): array
    {
        $defaults = config('salestrack.forecast.recommendations', []);

        return array_merge($defaults, $run->recommendation_thresholds ?? []);
    }

    /**
     * @param  array<string, mixed>  $opportunity
     * @param  array{recommendation_type: string, priority: string, title: string, description: string, action: string}  $definition
     * @return array<string, mixed>
     */
    private function row(ForecastRun $run, array $opportunity, array $definition): array
    {
        return [
            'forecast_run_id' => $run->id,
            'opportunity_id' => $opportunity['id'],
            'recommendation_type' => $definition['recommendation_type'],
            'priority' => $definition['priority'],
            'recommendation_title' => $definition['title'] . ': ' . ($opportunity['title'] ?: 'Opportunity #' . $opportunity['id']),
            'recommendation_description' => $definition['description'],
            'estimated_value' => $opportunity['value'],
            'probability' => $opportunity['probability_percent'],
            'action_suggestion' => $definition['action'],
        ];
    }
}
