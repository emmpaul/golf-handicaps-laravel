<?php

/*
 * No test reaches Anthropic (design D9): the global beforeEach of tests/Pest.php fakes the
 * reading agent with stray prompts refused and refuses stray HTTP requests, and phpunit.xml
 * empties the key of the development .env.
 */

use App\Integrations\Ai\ScorecardPhotoReader;
use App\Integrations\Ai\SgtScorecardReader;
use Illuminate\Http\Client\StrayRequestException;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Enums\Lab;

// Mutation that turns this test red: preventStrayPrompts() removed from the beforeEach (the
// prompt then goes to the real gateway and stops on the refused HTTP request instead).
test('a prompt of the reading agent without a faked answer throws instead of leaving', function () {
    expect(fn () => (new ScorecardPhotoReader)->prompt('Lis la carte', provider: Lab::Anthropic, model: 'claude-sonnet-5-5'))
        ->toThrow(RuntimeException::class, 'Attempted prompt [Lis la carte] without a fake agent response.');
});

// Course-rating-sgt, design D11. Mutation that turns this test red: the SgtScorecardReader line
// removed from the beforeEach.
test('a prompt of the SGT scorecard agent without a faked answer throws instead of leaving', function () {
    expect(fn () => (new SgtScorecardReader)->prompt('Lis la carte SGT', provider: Lab::Anthropic, model: 'claude-sonnet-5-5'))
        ->toThrow(RuntimeException::class, 'Attempted prompt [Lis la carte SGT] without a fake agent response.');
});

// Mutation that turns this test red: Http::preventStrayRequests() removed from the beforeEach.
test('an HTTP request to Anthropic outside a fake throws instead of leaving', function () {
    expect(fn () => Http::get('https://api.anthropic.com/v1/models'))
        ->toThrow(StrayRequestException::class);
});

// Mutation that turns this test red: the ANTHROPIC_API_KEY line removed from phpunit.xml (the
// key of the development .env is then read).
test('tests run without the key of the development environment', function () {
    // Compared through filled() so that a failure never prints the key.
    expect(filled(config('ai.providers.anthropic.key')))->toBeFalse();
});
