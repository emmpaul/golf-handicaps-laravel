<?php

/*
 * Mutations that turn this test red: remove the passcode-check call or its warning from the
 * Makefile (the empty cases lose their message), or let it write APP_PASSCODE= with a value
 * into .env (the .env content changes).
 */

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

/**
 * Runs the passcode check of make setup on a copy of the Makefile, next to the given .env.
 *
 * @return array{exit: int, stdout: string, stderr: string, env: string}
 */
function runPasscodeCheck(string $env): array
{
    $directory = sys_get_temp_dir().'/make-setup-passcode-'.bin2hex(random_bytes(6));
    File::ensureDirectoryExists($directory);
    File::copy(base_path('Makefile'), $directory.'/Makefile');
    File::put($directory.'/.env', $env);

    try {
        $result = Process::path($directory)->run(['make', '--no-print-directory', 'passcode-check']);

        return [
            'exit' => $result->exitCode() ?? -1,
            'stdout' => $result->output(),
            'stderr' => $result->errorOutput(),
            'env' => (string) file_get_contents($directory.'/.env'),
        ];
    } finally {
        File::deleteDirectory($directory);
    }
}

test('make setup calls the passcode check', function () {
    preg_match('/^setup:.*?(?=^\S)/ms', (string) file_get_contents(base_path('Makefile')), $setup);

    expect($setup[0] ?? '')->toContain("\t@\$(MAKE) --no-print-directory passcode-check\n");
});

test('the passcode check warns about an empty APP_PASSCODE without writing a value', function (string $env) {
    $result = runPasscodeCheck($env);

    expect($result['exit'])->toBe(0)
        ->and($result['stdout'])->toBe('')
        ->and($result['stderr'])->toBe("Attention : APP_PASSCODE est vide dans .env ; toute requête HTTP répondra 500 tant qu'il n'a pas de valeur.\n")
        ->and($result['env'])->toBe($env);
})->with([
    'empty' => "APP_KEY=base64:abc\nAPP_PASSCODE=\nAPP_LOCALE=fr\n",
    'empty quotes' => "APP_PASSCODE=\"\"\n",
    'missing' => "APP_KEY=base64:abc\n",
]);

test('the passcode check stays silent when APP_PASSCODE has a value', function (string $env) {
    $result = runPasscodeCheck($env);

    expect($result['exit'])->toBe(0)
        ->and($result['stdout'])->toBe('')
        ->and($result['stderr'])->toBe('')
        ->and($result['env'])->toBe($env);
})->with([
    'plain' => "APP_PASSCODE=birdie-2026\n",
    'quoted' => "APP_PASSCODE=\"birdie 2026\"\n",
]);
