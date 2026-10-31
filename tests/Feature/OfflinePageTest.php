<?php

/*
 * Spec offline-mode (offline-pwa), « Page jamais visitée hors ligne »: « Ouverture à froid sans
 * copie », and « Service worker servi sans session »: « Écran hors ligne »; design D5.
 *
 * public/offline.html is served by Caddy without PHP and kept by the service worker at install:
 * the test reads the file itself. Its colours are the values of the bg and ink tokens.
 *
 * Mutations that turn this test red: the link pointed at "/" instead of "/import"; lang="en";
 * a stylesheet loaded from https://fonts.googleapis.com; the background left at #000000; the
 * column left at 480px or the side padding at 20px instead of the column and gutter tokens.
 */

function offlinePage(): string
{
    return (string) file_get_contents(public_path('offline.html'));
}

test('the offline screen is French and leads to the import screen', function () {
    $html = offlinePage();

    expect($html)
        ->toContain('<html lang="fr">')
        ->toContain('<title>Hors ligne</title>')
        ->toContain('<h1>Hors ligne</h1>')
        ->toContain('<p>Cette page n\'a pas encore été ouverte sur ce téléphone.</p>')
        ->toContain('<a href="/import">Importer une carte</a>');
});

test('the offline screen loads nothing from another address', function () {
    expect(offlinePage())->not->toMatch('~(?:https?:)?//~i');
});

test('the offline screen uses the colours of the bg and ink tokens', function () {
    expect(offlinePage())
        ->toMatch('~background(?:-color)?:\s*#060709;~i')
        ->toMatch('~\bcolor:\s*#f4f5f7;~i');
});

test('the offline screen uses the column and gutter tokens', function () {
    expect(offlinePage())
        ->toMatch('~body\s*\{[^}]*max-width:\s*390px;~')
        ->toMatch('~body\s*\{[^}]*padding:\s*max\(54px, env\(safe-area-inset-top\)\) 16px 32px;~');
});
