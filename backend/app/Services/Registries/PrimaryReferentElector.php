<?php

namespace App\Services\Registries;

use App\Models\Registry;

/**
 * Keeps the registry's "Referente principale" consistent (spec 0330 D-3/D-4,
 * INV-1): it is the preferred referent when still linked, else the first
 * linked one (lowest pivot id), else null when the registry has no referents.
 * Single implementation shared by RegistryService and ReferentService.
 */
class PrimaryReferentElector
{
    public function elect(Registry $registry, ?int $preferredId): void
    {
        $linkedIds = $registry->referents()->pluck('referents.id')->map(static fn ($id): int => (int) $id)->all();

        $electedId = $preferredId !== null && in_array($preferredId, $linkedIds, true)
            ? $preferredId
            : ($linkedIds[0] ?? null);

        if ($registry->primary_referent_id !== $electedId) {
            $registry->forceFill(['primary_referent_id' => $electedId])->save();
        }
    }
}
