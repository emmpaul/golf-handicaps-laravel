<?php

/*
 * Spec visual-theme (visual-redesign), « Thème sombre seul »: « Couleur de la barre d'état »;
 * design D10, decision Q1; design DO30 for the status bar style of the installed app.
 *
 * Mutations that turn these tests red: a second theme-color kept in
 * resources/views/app.blade.php (the light one, #F4F7FB, with its media); color-scheme left at
 * « light dark »; the mobile-web-app-capable meta tag removed; the status bar style left at
 * « default » (dark text over the dark background).
 */

beforeEach(function () {
    $this->withPasscodeSession();
});

function homeDocument(): DOMXPath
{
    $html = test()->get('/')->assertOk()->getContent();
    $document = new DOMDocument;
    $document->loadHTML((string) $html, LIBXML_NOERROR);

    return new DOMXPath($document);
}

/**
 * @return list<string>
 */
function attributeValues(DOMXPath $xpath, string $query): array
{
    $values = [];
    foreach ($xpath->query($query) ?: [] as $attribute) {
        $values[] = (string) $attribute->nodeValue;
    }

    return $values;
}

test('the document is in french', function () {
    expect(attributeValues(homeDocument(), '/html/@lang'))->toBe(['fr']);
});

test('the viewport covers the whole screen, notch included', function () {
    $viewport = attributeValues(homeDocument(), '//meta[@name="viewport"]/@content');

    expect($viewport)->toHaveCount(1)
        ->and($viewport[0])->toContain('viewport-fit=cover');
});

test('one theme color, the background of the design, whatever the mode', function () {
    $xpath = homeDocument();

    expect(attributeValues($xpath, '//meta[@name="theme-color"]/@content'))->toBe(['#060709'])
        ->and(attributeValues($xpath, '//meta[@name="theme-color"]/@media'))->toBe([]);
});

test('the page links the web manifest and the apple touch icon', function () {
    $xpath = homeDocument();

    expect(attributeValues($xpath, '//link[@rel="manifest"]/@href'))->toBe(['/manifest.webmanifest'])
        ->and(attributeValues($xpath, '//link[@rel="apple-touch-icon"]/@href'))->toBe(['/apple-touch-icon.png']);
});

test('the page can be installed on the home screen', function () {
    $xpath = homeDocument();

    expect(attributeValues($xpath, '//meta[@name="mobile-web-app-capable"]/@content'))->toBe(['yes'])
        ->and(attributeValues($xpath, '//meta[@name="apple-mobile-web-app-capable"]/@content'))->toBe(['yes']);
});

test('the installed app draws a black status bar over the dark background', function () {
    expect(attributeValues(homeDocument(), '//meta[@name="apple-mobile-web-app-status-bar-style"]/@content'))
        ->toBe(['black']);
});

test('the page declares the dark scheme alone', function () {
    $xpath = homeDocument();

    expect(attributeValues($xpath, '//meta[@name="color-scheme"]/@content'))->toBe(['dark']);
});

test('the page sets no appearance cookie', function () {
    $this->withCookie('appearance', 'dark')
        ->get('/')
        ->assertOk()
        ->assertCookieMissing('appearance');
});
