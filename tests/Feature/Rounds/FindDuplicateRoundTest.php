<?php

/*
 * Mutations that turn these tests red:
 * - course and tees compared with their case: « bethpage black / RED » finds nothing;
 * - the « player:gross » signature left unsorted: the lines in the other order find nothing;
 * - the except round not left out: the correction of the round finds the round itself;
 * - the gross left out of the signature: Manu at 87 finds the reference round.
 */

use App\Domain\Rounds\Actions\CreateRound;
use App\Domain\Rounds\Queries\FindDuplicateRound;
use App\Domain\Rounds\RoundCard;
use App\Models\Player;

beforeEach(function () {
    $this->gab = Player::factory()->create(['name' => 'Gab']);
    $this->manu = Player::factory()->create(['name' => 'Manu']);
    $this->reference = app(CreateRound::class)->handle(RoundCard::fromArray(referenceCardData($this->gab->id, $this->manu->id)));
    // Same course and date, other players: never the duplicate.
    $sean = Player::factory()->create(['name' => 'Sean']);
    app(CreateRound::class)->handle(RoundCard::fromArray(referenceCardData($this->gab->id, $sean->id)));
});

test('the same card finds the saved round', function () {
    $card = RoundCard::fromArray(referenceCardData($this->gab->id, $this->manu->id));

    expect(app(FindDuplicateRound::class)->for($card)?->id)->toBe($this->reference->id);
});

test('course and tees are compared without case nor surrounding spaces', function () {
    $card = referenceCardData($this->gab->id, $this->manu->id);
    $card['courseName'] = ' bethpage black ';
    $card['tees'] = 'RED';

    expect(app(FindDuplicateRound::class)->for(RoundCard::fromArray($card))?->id)->toBe($this->reference->id);
});

test('the lines in the other order find the saved round', function () {
    $card = referenceCardData($this->gab->id, $this->manu->id);
    $card['lines'] = array_reverse($card['lines']);

    expect(app(FindDuplicateRound::class)->for(RoundCard::fromArray($card))?->id)->toBe($this->reference->id);
});

test('another gross finds nothing', function () {
    $card = referenceCardData($this->gab->id, $this->manu->id);
    $card['lines'][1]['strokes'][0] = 6;

    expect(app(FindDuplicateRound::class)->for(RoundCard::fromArray($card)))->toBeNull();
});

test('the corrected round is not its own duplicate', function () {
    $card = RoundCard::fromArray(referenceCardData($this->gab->id, $this->manu->id));

    expect(app(FindDuplicateRound::class)->for($card, $this->reference))->toBeNull();
});
