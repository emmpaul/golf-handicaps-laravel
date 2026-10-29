<?php

namespace App\Integrations\Sgt;

use RuntimeException;

/**
 * Simulator Golf Tour did not give its course list or a scorecard: network, delay, HTTP status
 * or a page in another format (design D3). The lookup job lets it surface: the queue retries
 * once, then the course ends « Recherche échouée ».
 */
final class SgtUnavailable extends RuntimeException {}
