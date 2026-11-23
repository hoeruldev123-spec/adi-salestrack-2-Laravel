<?php

namespace App\Services\Forecast;

use App\Enums\OpportunityStage;
use App\Enums\OpportunityStatus;
use App\Models\ForecastRun;
use App\Models\Opportunity;
use Illuminate\Support\Collection;

class StageProbabilityService
{
    /**
     * Calculate empirical win probability per sales stage from WON and LOST
     * opportunities in the configured historical period.
     *
     * @return array{
     *     period: array{start: string, end: string},
     *     stages: array<string, array{
     *         stage: string, won: int, lost: int, total: int,
     *         probability: float, probability_percent: float,
     *         source: string, warning: string|null
     *     }>,
     *     overall_win_rate: float|null,
     *     warnings: array<int, string>
     * }
     */
    public function forRun(ForecastRun $run): array
    {
        $start = $run->historicalStart();
        $end = $run->historicalEnd();
        $forecastStart = $run->forecastStart();

        if ($end->gte($forecastStart)) {
            $end = $forecastStart->copy()->subDay()->endOfDay();
        }

        if ($end->lt($start)) {
            $start = $end->copy()->subYears((int) config('salestrack.forecast.default_historical_years', 5));
        }

        $opportunities = Opportunity::query()
            ->whereIn('status', [OpportunityStatus::WON->value, OpportunityStatus::LOST->value])
            ->whereRaw(
                'COALESCE(actual_closing_date, closing_date, expected_close_date, DATE(created_at)) BETWEEN ? AND ?',
                [$start->toDateString(), $end->toDateString()]
            )
            ->with(['stageHistories' => fn ($query) => $query->orderByDesc('changed_at')->orderByDesc('id')])
            ->get(['id', 'stage', 'progress', 'probability', 'status', 'actual_closing_date', 'closing_date', 'expected_close_date', 'created_at']);

        $buckets = $this->emptyBuckets();
        $won = 0;
        $lost = 0;

        foreach ($opportunities as $opportunity) {
            $stage = $this->resolveStage($opportunity);

            if (! isset($buckets[$stage])) {
                $buckets[$stage] = $this->emptyBucket($stage);
            }

            if ($opportunity->status === OpportunityStatus::WON->value) {
                $buckets[$stage]['won']++;
                $won++;
            } else {
                $buckets[$stage]['lost']++;
                $lost++;
            }

            $buckets[$stage]['total']++;
        }

        $minimumSamples = max(1, (int) config('salestrack.forecast.min_stage_samples', 5));
        $stages = [];
        $warnings = [];

        foreach ($buckets as $stage => $bucket) {
            $total = $bucket['won'] + $bucket['lost'];
            $warning = null;

            if ($total >= $minimumSamples) {
                $probability = $total > 0 ? $bucket['won'] / $total : 0.0;
                $source = 'HISTORICAL';
            } else {
                $probability = $this->fallbackProbability($stage);
                $source = 'FALLBACK';
                $warning = $total === 0
                    ? "Belum ada data historis WON/LOST pada stage {$stage}; digunakan probabilitas fallback."
                    : "Data historis stage {$stage} hanya {$total} opportunity (minimum {$minimumSamples}); digunakan probabilitas fallback.";
                $warnings[] = $warning;
            }

            $stages[$stage] = [
                'stage' => $stage,
                'won' => $bucket['won'],
                'lost' => $bucket['lost'],
                'total' => $total,
                'probability' => round($probability, 6),
                'probability_percent' => round($probability * 100, 2),
                'source' => $source,
                'warning' => $warning,
            ];
        }

        $closedTotal = $won + $lost;

        return [
            'period' => [
                'start' => $start->toDateString(),
                'end' => $end->toDateString(),
            ],
            'stages' => $stages,
            'overall_win_rate' => $closedTotal > 0 ? round($won / $closedTotal, 6) : null,
            'warnings' => $warnings,
        ];
    }

    /**
     * @return array<string, array{won: int, lost: int, total: int}>
     */
    private function emptyBuckets(): array
    {
        $buckets = [];

        foreach (OpportunityStage::cases() as $stage) {
            $buckets[$stage->value] = ['won' => 0, 'lost' => 0, 'total' => 0];
        }

        return $buckets;
    }

    /**
     * @return array{won: int, lost: int, total: int}
     */
    private function emptyBucket(string $stage): array
    {
        return ['won' => 0, 'lost' => 0, 'total' => 0];
    }

    private function resolveStage(Opportunity $opportunity): string
    {
        /** @var Collection<int, \App\Models\OpportunityStageHistory> $histories */
        $histories = $opportunity->stageHistories;
        $latestHistory = $histories->first();

        $stage = $latestHistory?->current_stage
            ?: $opportunity->stage
            ?: $opportunity->progress
            ?: 'LEAD';

        return strtoupper(trim((string) $stage));
    }

    private function fallbackProbability(string $stage): float
    {
        $enum = OpportunityStage::tryFrom($stage);

        return ($enum?->defaultProbability() ?? 10) / 100;
    }
}
