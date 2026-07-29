<?php

declare(strict_types=1);

namespace App\DataObjects\CommissionConfigurations;

final readonly class UpdateCommissionConfigurationData
{
    /** @param array<string, mixed> $attributes */
    public function __construct(public array $attributes) {}

    /** @param array<string, mixed> $data */
    public static function fromValidated(array $data): self
    {
        return new self($data);
    }
}
