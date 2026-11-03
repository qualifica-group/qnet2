<?php

namespace App\Policies;

use App\Models\ImportRun;
use App\Models\User;
use App\Policies\Abstracts\BasePolicy;
use Illuminate\Database\Eloquent\Model;

/**
 * Authorization policy for the import module (spec 0034, per-domain since
 * spec 0348). The module has NO permission set of its own: every surface
 * (history, detail, wizard, export) is gated by the `import` ability of the
 * run's domain — `leads.import` by default (user decision 2026-07-17: the
 * former `import-runs.*` set was a duplicate and was removed),
 * `request-management.import` for the request-management domain.
 *
 * Class-level abilities (viewAny/create) receive the route {domain} as an
 * extra argument (`authorize('create', [ImportRun::class, $domain])`); model
 * abilities read it from the run's `resource`. Without a domain they fall
 * back to `leads.import` (the leads history table, which has no domain).
 *
 * Runs are NOT owner-scoped: every holder of the domain's import permission
 * views and deletes every run, whoever started it (user decision
 * 2026-09-16). Super-admin bypasses globally via AppServiceProvider's
 * Gate::before.
 */
class ImportRunPolicy extends BasePolicy
{
    /** Permission of every domain not listed in DOMAIN_PERMISSIONS. */
    private const string DEFAULT_PERMISSION = 'leads.import';

    /** @var array<string, string> import domain => the permission gating it */
    private const array DOMAIN_PERMISSIONS = [
        'request-management' => 'request-management.import',
    ];

    /**
     * The import module rides the domains' own permission namespaces; it never
     * mints `import-runs.*` strings (see abilities() below).
     */
    protected function resource(): string
    {
        return 'leads';
    }

    public function viewAny(User $user, ?string $domain = null): bool
    {
        return $this->canImport($user, $domain);
    }

    public function view(User $user, Model $model): bool
    {
        return $this->canImport($user, $this->domainOf($model));
    }

    public function create(User $user, ?string $domain = null): bool
    {
        return $this->canImport($user, $domain);
    }

    public function update(User $user, Model $model): bool
    {
        return $this->canImport($user, $this->domainOf($model));
    }

    public function delete(User $user, Model $model): bool
    {
        return $this->canImport($user, $this->domainOf($model));
    }

    public function export(User $user): bool
    {
        return $this->canImport($user, null);
    }

    private function canImport(User $user, ?string $domain): bool
    {
        return $user->can(self::DOMAIN_PERMISSIONS[$domain] ?? self::DEFAULT_PERMISSION);
    }

    private function domainOf(Model $model): ?string
    {
        return $model instanceof ImportRun ? $model->resource : null;
    }

    /**
     * The module contributes NO permissions to the catalog: it reuses
     * `leads.import` (minted by LeadPolicy) and `request-management.import`
     * (minted by RequestManagementPolicy). Empty here so SyncPermissions
     * generates no `import-runs.*` strings.
     *
     * @return array<int, string>
     */
    public static function abilities(): array
    {
        return [];
    }
}
