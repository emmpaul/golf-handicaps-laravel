<?php

/**
 * @return array<string, string> description of each public target, keyed by target name
 */
function makefileTargets(): array
{
    expect(base_path('Makefile'))->toBeFile();

    preg_match_all('/^([a-z-]+):(?!=)(.*)$/m', (string) file_get_contents(base_path('Makefile')), $matches, PREG_SET_ORDER);

    $targets = [];

    foreach ($matches as [, $name, $rest]) {
        $description = str_contains($rest, '## ') ? trim(substr($rest, strpos($rest, '## ') + 3)) : '';
        $targets[$name] = $description;
    }

    return $targets;
}

test('every public make target has a help description', function () {
    $undescribed = array_keys(array_filter(makefileTargets(), fn (string $description) => $description === ''));

    expect($undescribed)->toBe([]);
});

test('make help lists every documented target', function () {
    $required = [
        'setup', 'up', 'down', 'dev', 'dev-stop', 'browser', 'build', 'migrate', 'fresh', 'demo', 'openspec',
        'lint', 'format', 'analyse', 'duplicates', 'arch-test', 'test-backend', 'test-frontend',
        'test', 'update-shards', 'check', 'commitlint', 'changed-code', 'image', 'image-smoke', 'image-tags',
        'perf-probe',
    ];

    $missing = array_values(array_diff($required, array_keys(makefileTargets())));

    expect($missing)->toBe([]);
});
