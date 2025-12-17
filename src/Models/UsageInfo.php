<?php

declare(strict_types=1);

namespace ShopSavvy\SDK\Models;

/**
 * Current billing period details
 */
class UsagePeriod
{
    public function __construct(
        public readonly string $startDate,
        public readonly string $endDate,
        public readonly int $creditsUsed,
        public readonly int $creditsLimit,
        public readonly int $creditsRemaining,
        public readonly int $requestsMade
    ) {
    }

    /**
     * Create UsagePeriod from array data
     *
     * @param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        return new self(
            $data['start_date'],
            $data['end_date'],
            $data['credits_used'],
            $data['credits_limit'],
            $data['credits_remaining'],
            $data['requests_made']
        );
    }
}

/**
 * API usage information model
 */
class UsageInfo
{
    public function __construct(
        public readonly UsagePeriod $currentPeriod,
        public readonly float $usagePercentage
    ) {
    }

    /**
     * Create UsageInfo from array data
     *
     * @param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        return new self(
            UsagePeriod::fromArray($data['current_period']),
            $data['usage_percentage']
        );
    }

    // Backward-compatible aliases

    /**
     * @deprecated Use currentPeriod->creditsUsed instead
     */
    public function getCreditsUsed(): int
    {
        return $this->currentPeriod->creditsUsed;
    }

    /**
     * @deprecated Use currentPeriod->creditsRemaining instead
     */
    public function getCreditsRemaining(): int
    {
        return $this->currentPeriod->creditsRemaining;
    }

    /**
     * @deprecated Use currentPeriod->creditsLimit instead
     */
    public function getCreditsTotal(): int
    {
        return $this->currentPeriod->creditsLimit;
    }

    /**
     * @deprecated Use currentPeriod->startDate instead
     */
    public function getBillingPeriodStart(): string
    {
        return $this->currentPeriod->startDate;
    }

    /**
     * @deprecated Use currentPeriod->endDate instead
     */
    public function getBillingPeriodEnd(): string
    {
        return $this->currentPeriod->endDate;
    }
}
