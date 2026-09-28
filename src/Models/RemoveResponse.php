<?php

declare(strict_types=1);

namespace ShopSavvy\SDK\Models;

/**
 * Response model for removal operations
 */
class RemoveResponse
{
    public function __construct(
        public readonly bool $removed
    ) {
    }

    /**
     * Create RemoveResponse from array data
     *
     * @param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        return new self(
            $data['removed']
        );
    }
}
