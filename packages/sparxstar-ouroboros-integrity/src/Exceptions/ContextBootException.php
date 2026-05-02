<?php

declare(strict_types=1);

namespace Starisian\Sparxstar\Infrastructure\Exceptions;

/**
 * Thrown when ContextEngine cannot produce a valid SirusContext.
 *
 * MUST NEVER be caught and swallowed. Any catch block MUST re-throw
 * or terminate the request.
 */
class ContextBootException extends \RuntimeException {}
