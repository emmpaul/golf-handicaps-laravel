<?php

/*
|--------------------------------------------------------------------------
| Trusted Proxies
|--------------------------------------------------------------------------
|
| The proxies allowed to report the client IP through X-Forwarded-For, read
| from TRUSTED_PROXIES: addresses or networks separated by commas. Empty
| means no proxy is trusted. The wildcard "*" (or "**") is dropped: it
| would let any caller choose the IP the login limiter sees.
|
*/

$proxies = array_values(array_filter(
    array_map(trim(...), explode(',', (string) env('TRUSTED_PROXIES', ''))),
    fn (string $proxy): bool => ! in_array($proxy, ['', '*', '**'], true),
));

return [

    'proxies' => $proxies === [] ? null : $proxies,

];
