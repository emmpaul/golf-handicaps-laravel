<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Initial players
    |--------------------------------------------------------------------------
    |
    | Names separated by commas, created by golf:initial-players (run by the
    | migrate service of the stack) on a base without any player. Missing or
    | empty: no player is created.
    |
    */

    'initial_players' => env('INITIAL_PLAYERS'),

    /*
    |--------------------------------------------------------------------------
    | Reading of the scorecards by the AI
    |--------------------------------------------------------------------------
    |
    | The model reads each photo once; the monthly budget is counted per
    | calendar month of budget_time_zone. Prices in micro-dollars per token
    | (= dollars per million tokens), from the grid of Anthropic read on
    | 2026-10-28. AppServiceProvider refuses to boot when the model has no
    | price or the time zone is unknown: a new model comes with its price.
    | The token bounds give the maximal cost of a call, checked against the
    | budget before each call.
    |
    */

    'ai' => [
        'model' => env('AI_MODEL', 'claude-sonnet-5-5'),
        'monthly_budget_usd' => (float) env('AI_MONTHLY_BUDGET_USD', 5),
        'budget_time_zone' => env('AI_BUDGET_TIME_ZONE', 'America/Montreal'),
        'max_input_tokens' => 6000,
        'max_output_tokens' => 4000,
        'prices' => [
            'claude-sonnet-5-5' => ['input' => 2, 'output' => 10],
            'claude-opus-5-5' => ['input' => 4, 'output' => 20],
        ],
    ],

];
