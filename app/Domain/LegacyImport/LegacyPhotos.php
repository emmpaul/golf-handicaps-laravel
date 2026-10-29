<?php

namespace App\Domain\LegacyImport;

use App\Domain\Imports\Exceptions\UnreadablePhoto;
use App\Domain\Imports\WebpPhoto;
use App\Domain\LegacyImport\Exceptions\LegacyImportFailed;
use Exception;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

/**
 * The photos of Next (design D9): each path uploads/<name> of a round or an import is read in
 * the --uploads folder, never anywhere else, and goes to imports/<name>.webp on the photos disk,
 * once when a round and its import share it. A WebP is copied byte for byte, any other image
 * converted. Every file written is noted, so that a failed import gives the disk back as it was.
 */
final class LegacyPhotos
{
    public const string DISK = 'photos';

    private const string NAME = '/^uploads\/([A-Za-z0-9._-]+)$/';

    /**
     * What each path written had before: its bytes, or null when it did not exist.
     *
     * @var array<string, string|null>
     */
    private array $previous = [];

    /**
     * @param  list<string>  $paths  the distinct paths referenced, sorted
     */
    private function __construct(private readonly array $paths, private readonly ?string $uploads) {}

    /**
     * Checks every photo the base references before any write, each check listing all its
     * faults: --uploads given, each path of the form uploads/<name>, each file present.
     *
     * @throws LegacyImportFailed
     */
    public static function check(LegacyDatabase $legacy, ?string $uploads): self
    {
        $paths = array_values(array_unique([
            ...self::referenced($legacy, 'rounds'),
            ...self::referenced($legacy, 'imports'),
        ]));
        sort($paths);

        if ($paths === []) {
            return new self([], $uploads);
        }

        if ($uploads === null) {
            throw new LegacyImportFailed('Option --uploads requise : '.count($paths).' photos référencées.');
        }

        $refused = array_values(array_filter($paths, fn (string $path): bool => self::name($path) === null));
        if ($refused !== []) {
            $lines = array_map(fn (string $path): string => "Chemin de photo refusé : {$path}", $refused);
            throw new LegacyImportFailed($lines[0], array_slice($lines, 1));
        }

        $missing = array_values(array_filter($paths, function (string $path) use ($uploads): bool {
            $file = $uploads.'/'.self::name($path);

            return ! is_file($file) || ! is_readable($file);
        }));
        if ($missing !== []) {
            throw new LegacyImportFailed('Photos manquantes :', $missing);
        }

        self::checkDestinations($paths);

        return new self($paths, $uploads);
    }

    /**
     * Two sources that would give the same file (uploads/a.webp and uploads/a.jpg →
     * imports/a.webp) are refused: the second copy would overwrite the first (design D9).
     *
     * @param  list<string>  $paths  the distinct paths referenced, sorted
     *
     * @throws LegacyImportFailed
     */
    private static function checkDestinations(array $paths): void
    {
        $sources = [];
        foreach ($paths as $path) {
            $sources[(string) self::destination($path)][] = $path;
        }

        $collisions = [];
        foreach ($sources as $destination => $group) {
            if (count($group) > 1) {
                $collisions[] = implode(' et ', $group)." → {$destination}";
            }
        }

        if ($collisions !== []) {
            throw new LegacyImportFailed('Photos de même destination :', $collisions);
        }
    }

    /**
     * The path on the photos disk of a photo of Next: imports/<name without extension>.webp.
     */
    public static function destination(?string $path): ?string
    {
        return $path === null ? null : 'imports/'.pathinfo($path, PATHINFO_FILENAME).'.webp';
    }

    /**
     * Reads every photo and, unless $dryRun, writes it on the photos disk.
     *
     * @return int the photos read
     *
     * @throws LegacyImportFailed when a photo cannot be decoded or written
     */
    public function copy(bool $dryRun): int
    {
        foreach ($this->paths as $path) {
            $destination = (string) self::destination($path);
            try {
                $this->copyOne($path, $destination, $dryRun);
            } catch (LegacyImportFailed $failure) {
                throw $failure;
            } catch (Exception $exception) {
                // An error of Imagick other than UnreadablePhoto, or of the filesystem.
                throw new LegacyImportFailed("Échec de la copie des photos : {$path} → {$destination} : {$exception->getMessage()}");
            }
        }

        return count($this->paths);
    }

    /**
     * @throws LegacyImportFailed when the photo cannot be decoded or written
     * @throws Exception an error of Imagick other than UnreadablePhoto, or of a disk that throws
     */
    private function copyOne(string $path, string $destination, bool $dryRun): void
    {
        $bytes = $this->webp($path);
        if ($dryRun) {
            return;
        }

        if (! array_key_exists($destination, $this->previous)) {
            $this->previous[$destination] = $this->disk()->exists($destination) ? $this->disk()->get($destination) : null;
        }
        if (! $this->disk()->put($destination, $bytes)) {
            throw new LegacyImportFailed("Échec de la copie des photos : {$path} → {$destination}");
        }
    }

    /**
     * Gives the disk back as it was before copy(): each file written is deleted, or given back
     * the bytes it had.
     */
    public function undo(): void
    {
        foreach ($this->previous as $destination => $bytes) {
            if ($bytes === null) {
                $this->disk()->delete($destination);
            } else {
                $this->disk()->put($destination, $bytes);
            }
        }

        $this->previous = [];
    }

    /**
     * The files of the --uploads folder that no row references.
     */
    public function unreferencedFiles(): int
    {
        if ($this->uploads === null || ! is_dir($this->uploads)) {
            return 0;
        }

        $referenced = array_map(fn (string $path): ?string => self::name($path), $this->paths);
        $files = array_filter(scandir($this->uploads) ?: [], fn (string $file): bool => is_file($this->uploads.'/'.$file));

        return count(array_diff($files, $referenced));
    }

    /**
     * The photos disk paths of the imported base.
     *
     * @return list<string>
     */
    public function destinations(): array
    {
        return array_map(fn (string $path): string => (string) self::destination($path), $this->paths);
    }

    /**
     * The WebP bytes of a photo: a WebP as is, any other image converted.
     *
     * @throws LegacyImportFailed
     */
    private function webp(string $path): string
    {
        $file = $this->uploads.'/'.self::name($path);
        $bytes = (string) file_get_contents($file);
        if (substr($bytes, 0, 4) === 'RIFF' && substr($bytes, 8, 4) === 'WEBP') {
            return $bytes;
        }

        try {
            return WebpPhoto::fromPath($file);
        } catch (UnreadablePhoto) {
            throw new LegacyImportFailed("Photo illisible : {$path}");
        }
    }

    /**
     * The name of the file of uploads/<name>, null for any other path.
     */
    private static function name(string $path): ?string
    {
        if (preg_match(self::NAME, $path, $match) !== 1 || in_array($match[1], ['.', '..'], true)) {
            return null;
        }

        return $match[1];
    }

    /**
     * @return list<string>
     */
    private static function referenced(LegacyDatabase $legacy, string $table): array
    {
        return array_values(array_filter(
            $legacy->connection()->table($table)->whereNotNull('photo_path')->pluck('photo_path')->all(),
            is_string(...),
        ));
    }

    private function disk(): Filesystem
    {
        return Storage::disk(self::DISK);
    }
}
