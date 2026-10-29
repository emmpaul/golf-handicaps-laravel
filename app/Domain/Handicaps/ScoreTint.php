<?php

namespace App\Domain\Handicaps;

/**
 * The tint of the delta of an average by par to the par (spec handicap-index, « Moyennes par
 * par »): green up to 0,3 stroke, grey up to 1,0, red above, compared before rounding.
 */
enum ScoreTint: string
{
    case Good = 'good';
    case Neutral = 'neutral';
    case Bad = 'bad';

    /**
     * The tint of $sum strokes on $holes holes of par $par, in integers: green when
     * 10 × (sum − par × holes) ≤ 3 × holes, grey when ≤ 10 × holes (design D13).
     */
    public static function of(int $sum, int $par, int $holes): self
    {
        $tenths = 10 * ($sum - $par * $holes);

        return match (true) {
            $tenths <= 3 * $holes => self::Good,
            $tenths <= 10 * $holes => self::Neutral,
            default => self::Bad,
        };
    }
}
