<?php

declare(strict_types=1);

if (! function_exists('minimalRegistryProfilePayload')) {
    /**
     * Minimal valid nested personal_data payload (individual, no children).
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    function minimalRegistryProfilePayload(array $overrides = []): array
    {
        return array_merge([
            'type' => 'individual',
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            // An anagrafica must carry a phone number at creation (user
            // directive 2026-09-07): a payload without one is no longer a
            // valid create, so the minimal one holds it.
            'contacts' => [['type' => 'phone', 'value' => '+39 02 1112223', 'is_primary' => true]],
        ], $overrides);
    }
}
