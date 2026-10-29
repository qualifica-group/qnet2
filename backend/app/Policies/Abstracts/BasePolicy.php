<?php

namespace App\Policies\Abstracts;

use App\Models\User;
use Closure;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;

/**
 * Standard CRUD policy backed by Spatie permissions.
 *
 * Each ability maps to a "{resource}.{ability}" permission, where {resource}
 * is the prefix returned by resource() (typically the model's table name).
 *
 * Override a single method in the concrete policy for non-standard rules
 * (e.g. ownership checks).
 */
abstract class BasePolicy
{
    /**
     * Permission prefix for this resource, e.g. "users".
     */
    abstract protected function resource(): string;

    public function viewAny(User $user): bool|Response
    {
        return $user->can($this->permission('viewAny'));
    }

    public function view(User $user, Model $model): bool|Response
    {
        return $user->can($this->permission('view'));
    }

    public function create(User $user): bool|Response
    {
        return $user->can($this->permission('create'));
    }

    public function update(User $user, Model $model): bool|Response
    {
        return $user->can($this->permission('update'));
    }

    public function delete(User $user, Model $model): bool|Response
    {
        return $user->can($this->permission('delete'));
    }

    /**
     * Bulk/file export of this resource (spec 0004 — centralized authorization
     * metadata: `resource.export`).
     */
    public function export(User $user): bool
    {
        return $user->can($this->permission('export'));
    }

    /**
     * Bulk/file import of this resource (spec 0004 — centralized authorization
     * metadata: `resource.import`).
     */
    public function import(User $user): bool
    {
        return $user->can($this->permission('import'));
    }

    /**
     * View this resource's aggregated activity log (spec 0034 —
     * `resource.viewActivity`). Resource-level, like viewAny/create: the
     * per-record visibility boundary is the model's own `view` ability,
     * checked separately by the endpoint.
     */
    public function viewActivity(User $user): bool
    {
        return $user->can($this->permission('viewActivity'));
    }

    /**
     * The standard CRUD (+export/import/viewActivity) abilities every
     * resource policy exposes.
     *
     * @return array<int, string>
     */
    public static function abilities(): array
    {
        return ['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import', 'viewActivity'];
    }

    /**
     * The full list of standard permissions for this resource.
     *
     * Late-static-bound (`static::abilities()`, not `self::`): a concrete
     * policy overriding `abilities()` (e.g. to drop a nonsensical ability)
     * must see its OWN override reflected here, not BasePolicy's default.
     *
     * @return array<int, string>
     */
    public function permissions(): array
    {
        return array_map(fn (string $ability) => $this->permission($ability), static::abilities());
    }

    /**
     * Permission first, then the perimeter (spec 0328 D-6). A missing
     * permission is denied WITHOUT a message, so the denial registry names
     * it; a record outside the perimeter is denied with its own reason.
     * `$perimeter` runs only once the permission is held, like the `&&` it
     * replaces.
     *
     * @param  Closure(): bool  $perimeter
     */
    protected function withinPerimeter(bool $hasPermission, Closure $perimeter, string $reason): Response
    {
        if (! $hasPermission) {
            return Response::deny();
        }

        return $perimeter() ? Response::allow() : $this->denyBecause($reason);
    }

    /**
     * @param  string  $reason  key of lang/{locale}/authorization.php
     */
    protected function denyBecause(string $reason): Response
    {
        return Response::deny(__("authorization.{$reason}"));
    }

    protected function permission(string $ability): string
    {
        return "{$this->resource()}.{$ability}";
    }
}
