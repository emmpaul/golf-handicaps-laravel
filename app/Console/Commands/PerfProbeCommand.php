<?php

namespace App\Console\Commands;

use App\Support\PerfProbeStatistics;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

/**
 * The read-only measure of an address (design D2 of optimize-performance): one GET /login for
 * the XSRF cookie, one POST /login with the passcode, then GET requests only. Each trial opens a
 * new connection, as the reference measure of 2026-10-29 did with curl, and keeps
 * starttransfer_time − appconnect_time: from the end of the TLS handshake to the first byte.
 *
 * The passcode comes from PERF_PASSCODE, or is asked without being shown. It and the cookies
 * stay in memory: neither the output nor the report under reports/perf/ holds them.
 */
#[Signature('golf:perf-probe
    {url : Adresse mesurée, par exemple https://golf.emmanuelpaul.com}
    {--runs=20 : Nombre d\'essais par page}')]
#[Description('Measure the pages of an address, read only: one POST /login, then GET requests')]
class PerfProbeCommand extends Command
{
    /**
     * Full pages open without a session, measured without any cookie.
     */
    private const array PUBLIC_PAGES = ['/robots.txt', '/up', '/api/version', '/login'];

    /**
     * Full pages behind the passcode, measured with the session of the POST /login.
     */
    private const array PROTECTED_PAGES = ['/', '/handicaps', '/strokes', '/rounds', '/players', '/courses', '/import'];

    /**
     * The five sections of the bar, in its order, measured again as Inertia visits.
     */
    private const array SECTIONS = ['/rounds', '/handicaps', '/strokes', '/players', '/import'];

    /**
     * The columns of the report and of the table printed at the end.
     */
    private const array HEADERS = ['Adresse', 'Visite', 'Statut', 'Médiane (ms)', '90e centile (ms)', 'Min (ms)', 'app (ms)', 'db-count', 'Taille (o)'];

    public function handle(): int
    {
        $url = rtrim((string) $this->argument('url'), '/');
        $runs = (int) $this->option('runs');
        $host = parse_url($url, PHP_URL_HOST);

        if (! is_string($host) || ! in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true)) {
            $this->error("Adresse invalide : {$url}. Exemple : https://golf.emmanuelpaul.com");

            return self::FAILURE;
        }

        if ($runs < 1) {
            $this->error('--runs doit valoir au moins 1.');

            return self::FAILURE;
        }

        $passcode = getenv('PERF_PASSCODE');

        if (! is_string($passcode) || $passcode === '') {
            $passcode = (string) $this->secret("Code d'accès");
        }

        try {
            $session = new CookieJar;
            $version = $this->openSession($url, $passcode, $session);

            if ($version === null) {
                return self::FAILURE;
            }

            $rows = $this->measureAll($url, $runs, $session, $version);
        } catch (ConnectionException $exception) {
            $this->error("Connexion impossible à {$url} : {$exception->getMessage()}");

            return self::FAILURE;
        }

        $date = now(config()->string('app.display_timezone'))->toDateString();
        $reportPath = "reports/perf/{$date}-{$host}.md";
        File::ensureDirectoryExists(base_path('reports/perf'));
        File::put(base_path($reportPath), $this->report($url, $date, $runs, $rows));

        $this->table(self::HEADERS, $rows);
        $this->info("Rapport écrit : {$reportPath}");

        return self::SUCCESS;
    }

    /**
     * GET /login for the XSRF cookie and the Inertia version, then the only POST of the measure.
     *
     * @return string|null the Inertia version, null when the session could not be opened
     */
    private function openSession(string $url, string $passcode, CookieJar $session): ?string
    {
        $loginPage = $this->client()->withOptions(['cookies' => $session])->get("{$url}/login");
        $xsrfToken = $session->getCookieByName('XSRF-TOKEN')?->getValue();
        $version = $this->inertiaVersion($loginPage->body());

        if ($xsrfToken === null || $version === null) {
            $this->error("La page {$url}/login ne donne pas le jeton XSRF ou la version Inertia (réponse {$loginPage->status()}).");

            return null;
        }

        $login = $this->client()
            ->withOptions(['cookies' => $session])
            ->withHeaders(['X-XSRF-TOKEN' => urldecode($xsrfToken)])
            ->asForm()
            ->post("{$url}/login", ['passcode' => $passcode]);

        if (! $login->redirect() || (parse_url($login->header('Location'), PHP_URL_PATH) ?: '/') !== '/') {
            $this->error("Code d'accès refusé (réponse {$login->status()}).");

            return null;
        }

        return $version;
    }

    /**
     * Every page, $runs times each, in the order of the report.
     *
     * @return list<list<string>> the cells of each line of the report
     */
    private function measureAll(string $url, int $runs, CookieJar $session, string $version): array
    {
        $targets = [
            ...array_map(fn (string $path): array => [$path, null, null], self::PUBLIC_PAGES),
            ...array_map(fn (string $path): array => [$path, $session, null], self::PROTECTED_PAGES),
            ...array_map(fn (string $path): array => [$path, $session, $version], self::SECTIONS),
        ];

        return array_map(
            fn (array $target): array => $this->measure($url, $target[0], $runs, $target[1], $target[2]),
            $targets,
        );
    }

    /**
     * $runs trials of one page, each on a new connection, reduced to a line of the report.
     *
     * @return list<string>
     */
    private function measure(string $url, string $path, int $runs, ?CookieJar $session, ?string $inertiaVersion): array
    {
        $visit = $inertiaVersion === null ? 'page' : 'Inertia';
        $this->line("Mesure de {$path} ({$visit})…");
        $statuses = $firstBytes = $sizes = $appTimes = $queryCounts = [];

        for ($trial = 0; $trial < $runs; $trial++) {
            $request = $this->client()->withOptions([
                'curl' => [CURLOPT_FRESH_CONNECT => true, CURLOPT_FORBID_REUSE => true],
                ...($session === null ? [] : ['cookies' => $session]),
            ]);

            if ($inertiaVersion !== null) {
                $request->withHeaders([
                    'X-Inertia' => 'true',
                    'X-Inertia-Version' => $inertiaVersion,
                    'X-Requested-With' => 'XMLHttpRequest',
                ]);
            }

            $response = $request->get($url.$path);
            $statuses[$response->status()] = true;
            $this->collectTrial($response, $firstBytes, $sizes, $appTimes, $queryCounts);
        }

        $firstByte = PerfProbeStatistics::summarize($firstBytes);

        return [
            "`{$path}`",
            $visit,
            implode(', ', array_keys($statuses)),
            PerfProbeStatistics::display($firstByte['median'] ?? null),
            PerfProbeStatistics::display($firstByte['p90'] ?? null),
            PerfProbeStatistics::display($firstByte['min'] ?? null),
            PerfProbeStatistics::display(PerfProbeStatistics::summarize($appTimes)['median'] ?? null, 1),
            PerfProbeStatistics::display(PerfProbeStatistics::summarize($queryCounts)['median'] ?? null),
            PerfProbeStatistics::display(PerfProbeStatistics::summarize($sizes)['median'] ?? null),
        ];
    }

    /**
     * Adds the values of one trial that its transfer statistics and Server-Timing give.
     *
     * @param  list<float>  $firstBytes
     * @param  list<float>  $sizes
     * @param  list<float>  $appTimes
     * @param  list<float>  $queryCounts
     */
    private function collectTrial(Response $response, array &$firstBytes, array &$sizes, array &$appTimes, array &$queryCounts): void
    {
        $stats = $response->handlerStats();

        if (isset($stats['starttransfer_time'], $stats['appconnect_time'])) {
            $firstBytes[] = max(0.0, (float) $stats['starttransfer_time'] - (float) $stats['appconnect_time']) * 1000;
        }

        if (isset($stats['size_download'])) {
            $sizes[] = (float) $stats['size_download'];
        }

        $serverTiming = PerfProbeStatistics::serverTiming($response->header('Server-Timing') ?: null);

        if (isset($serverTiming['app'])) {
            $appTimes[] = $serverTiming['app'];
        }

        if (isset($serverTiming['db-count'])) {
            $queryCounts[] = $serverTiming['db-count'];
        }
    }

    /**
     * The version of the Inertia page held in the data-page script of a full page.
     */
    private function inertiaVersion(string $html): ?string
    {
        if (preg_match('/<script[^>]*\bdata-page="[^"]*"[^>]*>(.*?)<\/script>/s', $html, $match) !== 1) {
            return null;
        }

        $page = json_decode($match[1], true);
        $version = is_array($page) ? ($page['version'] ?? null) : null;

        return is_string($version) ? $version : null;
    }

    /**
     * A client that neither follows redirects nor retries: each answer is measured as it comes.
     * It asks for gzip, as a browser does: curl decodes the body, and size_download keeps the
     * compressed bytes received.
     */
    private function client(): PendingRequest
    {
        return Http::connectTimeout(10)->timeout(30)->withoutRedirecting()
            ->withHeaders(['Accept-Encoding' => 'gzip']);
    }

    /**
     * @param  list<list<string>>  $rows
     */
    private function report(string $url, string $date, int $runs, array $rows): string
    {
        $line = fn (array $cells): string => '| '.implode(' | ', $cells).' |';

        return implode("\n", [
            '# Mesure de performance',
            '',
            "- Adresse : {$url}",
            "- Date : {$date}",
            "- Essais par page : {$runs}",
            '- Temps : de la fin de la poignée de main TLS au premier octet (`starttransfer − appconnect`), connexion neuve à chaque essai. En HTTP sans TLS, depuis le début de la connexion.',
            '- `app` et `db-count` : médianes lues dans `Server-Timing`, « — » sans l\'en-tête. Taille : médiane des octets reçus, compressés.',
            '',
            $line(self::HEADERS),
            $line(['---', '---', '---', '---:', '---:', '---:', '---:', '---:', '---:']),
            ...array_map($line, $rows),
            '',
        ]);
    }
}
