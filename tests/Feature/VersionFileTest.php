<?php

function versionFileContent(): string
{
    return (string) @file_get_contents(base_path('version.txt'));
}

test('version.txt holds a bare MAJOR.MINOR.PATCH version', function () {
    $content = versionFileContent();

    expect(preg_match('/^\d+\.\d+\.\d+\n?$/', $content))
        ->toBe(1, sprintf('version.txt contains "%s", expected MAJOR.MINOR.PATCH alone', $content));
});

test('every released version has its CHANGELOG.md section', function () {
    $version = trim(versionFileContent());
    $changelog = (string) @file_get_contents(base_path('CHANGELOG.md'));

    // 0.0.0 means nothing has been released yet: only "Non publié" exists.
    $missing = $version === '0.0.0' || str_contains($changelog, "## [{$version}] - ") ? [] : [$version];

    expect($missing)->toBe([], sprintf('CHANGELOG.md has no "## [%s] - AAAA-MM-JJ" section for version.txt', $version));
});
