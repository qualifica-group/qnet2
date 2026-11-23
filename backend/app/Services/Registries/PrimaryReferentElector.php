<?php

namespace App\Services\Registries;

use App\Models\Registry;

/**
 * Keeps the registry's referent roles consistent. The "Referente principale"
 * (spec 0330 D-3/D-4, INV-1) is the preferred referent when still linked, else
 * the first linked one (lowest pivot id), else null when the registry has no
 * referents. The "Referente secondario" (spec 0408 D-2, INV-2) stays only while
 * linked and different from the principale; when it is empty it takes the first
 * just-added referent that is not the principale (spec 0408 D-9), else null.
 * Single implementation shared by RegistryService, ReferentService and the merges.
 */
class PrimaryReferentElector
{
    /**
     * @param  array<int, int>  $addedReferentIds  referents linked by this write, in submitted order:
     *                                             the candidates for an empty secondario (spec 0408 D-9)
     */
    public function elect(Registry $registry, ?int $preferredId, array $addedReferentIds = []): void
    {
        $linkedIds = $registry->referents()->pluck('referents.id')->map(static fn ($id): int => (int) $id)->all();

        $electedId = $preferredId !== null && in_array($preferredId, $linkedIds, true)
            ? $preferredId
            : ($linkedIds[0] ?? null);

        $secondaryId = $registry->secondary_referent_id !== null ? (int) $registry->secondary_referent_id : null;
        $keptSecondaryId = $secondaryId !== null && $secondaryId !== $electedId && in_array($secondaryId, $linkedIds, true)
            ? $secondaryId
            : null;
        $keptSecondaryId ??= $this->firstAddedOtherThan($addedReferentIds, $electedId, $linkedIds);

        if ($registry->primary_referent_id !== $electedId || $secondaryId !== $keptSecondaryId) {
            $registry->forceFill(['primary_referent_id' => $electedId, 'secondary_referent_id' => $keptSecondaryId])->save();
        }
    }

    /**
     * @param  array<int, int>  $addedReferentIds
     * @param  array<int, int>  $linkedIds
     */
    private function firstAddedOtherThan(array $addedReferentIds, ?int $primaryId, array $linkedIds): ?int
    {
        foreach ($addedReferentIds as $id) {
            if ($id !== $primaryId && in_array($id, $linkedIds, true)) {
                return $id;
            }
        }

        return null;
    }
}
