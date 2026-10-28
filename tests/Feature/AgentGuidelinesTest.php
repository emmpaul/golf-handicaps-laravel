<?php

test('AGENTS.md prescribes no command that bypasses the Makefile', function () {
    $prescriptions = [
        'artisan test',
        'bin pint',
        'vendor/bin/pest',
        'vendor/bin/phpstan',
        'pnpm run build',
        'ask the user to run',
    ];

    expect(base_path('AGENTS.md'))->toBeFile();

    $lines = file(base_path('AGENTS.md'), FILE_IGNORE_NEW_LINES) ?: [];
    $offences = [];

    foreach ($lines as $index => $text) {
        foreach ($prescriptions as $prescription) {
            if (stripos($text, $prescription) !== false) {
                $offences[] = sprintf('AGENTS.md:%d [%s] %s', $index + 1, $prescription, trim($text));
            }
        }
    }

    expect($offences)->toBe([], "AGENTS.md bypasses the Makefile; fix .ai/guidelines/, then boost:update:\n".implode("\n", $offences));
});
