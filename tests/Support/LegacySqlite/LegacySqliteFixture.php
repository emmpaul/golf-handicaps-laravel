<?php

namespace Tests\Support\LegacySqlite;

use PDO;
use RuntimeException;

/**
 * A base SQLite of the Next application, built for one test (design D15 of
 * sqlite-import-command): a temporary file migrated by the Drizzle migrations of
 * tests/fixtures/legacy-sqlite/drizzle up to the level asked, then filled with invented rows,
 * and a temporary uploads folder. No real base ever enters the repository.
 *
 * Dates are written as Next writes them: milliseconds since the Unix epoch.
 */
final class LegacySqliteFixture
{
    public const string LEVEL_0003 = '0003';

    public const string LEVEL_0004 = '0004';

    public readonly string $path;

    /** The folder given to --uploads: the photos of uploads/<name>. */
    public readonly string $uploads;

    private readonly PDO $pdo;

    private function __construct(string $level)
    {
        $directory = sys_get_temp_dir().'/legacy-sqlite-'.bin2hex(random_bytes(8));
        mkdir($directory.'/uploads', 0777, true);

        $this->path = $directory.'/app.db';
        $this->uploads = $directory.'/uploads';
        $this->pdo = new PDO('sqlite:'.$this->path, options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

        $this->migrate($level);
    }

    public function __destruct()
    {
        $directory = dirname($this->path);
        foreach ([...(glob($this->uploads.'/*') ?: []), ...(glob($directory.'/*') ?: [])] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        @rmdir($this->uploads);
        @rmdir($directory);
    }

    /**
     * A base migrated up to the Drizzle migration $level included (0003 or 0004).
     */
    public static function atLevel(string $level = self::LEVEL_0004): self
    {
        return new self($level);
    }

    /**
     * Runs a statement of the test's own, a table rebuilt without a column for instance.
     */
    public function execute(string $sql): self
    {
        $this->pdo->exec($sql);

        return $this;
    }

    /**
     * Turns the base to the WAL journal of the live base of Next (bytes 18-19 of the header at
     * 2), through a connection of its own closed at once: SQLite then checkpoints and deletes
     * the -wal and -shm files, so that any such file seen later was created by the reader.
     */
    public function inWalMode(): self
    {
        $pdo = new PDO('sqlite:'.$this->path, options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->exec('pragma journal_mode=wal');
        unset($pdo);

        if (file_exists($this->path.'-wal') || file_exists($this->path.'-shm')) {
            throw new RuntimeException('The -wal and -shm files of the fixture remain after the switch to WAL.');
        }

        return $this;
    }

    /**
     * @return list<string> the tables of sqlite_master, internal tables of SQLite left out
     */
    public function tables(): array
    {
        /** @var list<string> $tables */
        $tables = $this->pdo
            ->query("select name from sqlite_master where type = 'table' and name not like 'sqlite_%' order by name")
            ?->fetchAll(PDO::FETCH_COLUMN) ?: [];

        return $tables;
    }

    public function player(int $id, string $name, int $createdAtMs): self
    {
        return $this->insert('players', ['id' => $id, 'name' => $name, 'created_at' => $createdAtMs]);
    }

    public function alias(int $id, int $playerId, string $alias): self
    {
        return $this->insert('player_aliases', ['id' => $id, 'player_id' => $playerId, 'alias' => $alias]);
    }

    /**
     * A round, its 18 holes, its lines in the order given and their scores.
     *
     * @param  list<array{par: int, strokeIndex: int, distance: int|null}>  $holes  hole 1 first
     * @param  list<array{int, list<int>}>  $lines  [SQLite player id, 18 strokes], position 0 first
     */
    public function round(int $id, string $courseName, string $tees, string $playedOn, int $createdAtMs, array $holes, array $lines, ?string $photoPath = null): self
    {
        $this->insert('rounds', [
            'id' => $id,
            'course_name' => $courseName,
            'tees' => $tees,
            'played_on' => $playedOn,
            'created_at' => $createdAtMs,
            'photo_path' => $photoPath,
        ]);

        foreach ($holes as $index => $hole) {
            $this->insert('round_holes', [
                'round_id' => $id,
                'hole' => $index + 1,
                'par' => $hole['par'],
                'stroke_index' => $hole['strokeIndex'],
                'distance' => $hole['distance'],
            ]);
        }

        foreach ($lines as $position => [$playerId, $strokes]) {
            $this->insert('round_players', ['round_id' => $id, 'player_id' => $playerId, 'position' => $position]);
            foreach ($strokes as $index => $stroke) {
                $this->insert('scores', ['round_id' => $id, 'player_id' => $playerId, 'hole' => $index + 1, 'strokes' => $stroke]);
            }
        }

        return $this;
    }

    /**
     * A row of player_round_stats as Next computed it.
     */
    public function stats(int $roundId, int $playerId, int $gross, int $adjusted, int $differentialT, ?int $indexBeforeT, int $indexAfterT, bool $countsInCurrent): self
    {
        return $this->insert('player_round_stats', [
            'round_id' => $roundId,
            'player_id' => $playerId,
            'gross' => $gross,
            'adjusted' => $adjusted,
            'differential_t' => $differentialT,
            'index_before_t' => $indexBeforeT,
            'index_after_t' => $indexAfterT,
            'counts_in_current' => $countsInCurrent ? 1 : 0,
        ]);
    }

    /**
     * A row of course_ratings; the columns not given are those of a pending lookup.
     *
     * @param  array<string, int|string|null>  $columns
     */
    public function courseRating(int $id, string $courseName, string $tees, int $updatedAtMs, array $columns = []): self
    {
        return $this->insert('course_ratings', [
            'id' => $id,
            'course_name' => $courseName,
            'tees' => $tees,
            'status' => 'pending',
            'updated_at' => $updatedAtMs,
            ...$columns,
        ]);
    }

    /**
     * A row of imports; the columns not given are those of an import read and not saved yet.
     *
     * @param  array<string, int|string|null>  $columns
     */
    public function import(int $id, string $photoPath, int $createdAtMs, array $columns = []): self
    {
        return $this->insert('imports', [
            'id' => $id,
            'status' => 'ready',
            'photo_path' => $photoPath,
            'draft' => self::referenceDraftJson(),
            'created_at' => $createdAtMs,
            ...$columns,
        ]);
    }

    public function aiUsage(int $id, int $createdAtMs, string $month, string $model, int $inputTokens, int $outputTokens, int $costMicroUsd): self
    {
        return $this->insert('ai_usage', [
            'id' => $id,
            'created_at' => $createdAtMs,
            'month' => $month,
            'model' => $model,
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'cost_micro_usd' => $costMicroUsd,
        ]);
    }

    /**
     * A row of the job queue of Next, done.
     */
    public function job(int $id, string $type, int $createdAtMs): self
    {
        return $this->insert('jobs', [
            'id' => $id,
            'type' => $type,
            'payload' => '{}',
            'status' => 'done',
            'run_after' => $createdAtMs,
            'created_at' => $createdAtMs,
        ]);
    }

    /**
     * A copy of database/data/img3064.webp in the uploads folder, under $name.
     */
    public function webpPhoto(string $name): self
    {
        copy(base_path('database/data/img3064.webp'), $this->uploads.'/'.$name);

        return $this;
    }

    /**
     * A small JPEG drawn by GD in the uploads folder, under $name.
     */
    public function jpegPhoto(string $name): self
    {
        $image = imagecreatetruecolor(40, 30);
        if ($image === false) {
            throw new RuntimeException('GD could not create the JPEG of the fixture.');
        }
        imagefill($image, 0, 0, (int) imagecolorallocate($image, 30, 120, 60));
        imagejpeg($image, $this->uploads.'/'.$name);

        return $this;
    }

    /**
     * Two players, ids 1 and 2, who play the reference card IMG_3064 on 2026-10-17 (round 1,
     * created at 1792251843742 ms, its photo at $photoPath), with the statistics Next computed
     * for their first round: 72 / 72 / 10 / none / 10 / yes, then 88 / 88 / 170 / none / 170 / yes.
     */
    public static function referenceRound(string $first = 'Gab', string $second = 'Manu', ?string $photoPath = null): self
    {
        $card = referenceCardData(null, null);

        return self::atLevel()
            ->player(1, $first, 1791400000000)
            ->player(2, $second, 1791500000000)
            ->round(1, 'BETHPAGE BLACK', 'Red', '2026-10-17', 1792251843742, $card['holes'], [
                [1, $card['lines'][0]['strokes']],
                [2, $card['lines'][1]['strokes']],
            ], $photoPath)
            ->stats(1, 1, 72, 72, 10, null, 10, true)
            ->stats(1, 2, 88, 88, 170, null, 170, true);
    }

    /**
     * The base of the spec « Comptes identiques »: 3 players, 7 aliases, 2 rounds of two
     * players (36 holes, 4 lines, 72 scores) with the statistics Next computed, 2 ratings, 2
     * imports, 3 rows of ai_usage and 5 jobs; the photos of the round and of the imports in the
     * uploads folder.
     *
     * Round 1: the reference card IMG_3064, BETHPAGE BLACK / Red played on 2026-10-17, created
     * at 1792251843742 ms, Gab 72 and Manu 88, photo uploads/3f2a.webp shared with import 1.
     * Round 2: the same course on 2026-10-10, created at 1791650000000 ms, Gab +17 and Sean +12.
     */
    public static function countsSet(): self
    {
        $card = referenceCardData(null, null);

        return self::atLevel()
            ->player(1, 'Gab', 1791500000000)
            ->player(2, 'Manu', 1791500000500)
            ->player(3, 'Sean', 1791600000000)
            ->alias(1, 1, 'GAB')
            ->alias(2, 1, 'GABRIEL')
            ->alias(3, 2, 'MANU')
            ->alias(4, 2, 'EMMANUEL')
            ->alias(5, 2, 'MANUEL')
            ->alias(6, 3, 'SEAN')
            ->alias(7, 3, 'SEANP')
            ->round(1, 'BETHPAGE BLACK', 'Red', '2026-10-17', 1792251843742, $card['holes'], [
                [1, $card['lines'][0]['strokes']],
                [2, $card['lines'][1]['strokes']],
            ], 'uploads/3f2a.webp')
            ->round(2, 'BETHPAGE BLACK', 'Red', '2026-10-10', 1791650000000, $card['holes'], [
                [1, strokesOverPar(17)],
                [3, strokesOverPar(12)],
            ])
            ->stats(2, 1, 88, 88, 170, null, 170, true)
            ->stats(2, 3, 83, 83, 120, null, 120, true)
            ->stats(1, 1, 72, 72, 10, 170, 90, true)
            ->stats(1, 2, 88, 88, 170, null, 170, true)
            ->courseRating(1, 'BETHPAGE BLACK', 'Red', 1792624190600)
            ->courseRating(2, 'PAYNES VALLEY', 'Blue', 1792624190600, [
                'status' => 'choose_course',
                'candidates' => '[{"id":41,"name":"Payne\'s Valley","location":"Missouri","par":71},{"id":42,"name":"Paynes Valley Short","location":"Missouri","par":27}]',
            ])
            ->import(1, 'uploads/3f2a.webp', 1792251800000, ['round_id' => 1])
            ->import(2, 'uploads/9b7c.webp', 1792251900000, ['status' => 'failed', 'draft' => null])
            ->aiUsage(1, 1789000000000, '2026-09', 'claude-opus-5-5', 2500, 900, 21000)
            ->aiUsage(2, 1789100000000, '2026-09', 'claude-opus-5-5', 2800, 1000, 23400)
            ->aiUsage(3, 1792251810000, '2026-10', 'claude-opus-5-5', 3000, 1200, 36000)
            ->job(1, 'ocr-scorecard', 1792251800000)
            ->job(2, 'ocr-scorecard', 1792251900000)
            ->job(3, 'course-rating-lookup', 1792251843742)
            ->job(4, 'course-rating-lookup', 1792252000000)
            ->job(5, 'course-rating-lookup', 1792253000000)
            ->webpPhoto('3f2a.webp')
            ->webpPhoto('9b7c.webp');
    }

    /**
     * The draft of the reference card as Next keeps it in imports.draft.
     */
    public static function referenceDraftJson(): string
    {
        return (string) file_get_contents(base_path('tests/fixtures/scorecards/gspro-img3064.draft.json'));
    }

    /**
     * @param  array<string, int|string|null>  $row
     */
    private function insert(string $table, array $row): self
    {
        $columns = array_keys($row);
        $statement = $this->pdo->prepare(sprintf(
            'insert into %s (%s) values (%s)',
            $table,
            implode(', ', $columns),
            implode(', ', array_fill(0, count($columns), '?')),
        ));
        $statement->execute(array_values($row));

        return $this;
    }

    /**
     * Applies each migration up to $level, split on the breakpoints as Drizzle does.
     */
    private function migrate(string $level): void
    {
        $files = glob(base_path('tests/fixtures/legacy-sqlite/drizzle/*.sql')) ?: [];
        sort($files);

        foreach ($files as $file) {
            if (strcmp(substr(basename($file), 0, 4), $level) > 0) {
                break;
            }

            foreach (explode('--> statement-breakpoint', (string) file_get_contents($file)) as $statement) {
                if (trim($statement) !== '') {
                    $this->pdo->exec($statement);
                }
            }
        }
    }
}
