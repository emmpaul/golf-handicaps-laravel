<?php

/*
 * Mutation that turns these tests red: swap the two theme-color values in
 * resources/views/app.blade.php (#0A111F for light, #F4F7FB for dark), or
 * remove the mobile-web-app-capable meta tag.
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

test('the theme color follows the light and dark modes', function () {
    $xpath = homeDocument();

    expect(attributeValues($xpath, '//meta[@name="theme-color"][@media="(prefers-color-scheme: light)"]/@content'))
        ->toBe(['#F4F7FB'])
        ->and(attributeValues($xpath, '//meta[@name="theme-color"][@media="(prefers-color-scheme: dark)"]/@content'))
        ->toBe(['#0A111F']);
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

test('the dark mode is left to the system', function () {
    $xpath = homeDocument();

    expect(attributeValues($xpath, '/html/@class'))->not->toContain('dark')
        ->and(attributeValues($xpath, '//meta[@name="color-scheme"]/@content'))->toBe(['light dark']);
});

test('the page sets no appearance cookie', function () {
    $this->withCookie('appearance', 'dark')
        ->get('/')
        ->assertOk()
        ->assertCookieMissing('appearance');
});
