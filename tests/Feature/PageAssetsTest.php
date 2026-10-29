<?php

/*
 * Mutations that turn these tests red: add
 * <link rel="stylesheet" href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600">
 * or, as the Golf App.dc.html of the redesign does,
 * <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Geist:wght@400;500;600&display=swap">
 * to resources/views/app.blade.php.
 */

beforeEach(function () {
    $this->withPasscodeSession();
});

/**
 * @return list<string>
 */
function referencedUrls(string $html): array
{
    $document = new DOMDocument;
    $document->loadHTML($html, LIBXML_NOERROR);
    $xpath = new DOMXPath($document);

    $urls = [];
    foreach ($xpath->query('//@href | //@src') ?: [] as $attribute) {
        $urls[] = $attribute->nodeValue;
    }

    return $urls;
}

test('the home page references no resource outside the application', function () {
    $response = $this->get('/');

    $response->assertOk();
    $appHost = parse_url(config('app.url'), PHP_URL_HOST);
    $external = array_values(array_filter(
        referencedUrls($response->getContent()),
        fn (string $url): bool => ! in_array(parse_url($url, PHP_URL_HOST), [null, $appHost], true),
    ));
    expect($external)->toBe([]);
});

test('the home page names no third-party font service', function () {
    $response = $this->get('/');

    $response->assertOk()
        ->assertDontSee('bunny.net')
        ->assertDontSee('fonts.googleapis.com')
        ->assertDontSee('fonts.gstatic.com');
});
