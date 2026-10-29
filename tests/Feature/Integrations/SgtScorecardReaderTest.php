<?php

/*
 * Spec course-ratings « Proposition lue sur la carte SGT », « Lecture de la carte sous le budget
 * IA » and « Données envoyées pour une recherche »; design D4, D11. The agent is faked: no test
 * reaches Anthropic.
 *
 * Mutations that turn these tests red:
 * - the bounds not checked: the slope of 160, the slope of 144.5 and the rating of 39.9 give a
 *   card;
 * - the image re-encoded (decoded and encoded again as a JPEG): the bytes sent are not those of
 *   the fixture;
 * - the rating kept in units instead of tenths: 71 instead of 714;
 * - the usage dropped on an unusable reading: 0 / 0 instead of 1 500 / 300;
 * - an empty list of tees accepted: found true without tees gives a card without tee
 *   (decision Q17).
 */

use App\Domain\Courses\Sgt\SgtTee;
use App\Integrations\Ai\SgtScorecardReader;
use Laravel\Ai\Files\Base64Image;
use Laravel\Ai\Prompts\AgentPrompt;

function sgtScorecard30(): string
{
    return (string) file_get_contents(base_path('tests/fixtures/sgt/scorecard_30.jpg'));
}

beforeEach(function () {
    config(['ai.providers.anthropic.key' => 'test-key']);
});

test('the reading of the Bethpage Black scorecard gives its par and its tees in tenths', function () {
    SgtScorecardReader::fake([billedSgtReading(bethpageSgtReading())]);

    $reading = app(SgtScorecardReader::class)->read(sgtScorecard30());

    expect($reading->card?->par)->toBe(71)
        ->and(array_map(fn (SgtTee $tee): array => [$tee->name, $tee->ratingT, $tee->slope], $reading->card->tees ?? []))->toBe([
            ['Blue', 775, 155],
            ['White', 742, 148],
            ['Red', 714, 144],
            ['Junior', 710, 120],
            ['PAR3', 540, 120],
        ])
        ->and([$reading->usage->inputTokens, $reading->usage->outputTokens])->toBe([1500, 300]);
});

test('an unusable reading gives no card and keeps its billed usage', function (array $answer) {
    SgtScorecardReader::fake([billedSgtReading($answer)]);

    $reading = app(SgtScorecardReader::class)->read(sgtScorecard30());

    expect($reading->card)->toBeNull()
        ->and([$reading->usage->inputTokens, $reading->usage->outputTokens])->toBe([1500, 300]);
})->with([
    'not a scorecard' => [['found' => false, 'par' => 0, 'tees' => []]],
    'slope of 160' => [['found' => true, 'par' => 71, 'tees' => [['name' => 'Red', 'rating' => 71.4, 'slope' => 160]]]],
    'slope of 144.5' => [['found' => true, 'par' => 71, 'tees' => [['name' => 'Red', 'rating' => 71.4, 'slope' => 144.5]]]],
    'rating of 39.9' => [['found' => true, 'par' => 71, 'tees' => [['name' => 'Red', 'rating' => 39.9, 'slope' => 144]]]],
    'refusal or truncated output' => [[]],
    'found without any tee' => [['found' => true, 'par' => 71, 'tees' => []]],
    'out of the schema' => [['found' => true, 'par' => 71, 'tees' => [['name' => 'Red', 'rating' => 71.4]]]],
]);

test('the reading sends the scorecard as served by SGT and nothing of the application', function () {
    createPlayersOnTest('Gab', 'Manu', 'Sean');
    createRoundCard(referenceCardData($this->gab->id, $this->manu->id));
    config(['golf.ai.model' => 'claude-opus-5-5']);
    SgtScorecardReader::fake([billedSgtReading(bethpageSgtReading())]);

    app(SgtScorecardReader::class)->read(sgtScorecard30());

    $sent = null;
    SgtScorecardReader::assertPrompted(function (AgentPrompt $prompt) use (&$sent): bool {
        $sent = $prompt;

        return true;
    });
    expect($sent->model)->toBe('claude-opus-5-5')
        ->and($sent->attachments)->toHaveCount(1)
        ->and($sent->attachments->first())->toBeInstanceOf(Base64Image::class)
        ->and($sent->attachments->first()->mimeType())->toBe('image/jpeg')
        ->and($sent->attachments->first()->content() === sgtScorecard30())->toBeTrue();
    foreach (['BETHPAGE', 'Bethpage', 'Gab', 'Manu', 'Sean'] as $text) {
        expect($sent->prompt)->not->toContain($text)
            ->and($sent->agent->instructions())->not->toContain($text);
    }
    SgtScorecardReader::assertPromptedTimes(1);
});
