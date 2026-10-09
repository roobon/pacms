<?php

namespace App\Support\Http;

/**
 * The host name has no address (DNS failure). A broken link rather than a forbidden one.
 */
class UnresolvableHostException extends UnsafeUrlException {}
