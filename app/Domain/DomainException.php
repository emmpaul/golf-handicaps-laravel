<?php

namespace App\Domain;

use RuntimeException;

/**
 * A business refusal whose message is written for the person using the application.
 *
 * bootstrap/app.php renders every subclass the same way: an error toast on the previous page,
 * or 422 { "error": message } for a JSON request outside Inertia.
 */
abstract class DomainException extends RuntimeException {}
