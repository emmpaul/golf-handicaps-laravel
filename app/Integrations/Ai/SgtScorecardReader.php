<?php

namespace App\Integrations\Ai;

use App\Domain\Courses\Sgt\SgtCard;
use App\Domain\Courses\Sgt\SgtTee;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Http\Client\RequestException;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasProviderOptions;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Exceptions\FailoverableException;
use Laravel\Ai\Files\Image;
use Laravel\Ai\Promptable;

/**
 * Reads the par and the rating and slope of each tee on the scorecard image of a Simulator Golf
 * Tour course (design D4), on the model of ScorecardPhotoReader. It sends the JPEG as served by
 * SGT, neither re-encoded nor reduced, and fixed instructions: nothing of the application, no
 * course name, player nor score. The SDK behaviour it relies on is noted in
 * ScorecardPhotoReader.
 */
class SgtScorecardReader implements Agent, HasProviderOptions, HasStructuredOutput
{
    use Promptable;

    /** The fixed instructions of src/ai/rating-reader.ts. */
    private const string INSTRUCTIONS = <<<'TEXT'
        Tu regardes l'image de carte de score d'un parcours publiée par Simulator Golf Tour (SGT).

        Au-dessus des colonnes de tees, une ligne « Slope/Rating » donne, pour chaque tee, deux nombres au format rating/slope : la course rating, à une décimale (par exemple 71.4), puis le slope, entier (par exemple 144). Donne une entrée par colonne de tee, dans l'ordre de la carte, avec le nom du tee tel qu'il est écrit, sa rating et son slope. Donne aussi le par total du parcours.

        Si l'image n'est pas une carte de score SGT, mets found à false.
        TEXT;

    /** Seconds of the call; with the two SGT requests it stays under the job timeout. */
    public const int TIMEOUT_SECONDS = 60;

    private const string PROMPT = 'Donne le par et la rating et le slope de chaque tee de la carte.';

    public function instructions(): string
    {
        return self::INSTRUCTIONS;
    }

    /**
     * The card of src/ai/rating-reader.ts, without bounds (the SDK strips them): read() checks
     * the shape and the WHS bounds.
     *
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'found' => $schema->boolean()->required(),
            'par' => $schema->integer()->required(),
            'tees' => $schema->array()->items($schema->object([
                'name' => $schema->string()->required(),
                'rating' => $schema->number()->required(),
                'slope' => $schema->integer()->required(),
            ]))->required(),
        ];
    }

    /**
     * The output bound, thinking included; without it the SDK asks for 64 000 tokens.
     */
    public function maxTokens(): int
    {
        return config()->integer('golf.ai.rating_max_output_tokens');
    }

    /**
     * @return array<string, mixed>
     */
    public function providerOptions(Lab|string $provider): array
    {
        return ['output_config' => ['effort' => 'low']];
    }

    /**
     * One call for the bytes of an SGT scorecard JPEG, with the model of AI_MODEL.
     *
     * @throws FailoverableException|RequestException on an error of the API, without usage
     */
    public function read(string $jpeg): SgtCardReading
    {
        $response = $this->prompt(
            self::PROMPT,
            attachments: [Image::fromBase64(base64_encode($jpeg), 'image/jpeg')],
            provider: Lab::Anthropic,
            model: config()->string('golf.ai.model'),
            timeout: self::TIMEOUT_SECONDS,
        );

        return new SgtCardReading(self::card($response->toArray()), $response->usage);
    }

    /**
     * The card of a reading, or null when it is unusable: a refusal or a truncated output (an
     * empty array), a shape out of the schema, found false, no tee at all (decision Q17), a
     * rating out of 40–90 or a slope out of 55–155 or not whole. The rating goes to tenths.
     *
     * @param  array<mixed>  $answer
     */
    private static function card(array $answer): ?SgtCard
    {
        if (($answer['found'] ?? null) !== true || ! is_int($answer['par'] ?? null)
            || ! is_array($answer['tees'] ?? null) || $answer['tees'] === [] || ! array_is_list($answer['tees'])) {
            return null;
        }

        $tees = [];
        foreach ($answer['tees'] as $tee) {
            $tee = self::tee($tee);
            if ($tee === null) {
                return null;
            }
            $tees[] = $tee;
        }

        return new SgtCard($answer['par'], $tees);
    }

    private static function tee(mixed $tee): ?SgtTee
    {
        if (! is_array($tee) || ! is_string($tee['name'] ?? null)) {
            return null;
        }

        $rating = $tee['rating'] ?? null;
        $slope = $tee['slope'] ?? null;
        if (! is_int($rating) && ! is_float($rating)) {
            return null;
        }
        if (is_float($slope) && floor($slope) === $slope) {
            $slope = (int) $slope;
        }
        if ($rating < 40 || $rating > 90 || ! is_int($slope) || $slope < 55 || $slope > 155) {
            return null;
        }

        return new SgtTee($tee['name'], (int) round($rating * 10), $slope);
    }
}
