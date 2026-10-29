<?php

namespace App\Integrations\Sgt;

use App\Domain\Courses\Sgt\SgtCourse;
use App\Domain\Courses\Sgt\SgtLinks;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Simulator Golf Tour seen from the lookup job (design D3): a user agent that names the
 * application, 10 s to connect and 20 s for the answer, the course list kept 24 h in the
 * default cache store so that the sweep of a deployment downloads it once.
 */
final class SgtClient
{
    private const string COURSES_URL = 'https://simulatorgolftour.com/courses';

    /** Seconds per request; twice this plus the AI reading stays under the job timeout. */
    public const int TIMEOUT_SECONDS = 20;

    private const string USER_AGENT = 'golf-handicaps-laravel (+https://github.com/emmpaul/golf-handicaps-laravel)';

    private const string CACHE_KEY = 'sgt:courses';

    /**
     * The courses of SGT. Kept as plain arrays: the database store unserializes no class. An
     * exception while reading keeps nothing.
     *
     * @return list<SgtCourse>
     *
     * @throws SgtUnavailable
     */
    public function courses(): array
    {
        /** @var list<array{int, string, string, int}> $rows */
        $rows = Cache::remember(self::CACHE_KEY, now()->addDay(), fn (): array => array_map(
            fn (SgtCourse $course): array => [$course->id, $course->name, $course->location, $course->par],
            SgtCoursesPage::parse($this->get(self::COURSES_URL)),
        ));

        return array_map(fn (array $row): SgtCourse => new SgtCourse(...$row), $rows);
    }

    /**
     * The bytes of the scorecard JPEG of a course, as served.
     *
     * @throws SgtUnavailable
     */
    public function scorecard(int $sgtCourseId): string
    {
        return $this->get(SgtLinks::scorecardUrl($sgtCourseId));
    }

    /**
     * @throws SgtUnavailable on a connection error, a delay or an HTTP error status
     */
    private function get(string $url): string
    {
        try {
            return $this->http()->get($url)->body();
        } catch (ConnectionException|RequestException $exception) {
            throw new SgtUnavailable("SGT unreachable: {$exception->getMessage()}", previous: $exception);
        }
    }

    private function http(): PendingRequest
    {
        return Http::withUserAgent(self::USER_AGENT)->connectTimeout(10)->timeout(self::TIMEOUT_SECONDS)->throw();
    }
}
