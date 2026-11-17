<?php

declare(strict_types=1);

namespace ShopSavvy\SDK\Exceptions;

use Exception;
use Throwable;

/**
 * Base exception for ShopSavvy API errors.
 *
 * API errors carry the API's structured detail. Branch on the exception class or on
 * `$errorCode`, not on the HTTP status: ERR_RATE_LIMIT and ERR_CREDITS_EXHAUSTED both
 * arrive as HTTP 429. (`getCode()` stays the integer HTTP status, as before.)
 */
class ShopSavvyException extends Exception
{
    /**
     * @param string|null $errorCode `error_detail.code`, e.g. "ERR_RATE_LIMIT"; null when the server sent none
     * @param string|null $action One-line fix from `error_detail.action`
     * @param string|null $docsUrl Link to the documentation for this code
     * @param string|null $requestId `meta.request_id`; quote it in support requests
     * @param int|null $retryAfterSeconds Seconds from the `Retry-After` header; only meaningful for ERR_RATE_LIMIT
     */
    public function __construct(
        string $message = '',
        int $code = 0,
        ?Throwable $previous = null,
        public readonly ?string $errorCode = null,
        public readonly ?string $action = null,
        public readonly ?string $docsUrl = null,
        public readonly ?string $requestId = null,
        public readonly ?int $retryAfterSeconds = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    /** HTTP status code (0 for failures with no response). */
    public function getStatusCode(): int
    {
        return $this->getCode();
    }
}
