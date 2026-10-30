<?php

/*
 * Spec performance-measurement « Rapport comparable »; design D2: median and 90th percentile at
 * the nearest rank (ceil(p × n)) of the sorted trials, Server-Timing absent shown as « — ».
 *
 * Mutations that turn these tests red:
 * - the 90th percentile at floor(0.9 × n) instead of the nearest rank: 120 instead of 300;
 * - the median taken on the unsorted trials: 120 instead of 110 for the trials in arrival order
 *   300, 100, 120, 105, 110;
 * - the minimum taken as the first trial: 300 instead of 100 for the same order;
 * - db-count read from the db measure: 3 instead of 4;
 * - the dash replaced by an empty cell for a missing value.
 */

use App\Support\PerfProbeStatistics;

test('five trials give the median, the 90th percentile and the minimum of the spec', function () {
    expect(PerfProbeStatistics::summarize([100, 120, 110, 300, 105]))
        ->toBe(['median' => 110, 'p90' => 300, 'min' => 100]);
});

test('the trials are sorted before the ranks are read', function () {
    expect(PerfProbeStatistics::summarize([300, 100, 120, 105, 110]))
        ->toBe(['median' => 110, 'p90' => 300, 'min' => 100]);
});

test('a single trial is its own median, 90th percentile and minimum', function () {
    expect(PerfProbeStatistics::summarize([50]))
        ->toBe(['median' => 50, 'p90' => 50, 'min' => 50]);
});

test('no trial gives no summary', function () {
    expect(PerfProbeStatistics::summarize([]))->toBeNull();
});

test('the app and db-count measures are read from Server-Timing', function () {
    expect(PerfProbeStatistics::serverTiming('app;dur=12.5, db;dur=3, db-count;dur=4'))
        ->toBe(['app' => 12.5, 'db-count' => 4.0]);
});

test('a response without Server-Timing gives no measure', function () {
    expect(PerfProbeStatistics::serverTiming(null))->toBeNull();
});

test('a missing value is shown as a dash', function () {
    expect(PerfProbeStatistics::display(null))->toBe('—');
});

test('a value is shown with the given decimals', function () {
    expect([PerfProbeStatistics::display(110.4), PerfProbeStatistics::display(12.5, 1)])
        ->toBe(['110', '12.5']);
});
