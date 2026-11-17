<?php

declare(strict_types=1);

namespace ShopSavvy\SDK\Exceptions;

/**
 * ERR_CREDITS_EXHAUSTED (HTTP 429): do NOT retry. Credits come back when the billing period
 * resets or the plan is upgraded. Deliberately not a ShopSavvyRateLimitException.
 */
class ShopSavvyCreditsExhaustedException extends ShopSavvyException
{
}
