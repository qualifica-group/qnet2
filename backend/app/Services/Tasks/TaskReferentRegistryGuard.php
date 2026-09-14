<?php

declare(strict_types=1);

namespace App\Services\Tasks;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * AC-014 of spec 0101: a Referente must belong, through the
 * `referent_registry` pivot, to the Anagrafica the Task points at.
 *
 * Invoked by App\Services\TaskService inside the write transaction, on the
 * RESULTING pair, so a PATCH that moves either side alone is still checked.
 * A referente WITHOUT an anagrafica is refused by the same rule: the
 * association is what makes a referente meaningful on a Task, and the form
 * disables the field until an anagrafica is picked (AC-080).
 */
final class TaskReferentRegistryGuard
{
    private const string REFERENT_REGISTRY_PIVOT = 'referent_registry';

    /**
     * @throws ValidationException 422 on `referent_id`
     */
    public function assertBelongs(?int $registryId, ?int $referentId): void
    {
        if ($referentId === null) {
            return;
        }

        $belongs = $registryId !== null && DB::table(self::REFERENT_REGISTRY_PIVOT)
            ->where('referent_id', $referentId)
            ->where('registry_id', $registryId)
            ->exists();

        if (! $belongs) {
            throw ValidationException::withMessages([
                'referent_id' => ['The selected referent does not belong to the selected registry.'],
            ]);
        }
    }
}
