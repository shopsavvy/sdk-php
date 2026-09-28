<?php

declare(strict_types=1);

namespace ShopSavvy\SDK\Models;

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
