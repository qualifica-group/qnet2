<?php

namespace App\ActivityLog;

use App\DataObjects\ActivityLog\ActivityLogDefinition;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Maps a `{resource}` string (GET /api/activity-log/{resource}/{id}) → its
 * ActivityLogDefinition, mirroring TableRegistry (App\Tables\TableRegistry):
 * an explicit config map (config/activity-log.php), unknown resource →
 * ModelNotFoundException → 404 (via BaseApiController). Adding a resource is
 * one config line, no controller/service change.
 *
 * `authorizer` is optional: a resource that omits it is gated by its root
 * model's own Policy (PolicyActivityLogAuthorizer, the spec-0034 rule).
 */
final class ActivityLogRegistry
{
    /**
     * @throws ModelNotFoundException when the resource is not registered.
     */
    public function resolve(string $resource): ActivityLogDefinition
    {
        /** @var array<string, array{model: class-string, relations?: array<int, string>, authorizer?: class-string, field_permission_resource?: string, field_permissions?: array<string, array<string, string>>}> $definitions */
        $definitions = config('activity-log.resources', []);

        $config = $definitions[$resource] ?? null;

        if ($config === null) {
            throw (new ModelNotFoundException)->setModel(ActivityLogDefinition::class, [$resource]);
        }

        return new ActivityLogDefinition(
            $config['model'],
            $config['relations'] ?? [],
            $config['authorizer'] ?? PolicyActivityLogAuthorizer::class,
            $config['field_permission_resource'] ?? null,
            $config['field_permissions'] ?? [],
        );
    }
}
