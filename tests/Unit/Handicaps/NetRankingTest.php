<?php

/*
 * Port of the rankNet cases of tests/server/rounds/leaderboard.test.ts of the Next.js
 * application.
 *
 * Mutations that turn these tests red:
 * - a dense rank (1, 2, 2, 3): the player after two tied seconds is 3rd instead of 4th;
 * - net = gross + handicap: Gab and Manu are at 73 and 105 net, the plus player at −1,6 who
 *   plays 70 at 68 instead of 72;
 * - a player without index ranked (handicap 0): the player at 80 gets a net of 80 and rank 3.
 */

use App\Domain\Handicaps\NetEntry;
use App\Domain\Handicaps\NetRanking;
use App\Domain\Handicaps\RankedNet;
use App\Domain\Handicaps\RatedCourse;

test('Gab (10) and Manu (174) at 72 and 88 are tied first at 71 net; without index, not ranked', function () {
    expect(NetRanking::rank([
        new NetEntry(72, 10),
        new NetEntry(88, 174),
        new NetEntry(80, null),
    ], null))->toEqual([
        new RankedNet(gross: 72, indexBeforeT: 10, playingHandicap: 1, net: 71, rank: 1),
        new RankedNet(gross: 88, indexBeforeT: 174, playingHandicap: 17, net: 71, rank: 1),
        new RankedNet(gross: 80, indexBeforeT: null, playingHandicap: null, net: null, rank: null),
    ]);
});

test('Gab (10) and Manu (174) at 72 and 88 on 71,4 / 144: handicaps 2 and 23, nets 70 and 65', function () {
    expect(NetRanking::rank([
        new NetEntry(72, 10),
        new NetEntry(88, 174),
    ], new RatedCourse(714, 144, 71)))->toEqual([
        new RankedNet(gross: 72, indexBeforeT: 10, playingHandicap: 2, net: 70, rank: 2),
        new RankedNet(gross: 88, indexBeforeT: 174, playingHandicap: 23, net: 65, rank: 1),
    ]);
});

test('a plus player at −1,6 before a round without rating plays 70: handicap −2, net 72', function () {
    expect(NetRanking::rank([new NetEntry(70, -16)], null))->toEqual([
        new RankedNet(gross: 70, indexBeforeT: -16, playingHandicap: -2, net: 72, rank: 1),
    ]);
});

test('after two tied at the 2nd rank, the next player is 4th', function () {
    $ranked = NetRanking::rank([
        new NetEntry(80, 0),
        new NetEntry(75, 50),
        new NetEntry(72, 125),
        new NetEntry(70, 0),
    ], null);

    expect(array_map(fn (RankedNet $entry): array => [$entry->net, $entry->rank], $ranked))
        ->toBe([[80, 4], [70, 2], [59, 1], [70, 2]]);
});
