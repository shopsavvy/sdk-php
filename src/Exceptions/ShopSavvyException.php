<?php

declare(strict_types=1);

namespace ShopSavvy\SDK\Exceptions;

use Exception;

/**
 * Base exception for ShopSavvy API errors
 */
class ShopSavvyException extends Exception
{
}

/**
 * Exception thrown when API key authentication fails
 */
class ShopSavvyAuthenticationException extends ShopSavvyException
{
}

/**
 * Exception thrown when a requested resource is not found
 */
class ShopSavvyNotFoundException extends ShopSavvyException
{
}

/**
 * Exception thrown when request parameters fail validation
 */
class ShopSavvyValidationException extends ShopSavvyException
{
}

/**
 * Exception thrown when API rate limits are exceeded
 */
class ShopSavvyRateLimitException extends ShopSavvyException
{
}

/**
 * Exception thrown when network errors occur
 */
class ShopSavvyNetworkException extends ShopSavvyException
{
}