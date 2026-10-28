<?php

/*
 * Spec players « Liste des joueurs », « Ajout d'un joueur », « Renommage d'un joueur »,
 * « Ajout manuel d'un alias », « Retrait d'un alias »; design D3 and D8.
 *
 * Mutations that turn these tests red:
 * - scopeBindings() removed from the alias routes: Manu's alias is then deleted through Sean's
 *   address, the « alias of another player » test answers 302 instead of 404;
 * - the unique name rule compares with the case (name = ? instead of lower(name) = lower(?)):
 *   « gab » then reaches AddPlayer, whose DuplicatePlayer comes back as a toast, not as the
 *   `name` error;
 * - the list ordered by created_at instead of lower(name): Manu comes first;
 * - the aliases loaded in descending order: EMMA comes after MANU;
 * - the rename rule that does not ignore the renamed player: Manu → MANU is refused;
 * - an alias message written with the straight apostrophe (« L'alias … ») instead of the
 *   typographic one of DESIGN.md;
 * - RemoveAlias that also deletes the scores of the player
 *   (Score::query()->where('player_id', $alias->player_id)->delete()): the reference round
 *   loses Manu's 88;
 * - RoundDetail that names a line by the first alias of its player instead of its name: the
 *   renamed Manu reads « MANU » on the reference round, not « Emmanuel »;
 * - RemoveAlias that deletes every alias of the player ($alias->player->aliases()->delete()):
 *   after « SEANP » is removed and given to Sean, Manu has lost « MANU » too.
 */

use App\Domain\Rounds\Actions\CreateRound;
use App\Domain\Rounds\RoundCard;
use App\Models\Player;
use App\Models\PlayerAlias;
use Inertia\Testing\AssertableInertia as Assert;

test('without a session /players redirects to /login', function () {
    $this->get('/players')->assertRedirect('/login');
});

describe('with a session', function () {
    beforeEach(function () {
        $this->withPasscodeSession();
    });

    test('the page lists the players by name whatever the case, each with its sorted aliases', function () {
        $manu = Player::factory()->withAlias()->create(['name' => 'Manu']);
        PlayerAlias::factory()->for($manu)->create(['alias' => 'EMMA']);
        Player::factory()->create(['name' => 'gab']);
        Player::factory()->withAlias()->create(['name' => 'Sean']);

        $response = $this->get('/players')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('players/index'));

        $players = collect($response->inertiaProps('players'));

        expect($players->map(fn (array $player): array => [
            $player['name'],
            array_column($player['aliases'], 'alias'),
        ])->all())->toBe([
            ['gab', []],
            ['Manu', ['EMMA', 'MANU']],
            ['Sean', ['SEAN']],
        ])
            ->and($players[1]['id'])->toBe($manu->id)
            ->and($players[1]['aliases'][1]['id'])->toBe($manu->aliases()->where('alias', 'MANU')->value('id'));
    });

    test('an address under /players without a page answers 404', function () {
        $this->get('/players/xyz')->assertNotFound();
    });

    test('adding a player creates it without alias', function () {
        $this->from('/players')->post('/players', ['name' => '  Alex '])
            ->assertRedirect('/players')
            ->assertSessionHasNoErrors();

        expect(playersWithAliases())->toBe(['Alex' => []]);
    });

    test('adding a name taken whatever the case gives the name error', function () {
        Player::factory()->withAlias()->create(['name' => 'Gab']);

        $this->from('/players')->post('/players', ['name' => 'gab'])
            ->assertRedirect('/players')
            ->assertSessionHasErrors(['name' => 'Ce joueur existe déjà']);

        expect(playersWithAliases())->toBe(['Gab' => ['GAB']]);
    });

    test('a name made of spaces is required', function () {
        $this->from('/players')->post('/players', ['name' => '  '])
            ->assertSessionHasErrors(['name' => 'Le nom est obligatoire']);

        expect(Player::query()->count())->toBe(0);
    });

    test('a name of 51 characters is refused, one of 50 is accepted', function () {
        $this->from('/players')->post('/players', ['name' => str_repeat('a', 51)])
            ->assertSessionHasErrors('name');

        expect(Player::query()->count())->toBe(0);

        $this->from('/players')->post('/players', ['name' => str_repeat('b', 50)])
            ->assertSessionHasNoErrors();

        expect(Player::query()->pluck('name')->all())->toBe([str_repeat('b', 50)]);
    });

    test('a player is renamed, its aliases kept', function () {
        $manu = Player::factory()->withAlias()->create(['name' => 'Manu']);

        $this->from('/players')->patch("/players/{$manu->id}", ['name' => 'Emmanuel'])
            ->assertRedirect('/players')
            ->assertSessionHasNoErrors();

        expect(playersWithAliases())->toBe(['Emmanuel' => ['MANU']]);
    });

    test('a player is renamed to its own name in another case', function () {
        $manu = Player::factory()->create(['name' => 'Manu']);

        $this->from('/players')->patch("/players/{$manu->id}", ['name' => 'MANU'])
            ->assertSessionHasNoErrors();

        expect($manu->fresh()->name)->toBe('MANU');
    });

    test('renaming to a name taken by another player gives the name error', function () {
        Player::factory()->create(['name' => 'Gab']);
        $manu = Player::factory()->create(['name' => 'Manu']);

        $this->from('/players')->patch("/players/{$manu->id}", ['name' => 'GAB'])
            ->assertSessionHasErrors(['name' => 'Ce joueur existe déjà']);

        expect($manu->fresh()->name)->toBe('Manu');
    });

    test('an alias is added upper case', function () {
        $sean = Player::factory()->withAlias()->create(['name' => 'Sean']);

        $this->from('/players')->post("/players/{$sean->id}/aliases", ['alias' => ' seanp '])
            ->assertRedirect('/players')
            ->assertSessionHasNoErrors();

        expect(playersWithAliases())->toBe(['Sean' => ['SEAN', 'SEANP']]);
    });

    test('an empty alias is required', function () {
        $sean = Player::factory()->withAlias()->create(['name' => 'Sean']);

        $this->from('/players')->post("/players/{$sean->id}/aliases", ['alias' => ''])
            ->assertSessionHasErrors(['alias' => 'L’alias est obligatoire']);

        expect(playersWithAliases())->toBe(['Sean' => ['SEAN']]);
    });

    test('an alias of 51 characters is refused', function () {
        $sean = Player::factory()->withAlias()->create(['name' => 'Sean']);

        $this->from('/players')->post("/players/{$sean->id}/aliases", ['alias' => str_repeat('A', 51)])
            ->assertSessionHasErrors(['alias' => 'L’alias fait au plus 50 caractères']);

        expect(playersWithAliases())->toBe(['Sean' => ['SEAN']]);
    });

    test('an alias is removed from its player', function () {
        $manu = Player::factory()->withAlias()->create(['name' => 'Manu']);
        $alias = PlayerAlias::factory()->for($manu)->create(['alias' => 'SEANP']);

        $this->from('/players')->delete("/players/{$manu->id}/aliases/{$alias->id}")
            ->assertRedirect('/players');

        expect(playersWithAliases())->toBe(['Manu' => ['MANU']]);
    });

    test('an alias of another player is not found', function () {
        $manu = Player::factory()->withAlias()->create(['name' => 'Manu']);
        $sean = Player::factory()->withAlias()->create(['name' => 'Sean']);
        $manuAlias = $manu->aliases()->sole();

        $this->from('/players')->delete("/players/{$sean->id}/aliases/{$manuAlias->id}")
            ->assertNotFound();

        expect(playersWithAliases())->toBe(['Manu' => ['MANU'], 'Sean' => ['SEAN']]);
    });

    describe('when Manu has played the reference round', function () {
        beforeEach(function () {
            $this->gab = Player::factory()->withAlias()->create(['name' => 'Gab']);
            $this->manu = Player::factory()->withAlias()->create(['name' => 'Manu']);
            $this->round = app(CreateRound::class)->handle(RoundCard::fromArray(referenceCardData($this->gab->id, $this->manu->id)));
        });

        test('removing an alias leaves the rounds already saved as they are', function () {
            $this->from('/players')->delete("/players/{$this->manu->id}/aliases/{$this->manu->aliases()->sole()->id}")
                ->assertRedirect('/players');

            expect(playersWithAliases())->toBe(['Gab' => ['GAB'], 'Manu' => []]);

            $this->get("/rounds/{$this->round->id}")->assertInertia(fn (Assert $page) => $page
                ->where('round.players', [['name' => 'Gab', 'gross' => 72], ['name' => 'Manu', 'gross' => 88]]));
        });

        test('a renamed player keeps its alias and carries its new name on the rounds it played', function () {
            $this->from('/players')->patch("/players/{$this->manu->id}", ['name' => 'Emmanuel'])
                ->assertRedirect('/players')
                ->assertSessionHasNoErrors();

            $players = $this->get('/players')->inertiaProps('players');

            expect(array_map(fn (array $player): array => [$player['name'], array_column($player['aliases'], 'alias')], $players))
                ->toBe([['Emmanuel', ['MANU']], ['Gab', ['GAB']]]);

            $this->get("/rounds/{$this->round->id}")->assertInertia(fn (Assert $page) => $page
                ->where('round.players', [['name' => 'Gab', 'gross' => 72], ['name' => 'Emmanuel', 'gross' => 88]]));
        });
    });

    test('an alias removed from one player can be given to another', function () {
        $manu = Player::factory()->withAlias()->create(['name' => 'Manu']);
        $alias = PlayerAlias::factory()->for($manu)->create(['alias' => 'SEANP']);
        $sean = Player::factory()->withAlias()->create(['name' => 'Sean']);

        $this->from('/players')->delete("/players/{$manu->id}/aliases/{$alias->id}")
            ->assertRedirect('/players');
        $this->from('/players')->post("/players/{$sean->id}/aliases", ['alias' => 'SEANP'])
            ->assertRedirect('/players')
            ->assertSessionHasNoErrors();

        expect(playersWithAliases())->toBe(['Manu' => ['MANU'], 'Sean' => ['SEAN', 'SEANP']]);
    });
});
