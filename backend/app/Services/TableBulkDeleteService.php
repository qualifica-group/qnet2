<?php

namespace App\Services;

use App\DataObjects\Table\BulkDeleteResult;
use App\Models\User;
use App\Tables\TableDefinition;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Generic, domain-driven bulk-delete engine for the Table framework (mirrors
 * TableService/TablePreferenceService: one implementation, every domain
 * inherits it through the TableDefinition contract).
 *
 * BEST-EFFORT per id: each delete is independent, no transaction wraps the
 * whole batch, so one forbidden/guarded/failed id never rolls back the
 * others already committed. Domain-specific delete guards (e.g. the
 * last-super-admin guard, a protected system row) are respected because the
 * actual delete is delegated to the definition's deleteModel() — the exact
 * same guard the single-row DELETE endpoint enforces. The per-row ability
 * likewise goes through the definition (authorizeDelete), so a domain whose
 * permission prefix differs from its model's Policy (request-management over
 * Opportunity, D-2) is gated by its OWN permission, never a foreign one.
 */
class TableBulkDeleteService
{
    private const string REASON_NOT_FOUND = 'not_found';

    private const string REASON_FORBIDDEN = 'forbidden';

    private const string REASON_GUARDED = 'guarded';

    /**
     * @param  array<int, int>  $ids
     */
    public function delete(TableDefinition $definition, User $actor, array $ids): BulkDeleteResult
    {
        // Step 1: load every requested row within the domain's own scope
        // (baseQuery), so tenant/base constraints are respected exactly like
        // every other Table endpoint.
        $models = $this->loadModels($definition, $ids);

        // Step 2: ids absent from the loaded set are not_found.
        $failed = $this->notFoundEntries($ids, $models);

        // Step 3: per loaded model, ability check then best-effort delete.
        $deleted = 0;

        foreach ($models as $id => $model) {
            if (! $definition->authorizeDelete($actor, $model)) {
                $failed[] = ['id' => $id, 'reason' => self::REASON_FORBIDDEN];

                continue;
            }

            if ($this->tryDelete($definition, $model)) {
                $deleted++;
            } else {
                $failed[] = ['id' => $id, 'reason' => self::REASON_GUARDED];
            }
        }

        return new BulkDeleteResult($deleted, $failed);
    }

    /**
     * @param  array<int, int>  $ids
     * @return array<int, Model>
     */
    private function loadModels(TableDefinition $definition, array $ids): array
    {
        $query = $definition->baseQuery();
        $key = $query->getModel()->getKeyName();

        /** @var array<int, Model> $models */
        $models = $query->whereIn($key, $ids)->get()->keyBy($key)->all();

        return $models;
    }

    /**
     * @param  array<int, int>  $ids
     * @param  array<int, Model>  $models
     * @return array<int, array{id: int, reason: string}>
     */
    private function notFoundEntries(array $ids, array $models): array
    {
        $failed = [];

        foreach ($ids as $id) {
            if (! array_key_exists($id, $models)) {
                $failed[] = ['id' => $id, 'reason' => self::REASON_NOT_FOUND];
            }
        }

        return $failed;
    }

    /**
     * Best-effort delete of a single, already authorized model. A
     * domain guard rejecting THIS row (AuthorizationException, an HTTP
     * exception raised via `abort()`, e.g. the last-super-admin guard, or a
     * ValidationException — e.g. Tasks' cascade-delete guard, D-5 spec 0153,
     * naming a non-deletable descendant) is caught here and never propagates
     * to abort the rest of the batch.
     */
    private function tryDelete(TableDefinition $definition, Model $model): bool
    {
        try {
            $definition->deleteModel($model);

            return true;
        } catch (AuthorizationException|HttpException|ValidationException) {
            return false;
        }
    }
}
