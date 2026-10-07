<?php

namespace App\Support\Http;

use RuntimeException;

/**
 * Raised by SafeHttpClient; the message is safe to show to editors.
 */
class UnsafeUrlException extends RuntimeException {}
