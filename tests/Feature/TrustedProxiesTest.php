<?php

/*
 * Mutation that turns these tests red: in config/trustedproxy.php, keep the wildcard by
 * returning env('TRUSTED_PROXIES') as is: TRUSTED_PROXIES=* then trusts every caller and the
 * client IP becomes 203.0.113.7.
 */

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::get('/test-client-ip', fn (Request $request) => $request->ip());
});

test('the client IP is read from X-Forwarded-For only behind a trusted proxy', function (string $trustedProxies, string $remoteAddress, string $clientIp) {
    $this->loadConfigUnder('trustedproxy', ['TRUSTED_PROXIES' => $trustedProxies]);

    $this->withServerVariables(['REMOTE_ADDR' => $remoteAddress])
        ->withHeader('X-Forwarded-For', '203.0.113.7')
        ->get('/test-client-ip')
        ->assertOk()
        ->assertContent($clientIp);
})->with([
    'trusted proxy' => ['10.0.0.0/8', '10.0.1.5', '203.0.113.7'],
    'unknown proxy' => ['10.0.0.0/8', '198.51.100.9', '198.51.100.9'],
    'empty' => ['', '10.0.1.5', '10.0.1.5'],
    'wildcard refused' => ['*', '198.51.100.9', '198.51.100.9'],
]);
