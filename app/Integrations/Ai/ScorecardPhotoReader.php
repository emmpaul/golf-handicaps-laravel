<?php

namespace App\Integrations\Ai;

use Laravel\Ai\Contracts\Agent;
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
class ScorecardPhotoReader implements Agent
{
    use Promptable;

    // The reading instructions, the schema, the bounds and the image come with task 5.4.
    public function instructions(): string
    {
        return '';
    }
}
