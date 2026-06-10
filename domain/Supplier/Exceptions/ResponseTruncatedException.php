<?php

declare(strict_types=1);

namespace Domain\Supplier\Exceptions;

use RuntimeException;

/**
 * The model hit its output token cap mid-response. The caller should retry the
 * same content in smaller pieces (e.g. halve the page chunk).
 */
class ResponseTruncatedException extends RuntimeException {}
