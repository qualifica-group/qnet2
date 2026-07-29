<?php

namespace App\DataObjects\ActivityLog;

use App\ActivityLog\Contracts\ActivityLogAuthorizer;
use Illuminate\Database\Eloquent\Model;

/**
 * One `config/activity-log.php` entry, resolved (spec 0034): the root model
 * class of a `{resource}`, the dot-path relations aggregated alongside it and
 * the authorizer that gates reading it (default: the model's own Policy).
 */
final readonly class ActivityLogDefinition
{
    /**
     * @param  class-string<Model>  $model
     * @param  array<int, string>  $relations
     * @param  class-string<ActivityLogAuthorizer>  $authorizer
     * @param  array<string, array<string, string>>  $fieldPermissions
     */
    public function __construct(
        public string $model,
        public array $relations,
        public string $authorizer,
        public ?string $fieldPermissionResource = null,
        public array $fieldPermissions = [],
    ) {}
}
