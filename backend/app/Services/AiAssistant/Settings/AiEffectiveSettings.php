<?php

declare(strict_types=1);

namespace App\Services\AiAssistant\Settings;

/**
 * The settings in force for an assistant turn (spec 0433): what the turn reads
 * instead of the .env. `routing` is already in the OpenRouter `provider` shape.
 */
final readonly class AiEffectiveSettings
{
    /**
     * @param  array{zdr: bool, data_collection: string, require_parameters: bool, sort: string}  $routing
     */
    public function __construct(
        public bool $enabled,
        public string $modelId,
        public string $instructions,
        public array $routing,
        public ?string $spendCapPersonUsd,
        public ?string $spendCapTotalUsd,
    ) {}
}
