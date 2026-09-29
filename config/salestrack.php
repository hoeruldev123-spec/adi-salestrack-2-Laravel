<?php

return [

    'forecast' => [
        'historical_win_rate' => (float) env('FORECAST_HISTORICAL_WIN_RATE', 0.3),
        'months' => 6,
    ],

    'segmentation' => [
        'platinum' => 5_000_000_000,
        'gold' => 1_000_000_000,
        'silver' => 500_000_000,
    ],

    'leaderboard_size' => 5,

    'top_principal_count' => 8,

    'top_sales_count' => 5,

];
