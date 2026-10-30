<?php

/*
 * Spec visual-theme (optimize-performance), « Polices téléchargées une fois »; design D3.
 *
 * Reads the /login page as `make build` leaves it: the font preloads of the document, and the
 * @font-face rules of its inline styles and of the stylesheets it links from public/build.
 *
 * Mutations that turn these tests red: fonts set back to the two fontsource() of vite.config.ts
 * with subsets ['latin', 'latin-ext'] (ten preloads, a rule per subset, WOFF rules); a WOFF file
 * added next to a WOFF2 one in a rule; the preload of Geist Mono 600 removed or pointed at
 * another weight; the « Geist fallback » rule removed.
 */

/**
 * @return array{preloads: list<string>, css: string}
 */
function loginFontSources(): array
{
    $html = (string) test()->get('/login')->assertOk()->getContent();
    $document = new DOMDocument;
    $document->loadHTML($html, LIBXML_NOERROR);
    $xpath = new DOMXPath($document);

    $preloads = [];
    foreach ($xpath->query('//link[@rel="preload"][@as="font"]/@href') ?: [] as $href) {
        $preloads[] = (string) $href->nodeValue;
    }

    $css = '';
    foreach ($xpath->query('//style') ?: [] as $style) {
        $css .= $style->textContent."\n";
    }
    foreach ($xpath->query('//link[@rel="stylesheet"]/@href') ?: [] as $href) {
        $css .= file_get_contents(public_path((string) parse_url((string) $href->nodeValue, PHP_URL_PATH)))."\n";
    }

    return ['preloads' => $preloads, 'css' => $css];
}

/**
 * The @font-face rules of a stylesheet, each as its family, its weight and the URLs of its src.
 *
 * @return list<array{family: string, weight: string, src: string, urls: list<string>}>
 */
function fontFaces(string $css): array
{
    preg_match_all('/@font-face\s*\{([^}]*)\}/', $css, $blocks);

    $faces = [];
    foreach ($blocks[1] as $block) {
        preg_match('/font-family:\s*["\']?([^"\';]+)["\']?\s*;/', $block, $family);
        preg_match('/font-weight:\s*([^;]+);/', $block, $weight);
        preg_match('/src:\s*([^;]+);/', $block, $src);
        preg_match_all('/url\(\s*["\']?([^"\')]+)["\']?\s*\)/', $src[1] ?? '', $urls);
        $faces[] = [
            'family' => trim($family[1] ?? ''),
            'weight' => trim($weight[1] ?? ''),
            'src' => trim($src[1] ?? ''),
            'urls' => $urls[1],
        ];
    }

    return $faces;
}

/**
 * @param  list<string>  $urls
 * @return list<string>
 */
function fileNames(array $urls): array
{
    $names = array_map(fn (string $url): string => basename((string) parse_url($url, PHP_URL_PATH)), $urls);
    sort($names);

    return $names;
}

test('the page preloads five font files, all WOFF2', function () {
    $preloads = loginFontSources()['preloads'];

    expect($preloads)->toHaveCount(5)
        ->and(array_values(array_filter($preloads, fn (string $url): bool => ! str_ends_with($url, '.woff2'))))->toBe([]);
});

test('no @font-face rule names a WOFF file', function () {
    $urls = array_merge(...array_column(fontFaces(loginFontSources()['css']), 'urls'));

    expect($urls)->not->toBe([])
        ->and(array_values(array_filter($urls, fn (string $url): bool => str_ends_with($url, '.woff'))))->toBe([]);
});

test('one @font-face rule per weight of Geist and Geist Mono', function () {
    $served = array_values(array_filter(fontFaces(loginFontSources()['css']), fn (array $face): bool => $face['urls'] !== []));
    $variants = array_map(fn (array $face): string => $face['family'].' '.$face['weight'], $served);
    sort($variants);

    expect($variants)->toBe(['Geist 400', 'Geist 500', 'Geist 600', 'Geist Mono 500', 'Geist Mono 600']);
});

test('the preloaded files are exactly the files of the @font-face rules', function () {
    ['preloads' => $preloads, 'css' => $css] = loginFontSources();
    $ruleUrls = array_merge(...array_column(fontFaces($css), 'urls'));

    expect(fileNames($preloads))->toBe(fileNames($ruleUrls))
        ->and(fileNames($preloads))->toHaveCount(5);
});

test('a Geist fallback rule draws a local font with adjusted metrics', function () {
    $fallbacks = array_values(array_filter(fontFaces(loginFontSources()['css']), fn (array $face): bool => $face['family'] === 'Geist fallback'));

    expect($fallbacks)->toHaveCount(1)
        ->and($fallbacks[0]['src'])->toStartWith('local(')
        ->and($fallbacks[0]['urls'])->toBe([]);
});
