<?php

/**
 * ContextBootException - Thrown when ContextEngine cannot produce a valid SirusContext.
 *
 * PROVISIONAL: This exception is a mirror of the canonical definition owned by
 * sparxstar-ouroboros-integrity. Replace this file with the Ouroboros package
 * import once that package ships. Do NOT redefine the class if Ouroboros is
 * already loaded.
 *
 * Hard rule: ContextBootException MUST NEVER be caught and swallowed.
 * If context cannot be established, the request MUST halt.
 *
 * @package Starisian\Sparxstar\Sirus
 */

declare(strict_types=1);

namespace Starisian\Sparxstar\Sirus\exceptions;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Signals that ContextEngine::current() failed to produce a valid SirusContext.
 *
 * Callers MUST NOT catch this exception silently. Any catch block that handles
 * this exception MUST re-throw it or terminate the request, ensuring downstream
 * layers never operate on undefined context state.
 */
class ContextBootException extends \RuntimeException
{
    /**
     * @param string $message  Human-readable description of the boot failure.
     * @param int    $code     Optional error code.
     * @param \Throwable|null $previous Prior exception chain, if any.
     */
    public function __construct(
        string $message = '[Sirus] ContextBootException: context could not be established.',
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}
