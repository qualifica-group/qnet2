<?php

namespace App\Services\Registries;

use App\Models\Registry;

/**
 * Keeps the registry's referent roles consistent. The "Referente principale"
 * (spec 0330 D-3/D-4, INV-1) is the preferred referent when still linked, else
 * the first linked one (lowest pivot id), else null when the registry has no
 * referents. The "Referente secondario" (spec 0408 D-2, INV-2) is never elected:
 * it stays only while linked and different from the principale, else null.
 * Single implementation shared by RegistryService, ReferentService and the merges.
 */
class PrimaryReferentElector
{
    public function elect(Registry $registry, ?int $preferredId): void
    {
        $linkedIds = $registry->referents()->pluck('referents.id')->map(static fn ($id): int => (int) $id)->all();

        $electedId = $preferredId !== null && in_array($preferredId, $linkedIds, true)
            ? $preferredId
            : ($linkedIds[0] ?? null);

        $secondaryId = $registry->secondary_referent_id !== null ? (int) $registry->secondary_referent_id : null;
        $keptSecondaryId = $secondaryId !== null && $secondaryId !== $electedId && in_array($secondaryId, $linkedIds, true)
            ? $secondaryId
            : null;

        if ($registry->primary_referent_id !== $electedId || $secondaryId !== $keptSecondaryId) {
            $registry->forceFill(['primary_referent_id' => $electedId, 'secondary_referent_id' => $keptSecondaryId])->save();
        }
    }
}
