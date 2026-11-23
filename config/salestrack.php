<?php

return [

    'forecast' => [
        'historical_win_rate' => (float) env('FORECAST_HISTORICAL_WIN_RATE', 0.3),
        'months' => 6,
        'default_historical_years' => (int) env('FORECAST_DEFAULT_HISTORICAL_YEARS', 5),
        'min_stage_samples' => (int) env('FORECAST_MIN_STAGE_SAMPLES', 5),
        'store_detailed_results' => (bool) env('FORECAST_STORE_DETAILED_RESULTS', true),
        'detail_chunk_size' => (int) env('FORECAST_DETAIL_CHUNK_SIZE', 1000),
        'distribution_bins' => (int) env('FORECAST_DISTRIBUTION_BINS', 20),
        'recommendations' => [
            'high_value_threshold' => (float) env('FORECAST_REC_HIGH_VALUE_THRESHOLD', 600_000_000),
            'low_value_threshold' => (float) env('FORECAST_REC_LOW_VALUE_THRESHOLD', 200_000_000),
            'high_probability_threshold' => (float) env('FORECAST_REC_HIGH_PROBABILITY_THRESHOLD', 70),
            'low_probability_threshold' => (float) env('FORECAST_REC_LOW_PROBABILITY_THRESHOLD', 30),
            'closing_soon_days' => (int) env('FORECAST_REC_CLOSING_SOON_DAYS', 30),
            'inactive_days' => (int) env('FORECAST_REC_INACTIVE_DAYS', 30),
        ],
    ],

    'segmentation' => [
        'platinum' => (float) env('SEGMENTATION_PLATINUM', 5_000_000_000),
        'gold' => (float) env('SEGMENTATION_GOLD', 1_000_000_000),
        'silver' => (float) env('SEGMENTATION_SILVER', 500_000_000),
    ],

    'leaderboard_size' => 5,

    'top_principal_count' => 8,

    'top_sales_count' => 5,

];
