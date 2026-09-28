<?php

declare(strict_types=1);

namespace ShopSavvy\SDK\Models;

/**
 * Response from batch removal
 *
 * @deprecated Not used by the client and not a shape the API sends or reads:
 *   removeProductFromSchedule() returns RemoveResponse (`success`, `message`, `meta`).
 */
class RemoveBatchResponse
{
    public function __construct(
        public readonly string $identifier,
        public readonly bool $removed
    ) {
    }

    /**
     * Create RemoveBatchResponse from array data
     *
     * @param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        return new self(
            $data['identifier'],
            $data['removed']
        );
    }
}
