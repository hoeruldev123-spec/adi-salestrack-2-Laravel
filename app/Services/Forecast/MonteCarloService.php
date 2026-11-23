<?php

namespace App\Services\Forecast;

class MonteCarloService
{
    public const DEFAULT_ITERATIONS = 10000;

    /**
     * Simulate win/lose of each opportunity based on its probability.
     * Returns array of total revenues per iteration (floats).
     *
     * @param  array<int, array{value: float, probability: float|int}>  $opportunities
     * @return array<int, float>
     */
    public function simulateOpportunities(array $opportunities, int $iterations = self::DEFAULT_ITERATIONS, ?int $seed = null): array
    {
        return $this->simulateDetailed($opportunities, $iterations, $seed)['totals'];
    }

    /**
     * Simulate opportunities and expose every opportunity result per iteration.
     *
     * The optional callback receives ($iterationNumber, $totalRevenue, $details)
     * where $details contains opportunity_id, random_number, probability,
     * simulated_value, and is_success for each opportunity in that iteration.
     *
     * @param  array<int, array{id?: int|null, value: float, probability: float|int}>  $opportunities
     * @return array{totals: array<int, float>}
     */
    public function simulateDetailed(
        array $opportunities,
        int $iterations = self::DEFAULT_ITERATIONS,
        ?int $seed = null,
        ?callable $onIteration = null
    ): array {
        $this->seed($seed);

        $normalized = $this->normalizeOpportunities($opportunities);
        $results = [];

        for ($i = 0; $i < $iterations; $i++) {
            $total = 0.0;
            $details = [];

            foreach ($normalized as $opportunity) {
                $randomNumber = $this->randomUnit();
                $isSuccess = $randomNumber <= $opportunity['probability'];
                $simulatedValue = $isSuccess ? $opportunity['value'] : 0.0;
                $total += $simulatedValue;

                if ($onIteration !== null) {
                    $details[] = [
                        'opportunity_id' => $opportunity['id'] ?? null,
                        'random_number' => $randomNumber,
                        'probability' => $opportunity['probability'] * 100,
                        'simulated_value' => $simulatedValue,
                        'is_success' => $isSuccess,
                    ];
                }
            }

            $results[] = $total;

            if ($onIteration !== null) {
                $onIteration($i + 1, $total, $details);
            }
        }

        return ['totals' => $results];
    }

    /**
     * Simulate using historical monthly revenue grouped into an
     * empirical frequency distribution (research flow: frequency ->
     * probability -> cumulative probability -> random number).
     *
     * @param  array<int, float>  $monthlyRevenue
     * @return array<int, float>
     */
    public function simulateHistorical(array $monthlyRevenue, int $iterations = self::DEFAULT_ITERATIONS, int $bins = 10, ?int $seed = null, int $months = 1): array
    {
        $this->seed($seed);

        if (empty($monthlyRevenue)) {
            return array_fill(0, $iterations, 0.0);
        }

        $distribution = $this->buildDistribution($monthlyRevenue, $bins);
        $results = [];

        for ($i = 0; $i < $iterations; $i++) {
            $total = 0.0;
            for ($m = 0; $m < $months; $m++) {
                $total += $this->sampleFromDistribution($distribution);
            }
            $results[] = $total;
        }

        return $results;
    }

    /**
     * Combined forecast: sample historical base plus simulated opportunities.
     *
     * @param  array<int, array{value: float, probability: float|int}>  $opportunities
     * @param  array<int, float>  $monthlyRevenue
     * @return array<int, float>
     */
    public function simulateCombined(array $opportunities, array $monthlyRevenue, int $iterations = self::DEFAULT_ITERATIONS, int $bins = 10, ?int $seed = null, int $months = 1): array
    {
        $this->seed($seed);

        $historical = $this->simulateHistorical($monthlyRevenue, $iterations, $bins, $seed, $months);
        $opportunityResults = $this->simulateOpportunities($opportunities, $iterations, $seed);

        $results = [];
        for ($i = 0; $i < $iterations; $i++) {
            $results[] = $historical[$i] + $opportunityResults[$i];
        }

        return $results;
    }

    /**
     * Full statistical summary of a set of simulated totals.
     *
     * @param  array<int, float>  $results
     */
    public function calculateStatistics(array $results, ?float $targetRevenue = null): array
    {
        $count = count($results);

        if ($count === 0) {
            throw new \InvalidArgumentException('Cannot calculate statistics from an empty result set');
        }

        sort($results, SORT_NUMERIC);

        $sum = array_sum($results);
        $minimum = $results[0];
        $maximum = $results[$count - 1];
        $mean = $sum / $count;
        $median = $this->percentile($results, 50);

        $achieved = 0;
        if ($targetRevenue !== null) {
            foreach ($results as $value) {
                if ($value >= $targetRevenue) {
                    $achieved++;
                }
            }
        }

        return [
            'minimum' => round($minimum, 2),
            'maximum' => round($maximum, 2),
            'mean' => round($mean, 2),
            'median' => round($median, 2),
            'p10' => round($this->percentile($results, 10), 2),
            'p25' => round($this->percentile($results, 25), 2),
            'p50' => round($this->percentile($results, 50), 2),
            'p75' => round($this->percentile($results, 75), 2),
            'p90' => round($this->percentile($results, 90), 2),
            'p95' => round($this->percentile($results, 95), 2),
            'target_probability' => $targetRevenue !== null
                ? round(($achieved / $count) * 100, 2)
                : null,
        ];
    }

    /**
     * Build a histogram for a set of simulated totals.
     *
     * @param  array<int, float>  $results
     * @return array<int, array{bin_start: float, bin_end: float, start: float, end: float, count: int}>
     */
    public function histogram(array $results, int $bins = 20): array
    {
        $count = count($results);

        if ($count === 0) {
            return [];
        }

        $minimum = min($results);
        $maximum = max($results);
        $bins = max(1, $bins);

        if ((float) $minimum === (float) $maximum) {
            return [[
                'bin_start' => round((float) $minimum, 2),
                'bin_end' => round((float) $maximum, 2),
                'start' => round((float) $minimum, 2),
                'end' => round((float) $maximum, 2),
                'count' => $count,
            ]];
        }

        $width = ((float) $maximum - (float) $minimum) / $bins;
        $counts = array_fill(0, $bins, 0);

        foreach ($results as $value) {
            $index = min((int) floor(((float) $value - (float) $minimum) / $width), $bins - 1);
            $counts[$index]++;
        }

        $histogram = [];

        for ($i = 0; $i < $bins; $i++) {
            $start = (float) $minimum + $i * $width;
            $end = $start + $width;

            $histogram[] = [
                'bin_start' => round($start, 2),
                'bin_end' => round($end, 2),
                'start' => round($start, 2),
                'end' => round($end, 2),
                'count' => $counts[$i],
            ];
        }

        return $histogram;
    }

    /**
     * Build empirical frequency distribution from monthly revenue data.
     *
     * @param  array<int, float>  $data
     */
    private function buildDistribution(array $data, int $bins): array
    {
        $min = min($data);
        $max = max($data);
        $count = count($data);

        if ($max == $min) {
            return [
                'midpoints' => [$max],
                'cumulative' => [1.0],
            ];
        }

        $width = ($max - $min) / $bins;
        $frequencies = array_fill(0, $bins, 0);
        $midpoints = [];

        foreach ($data as $value) {
            $index = min((int) floor(($value - $min) / $width), $bins - 1);
            $frequencies[$index]++;
        }

        for ($i = 0; $i < $bins; $i++) {
            $midpoints[] = $min + ($i + 0.5) * $width;
        }

        $cumulative = [];
        $running = 0.0;
        foreach ($frequencies as $frequency) {
            $running += $frequency / $count;
            $cumulative[] = $running;
        }

        return [
            'midpoints' => $midpoints,
            'cumulative' => $cumulative,
        ];
    }

    /**
     * @return float
     */
    private function sampleFromDistribution(array $distribution)
    {
        $random = $this->randomUnit();
        $midpoints = $distribution['midpoints'];
        $cumulative = $distribution['cumulative'];

        foreach ($cumulative as $index => $probability) {
            if ($random <= $probability) {
                return $midpoints[$index];
            }
        }

        return $midpoints[count($midpoints) - 1];
    }

    /**
     * @param  array<int, array{id?: int|null, value: float, probability: float|int}>  $opportunities
     * @return array<int, array{id: int|null, value: float, probability: float}>
     */
    private function normalizeOpportunities(array $opportunities): array
    {
        $normalized = [];

        foreach ($opportunities as $opportunity) {
            $probability = max(0.0, min(1.0, (float) $opportunity['probability']));
            $value = max(0.0, (float) $opportunity['value']);

            if ($probability === 0.0 || $value === 0.0) {
                continue;
            }

            $normalized[] = [
                'id' => isset($opportunity['id']) ? (int) $opportunity['id'] : null,
                'value' => $value,
                'probability' => $probability,
            ];
        }

        return $normalized;
    }

    private function randomUnit(): float
    {
        return mt_rand(0, 1000000000) / 1000000000;
    }

    private function seed(?int $seed): void
    {
        if ($seed !== null) {
            mt_srand($seed);
        }
    }

    private function percentile(array $sorted, int $p): float
    {
        $count = count($sorted);

        if ($count === 1) {
            return $sorted[0];
        }

        $rank = max(1, (int) ceil(($p / 100) * $count)) - 1;

        return $sorted[$rank];
    }
}
