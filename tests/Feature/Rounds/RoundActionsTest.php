<?php

/*
 * Mutations that turn these tests red:
 * - the checks run after the insertion: the par of 6 reaches the CHECK of round_holes and a
 *   QueryException comes out instead of IncoherentRoundCard;
 * - UpdateRound that sets photo_path back to null: the corrected round loses its photo;
 * - UpdateRound that keeps the former lines: Manu's scores stay next to Sean's;
 * - DeleteRound that deletes the photo before the commit: after the forced rollback the round
 *   still exists but its file is gone.
 */

use App\Domain\Rounds\Actions\CreateRound;
use App\Domain\Rounds\Actions\DeleteRound;
use App\Domain\Rounds\Actions\UpdateRound;
use App\Domain\Rounds\Exceptions\IncoherentRoundCard;
use App\Domain\Rounds\Problem;
use App\Domain\Rounds\RoundCard;
use App\Models\Player;
use App\Models\Round;
use App\Models\RoundHole;
use App\Models\RoundPlayer;
use App\Models\Score;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->gab = Player::factory()->create(['name' => 'Gab']);
    $this->manu = Player::factory()->create(['name' => 'Manu']);
});

function grossOf(Round $round, Player $player): int
{
    return (int) Score::query()->where('round_id', $round->id)->where('player_id', $player->id)->sum('strokes');
}

test('creating a round writes its holes, lines and scores', function () {
    $round = app(CreateRound::class)->handle(RoundCard::fromArray(referenceCardData($this->gab->id, $this->manu->id)));

    expect(Round::query()->count())->toBe(1)
        ->and($round->fresh()->played_on->toDateString())->toBe('2026-10-17')
        ->and($round->course_name)->toBe('BETHPAGE BLACK')
        ->and($round->tees)->toBe('Red')
        ->and(RoundHole::query()->where('round_id', $round->id)->orderBy('hole')->pluck('par')->all())
        ->toBe([4, 4, 3, 5, 4, 4, 5, 3, 4, 4, 4, 4, 5, 3, 4, 4, 3, 4])
        ->and(RoundPlayer::query()->where('round_id', $round->id)->orderBy('position')->get(['player_id', 'position'])->toArray())
        ->toBe([
            ['player_id' => $this->gab->id, 'position' => 0],
            ['player_id' => $this->manu->id, 'position' => 1],
        ])
        ->and(Score::query()->where('round_id', $round->id)->count())->toBe(36)
        ->and(grossOf($round, $this->gab))->toBe(72)
        ->and(grossOf($round, $this->manu))->toBe(88);
});

test('an incoherent card is refused before any write', function () {
    $card = referenceCardData($this->gab->id, $this->manu->id);
    $card['holes'][2]['par'] = 6;

    $refusal = null;
    try {
        app(CreateRound::class)->handle(RoundCard::fromArray($card));
    } catch (IncoherentRoundCard $exception) {
        $refusal = $exception;
    }

    expect($refusal?->getMessage())->toBe('1 problème à corriger')
        ->and($refusal?->problems)->toEqual([new Problem('Par 6 hors de 3 à 5', ['par:3'])])
        ->and(Round::query()->count())->toBe(0)
        ->and(RoundHole::query()->count())->toBe(0)
        ->and(Score::query()->count())->toBe(0);
});

test('correcting a score changes the gross and keeps the photo', function () {
    $round = app(CreateRound::class)->handle(RoundCard::fromArray(referenceCardData($this->gab->id, $this->manu->id)));
    $round->update(['photo_path' => 'rounds/img3064.webp']);
    $card = referenceCardData($this->gab->id, $this->manu->id);
    $card['lines'][1]['strokes'][0] = 6;

    app(UpdateRound::class)->handle($round, RoundCard::fromArray($card));

    expect(grossOf($round, $this->manu))->toBe(87)
        ->and(grossOf($round, $this->gab))->toBe(72)
        ->and($round->fresh()->photo_path)->toBe('rounds/img3064.webp')
        ->and(Score::query()->count())->toBe(36)
        ->and(RoundHole::query()->count())->toBe(18);
});

test('replacing a player leaves none of his scores', function () {
    $sean = Player::factory()->create(['name' => 'Sean']);
    $round = app(CreateRound::class)->handle(RoundCard::fromArray(referenceCardData($this->gab->id, $this->manu->id)));

    app(UpdateRound::class)->handle($round, RoundCard::fromArray(referenceCardData($this->gab->id, $sean->id)));

    expect($round->lines()->pluck('player_id')->all())->toBe([$this->gab->id, $sean->id])
        ->and(Score::query()->where('player_id', $this->manu->id)->count())->toBe(0)
        ->and(grossOf($round, $sean))->toBe(88);
});

test('an incoherent correction changes nothing', function () {
    $round = app(CreateRound::class)->handle(RoundCard::fromArray(referenceCardData($this->gab->id, $this->manu->id)));
    $card = referenceCardData($this->gab->id, $this->manu->id);
    $card['lines'][1]['strokes'][0] = 16;

    expect(fn () => app(UpdateRound::class)->handle($round, RoundCard::fromArray($card)))
        ->toThrow(IncoherentRoundCard::class, '1 problème à corriger');

    expect(grossOf($round, $this->manu))->toBe(88);
});

test('deleting a round deletes its photo and keeps its players', function () {
    Storage::fake('photos');
    $round = Round::factory()->withPlayers($this->gab, $this->manu)->withPhoto()->create();
    Storage::disk('photos')->assertExists((string) $round->photo_path);

    app(DeleteRound::class)->handle($round);

    expect(Round::query()->count())->toBe(0)
        ->and(Score::query()->count())->toBe(0)
        ->and(Player::query()->orderBy('name')->pluck('name')->all())->toBe(['Gab', 'Manu']);
    Storage::disk('photos')->assertMissing((string) $round->photo_path);
});

test('a rolled back deletion keeps the photo', function () {
    Storage::fake('photos');
    $round = Round::factory()->withPlayers($this->gab, $this->manu)->withPhoto()->create();

    try {
        DB::transaction(function () use ($round): void {
            app(DeleteRound::class)->handle($round);

            throw new RuntimeException('forced rollback');
        });
    } catch (RuntimeException) {
    }

    expect(Round::query()->pluck('id')->all())->toBe([$round->id]);
    Storage::disk('photos')->assertExists((string) $round->photo_path);
});
