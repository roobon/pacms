<?php

namespace App\Cms\External;

use RuntimeException;

/**
 * A feed that cannot be read (not a feed, not valid, or not safe). The message is shown
 * to admins as the source's last error.
 */
class FeedException extends RuntimeException {}
