<?php

namespace App\Integrations\Ai;

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

/*
 * What laravel/ai v1.3.0 does, read in vendor/laravel/ai and checked by a probe against
 * Http::fake (task 1.2 of scorecard-ai-import, 2026-10-28). Never against the network.
 *
 * - Key: config('ai.providers.anthropic.key'), from ANTHROPIC_API_KEY (config/ai.php). A faked
 *   agent never reads it: the fake answers with the key empty.
 * - Call: $agent->prompt($text, attachments: [...], provider: Lab::Anthropic, model: $model,
 *   timeout: $seconds). The SDK HTTP timeout defaults to 60 s.
 * - Structured output: implement HasStructuredOutput; schema(JsonSchema $schema) returns
 *   ['name' => $schema->string()->nullable()->required(), 'holes' => $schema->array()
 *   ->items($schema->integer()->nullable())->required(), 'par' => $schema->object([...])
 *   ->required(), ...]. A property without required() is optional. nullable() is sent as
 *   anyOf [{type}, {type: null}], every object gets additionalProperties: false. The SDK sends
 *   Anthropic's native output_config.format json_schema (no forced tool_choice, which Sonnet 5.5
 *   and Opus 5.5 reject); minimum, maximum, maxItems, pattern… are stripped by
 *   AnthropicSchemaSanitizer and folded into the description.
 * - Image: Laravel\Ai\Files\Image::fromPath($path, 'image/jpeg') gives a LocalImage, sent in
 *   base64 in the user message, before the prompt text.
 * - Output bound: a maxTokens(): int method (or #[MaxTokens]); without it the SDK sends
 *   max_tokens 64 000. On Sonnet 5.5 and Opus 5.5 thinking is always on and its tokens count in
 *   max_tokens and in output_tokens.
 * - Effort: implement HasProviderOptions; providerOptions(Lab|string $provider) returning
 *   ['output_config' => ['effort' => 'low']] is merged into output_config next to format.
 * - Usage: $response->usage is a Laravel\Ai\Responses\Data\TextUsage (inputTokens, which
 *   include the cache read and write tokens, outputTokens, which include thinking). Every
 *   response carries one, zeros when the API reports nothing.
 * - Refusal (stop_reason refusal), truncated output (max_tokens), text that is not JSON: no
 *   exception. prompt() returns a StructuredAgentResponse whose toArray() is [], with the
 *   billed usage. Valid JSON of the wrong shape comes back as is in toArray(): the SDK does
 *   not check it against the schema. The finish reason is
 *   $response->steps->last()->finishReason (FinishReason::ContentFilter, ::Length, ::Stop).
 * - Transient errors: Laravel\Ai\Exceptions\FailoverableException, i.e.
 *   ProviderConnectionException (wraps ConnectionException), RateLimitedException (429),
 *   ProviderOverloadedException (529, 502, 503, 504, 520, 522, 524), InsufficientCreditsException
 *   (402 or a billing message). A 500 surfaces as Illuminate\Http\Client\RequestException, as do
 *   400 and 401. The SDK retries once in process when Retry-After is 10 s or less.
 * - Fakes: ScorecardPhotoReader::fake([...]) takes a list or a closure
 *   fn (string $prompt, Collection $attachments, TextProvider $provider, string $model). An
 *   array answer gets a zero usage; to carry a usage, answer
 *   new StructuredTextResponse($card, json_encode($card), new TextUsage(3000, 1200), new Meta).
 *   The fake always reports FinishReason::Stop: a refusal or a truncation is faked by an empty
 *   card with its usage, which is what the real gateway returns. ->preventStrayPrompts() makes
 *   an unanswered prompt throw RuntimeException « Attempted prompt [...] without a fake agent
 *   response. ». assertPrompted(fn (AgentPrompt $prompt) => ...) sees ->prompt, ->attachments
 *   (LocalImage with ->path), ->model; assertPromptedTimes(), assertNeverPrompted().
 * - Migrations: the SDK only publishes create_agent_conversations_table, used by agents that
 *   remember conversations. This agent does not: nothing is published.
 */
/**
 * Reads the whole photo of a GSPro scorecard with a vision model of Anthropic, once per attempt
 * (design D3). It sends the photo and fixed instructions, nothing of the application: no
 * player, alias, round nor index.
 */
class ScorecardPhotoReader implements Agent, HasProviderOptions, HasStructuredOutput
{
    use Promptable;

    /** The fixed instructions of src/ai/completer.ts, for the whole photo and its date. */
    private const string INSTRUCTIONS = <<<'TEXT'
        Tu lis la photo d'une carte de score du simulateur de golf GSPro. La carte peut n'occuper qu'une partie de la photo, prise de biais.

        En haut, un bandeau donne le nom du parcours, puis une ligne de réglages qui contient les tees (par exemple « Red ») et la date (par exemple « DATE: 10/17/2026 »).
        Le tableau a une colonne par trou, de 1 à 18, avec trois colonnes de totaux : OUT après le trou 9 (trous 1 à 9), IN après le trou 18 (trous 10 à 18) et TOT (OUT + IN).
        Ses lignes, de haut en bas : les numéros de trou, PAR, INDEX (difficulté du trou, de 1 à 18, sans totaux), la distance de chaque trou, puis une ligne par joueur, avec son nom à gauche, ses 18 scores et ses totaux. Les lignes de joueur vides sont à ignorer.
        Certains scores sont encadrés ou cerclés selon leur écart au par : recopie seulement le chiffre.

        Recopie exactement ce qui est affiché, même si une somme te paraît fausse : ne corrige rien, ne calcule rien. Recopie le texte de la date tel qu'affiché, sans le convertir. Pour une case vide ou illisible, mets null plutôt que de deviner.
        TEXT;

    private const string PROMPT = 'Recopie cette carte de score.';

    public function instructions(): string
    {
        return self::INSTRUCTIONS;
    }

    /**
     * The card of src/ai/card.ts plus the date text; every value nullable, no bound (the SDK
     * strips them): ScorecardDraft::fromArray() checks the shape.
     *
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        $holes = fn (): Type => $schema->array()->items($schema->integer()->nullable())->required();
        $holeRow = fn (): Type => $schema->object([
            'holes' => $holes(),
            'out' => $schema->integer()->nullable()->required(),
            'in' => $schema->integer()->nullable()->required(),
            'total' => $schema->integer()->nullable()->required(),
        ])->required();

        return [
            'courseName' => $schema->string()->nullable()->required(),
            'tees' => $schema->string()->nullable()->required(),
            'dateText' => $schema->string()->nullable()->required(),
            'par' => $holeRow(),
            'strokeIndex' => $holes(),
            'distance' => $holeRow(),
            'players' => $schema->array()->items($schema->object([
                'name' => $schema->string()->nullable()->required(),
                'scores' => $holeRow(),
            ]))->required(),
        ];
    }

    /**
     * The output bound, thinking included; without it the SDK asks for 64 000 tokens.
     */
    public function maxTokens(): int
    {
        return config()->integer('golf.ai.max_output_tokens');
    }

    /**
     * @return array<string, mixed>
     */
    public function providerOptions(Lab|string $provider): array
    {
        return ['output_config' => ['effort' => 'low']];
    }

    /**
     * One call for the photo of the photos disk, with the model of AI_MODEL.
     *
     * @throws FailoverableException|RequestException on an error of the API, without usage
     */
    public function read(string $photoPath): ReadingResult
    {
        $jpeg = ScorecardImage::forReading($photoPath);

        try {
            $response = $this->prompt(
                self::PROMPT,
                attachments: [Image::fromPath($jpeg, 'image/jpeg')],
                provider: Lab::Anthropic,
                model: config()->string('golf.ai.model'),
                timeout: 90,
            );
        } finally {
            @unlink($jpeg);
        }

        return new ReadingResult($response->toArray(), $response->usage);
    }
}
