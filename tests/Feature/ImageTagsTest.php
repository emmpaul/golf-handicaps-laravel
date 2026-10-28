<?php

/*
 * Mutations that turn this test red: drop the comparison of the tag with version.txt from
 * make image-tags (the v0.2.0 tag publishes instead of failing), or accept any other ref as
 * main (refs/heads/feature prints tags instead of failing), or drop the full-sha check (a
 * short sha becomes an image tag).
 */

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

const IMAGE_TAGS_SHA = '4794e1b0c0ffee00000000000000000000000000';

/**
 * Runs make image-tags against a temporary version.txt holding the given version.
 *
 * @return array{exit: int, stdout: string, stderr: string}
 */
function runImageTags(string $ref, string $version, string $sha = IMAGE_TAGS_SHA): array
{
    $directory = sys_get_temp_dir().'/make-image-tags-'.bin2hex(random_bytes(6));
    File::ensureDirectoryExists($directory);
    File::put($directory.'/version.txt', $version."\n");

    try {
        $result = Process::path(base_path())->run([
            'make', '--no-print-directory', 'image-tags',
            'REF='.$ref, 'SHA='.$sha, 'VERSION_FILE='.$directory.'/version.txt',
        ]);

        return [
            'exit' => $result->exitCode() ?? -1,
            'stdout' => $result->output(),
            'stderr' => $result->errorOutput(),
        ];
    } finally {
        File::deleteDirectory($directory);
    }
}

test('a push on main is tagged main and the commit sha', function () {
    $result = runImageTags('refs/heads/main', '0.1.0');

    expect($result['exit'])->toBe(0)
        ->and($result['stdout'])->toBe("main 4794e1b0c0ffee00000000000000000000000000\n")
        ->and($result['stderr'])->toBe('');
});

test('a version tag matching version.txt is tagged with the version, latest and the commit sha', function () {
    $result = runImageTags('refs/tags/v0.1.0', '0.1.0');

    expect($result['exit'])->toBe(0)
        ->and($result['stdout'])->toBe("v0.1.0 latest 4794e1b0c0ffee00000000000000000000000000\n")
        ->and($result['stderr'])->toBe('');
});

test('a version tag that differs from version.txt fails naming both versions', function () {
    $result = runImageTags('refs/tags/v0.2.0', '0.1.0');

    expect($result['exit'])->not->toBe(0)
        ->and($result['stdout'])->toBe('')
        ->and($result['stderr'])->toContain("Le tag v0.2.0 annonce la version 0.2.0, mais version.txt contient 0.1.0 : rien n'est publié.\n");
});

test('a ref other than main or a version tag fails without tags', function () {
    $result = runImageTags('refs/heads/feature', '0.1.0');

    expect($result['exit'])->not->toBe(0)
        ->and($result['stdout'])->toBe('')
        ->and($result['stderr'])->toContain("La ref refs/heads/feature ne publie pas d'image : seuls refs/heads/main et refs/tags/vX.Y.Z publient.\n");
});

test('a sha that is not a full commit sha fails without tags', function () {
    $result = runImageTags('refs/heads/main', '0.1.0', '4794e1b');

    expect($result['exit'])->not->toBe(0)
        ->and($result['stdout'])->toBe('')
        ->and($result['stderr'])->toContain("SHA doit être le sha complet du commit (40 caractères hexadécimaux), reçu : 4794e1b\n");
});
