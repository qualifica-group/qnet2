<?php

namespace App\Http\Resources;

use App\Http\Resources\Abstracts\ForSelectResource;
use App\Models\Registry;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * For-select projection of a Registry (GET /api/registries/for-select).
 *
 * Minimal by design (ADR 0011): label = name, no subtitle/avatar. `meta`
 * (spec 0040 BR-4 + A-5) carries the registry's default commercial/reporter
 * referents, its supervisor (directive 2026-07-29) and its ordered account
 * managers, feeding the Opportunity form's prefill — always present (each key
 * null / `managers: []` when the registry has none), unlike the top-level
 * optional keys ForSelectResource strips.
 *
 * @mixin Registry
 */
class RegistryForSelectResource extends ForSelectResource
{
    /**
     * @return array<string, mixed>
     */
    protected function forSelectItem(Request $request): array
    {
        return [
            'id' => $this->id,
            'label' => $this->name,
            'meta' => [
                'commercial' => $this->commercial !== null
                    ? ['id' => $this->commercial->id, 'name' => $this->commercial->name]
                    : null,
                'reporter' => $this->reporter !== null
                    ? ['id' => $this->reporter->id, 'name' => $this->reporter->name]
                    : null,
                'supervisor' => $this->supervisor !== null
                    ? ['id' => $this->supervisor->id, 'name' => $this->supervisor->name]
                    : null,
                'managers' => $this->managers
                    ->map(static fn (User $manager): array => [
                        'id' => $manager->id,
                        'name' => $manager->name,
                        'position' => $manager->pivot->position,
                    ])
                    ->all(),
            ],
        ];
    }
}
