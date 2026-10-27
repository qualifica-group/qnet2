<?php

namespace Database\Seeders\Concerns;

use App\Models\Role;
use App\Models\User;
use App\RequestManagement\RequestModule;
use Database\Seeders\QualificaCatalog\OperatorRoleCatalogue as Catalogue;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Spatie\Permission\Guard;

/**
 * The one writer of a seeded role's matrix (permissions and per-field rows),
 * shared by every seeder that owns application roles, so they all converge the
 * same way: upsert by name, FULL sync, never an accumulation of grants.
 */
trait SyncsRoleMatrix
{
    /**
     * Hidden from every role a seeder syncs (spec 0314): the opportunity's
     * Lead is for super-admin only, who bypasses the matrix.
     *
     * @var array<string, array<int, string>>
     */
    private const array EVERY_ROLE_HIDDEN_FIELDS = [
        'opportunities' => ['lead_id'],
    ];

    /** The quote visibility widenings the two request modules mirror. */
    private const array QUOTE_VISIBILITY_TIERS = ['viewAll', 'viewSite'];

    /**
     * @param  Collection<int, string>  $permissions
     * @param  Collection<int, string>  $catalogue
     * @return Collection<int, string>
     */
    private function withUserFormStack(Collection $permissions, Collection $catalogue): Collection
    {
        return $permissions->contains(Catalogue::USER_FORM_GATE)
            ? $permissions->merge($catalogue->filter($this->isUserFormGrant(...)))->unique()->values()
            : $permissions;
    }

    /**
     * Derived from the role's permissions rather than from a block: whichever
     * block opens the Utenti section, the form must load in full.
     */
    private function isUserFormGrant(string $permission): bool
    {
        return in_array(Str::beforeLast($permission, '.'), Catalogue::USER_FORM_MODULES, true)
            && in_array(Str::afterLast($permission, '.'), Catalogue::USER_FORM_ABILITIES, true);
    }

    /**
     * @param  Collection<int, string>  $permissions
     */
    private function syncRole(string $name, string $description, Collection $permissions): Role
    {
        $role = Role::findOrCreate($name, Guard::getDefaultName(User::class));
        $role->description = $description;
        $role->save();
        $role->syncPermissions($this->withRequestModulesSight($permissions)->all());

        return $role;
    }

    /**
     * Whoever sees opportunities and quotes also sees Gestione Richieste and
     * Gestione Iscritti (user directive 2026-10-27), read-only, as wide as
     * their quotes: every offer, their Sedi's, or only the ones they operate.
     *
     * @param  Collection<int, string>  $permissions
     * @return Collection<int, string>
     */
    private function withRequestModulesSight(Collection $permissions): Collection
    {
        if (! $permissions->contains('opportunities.view') || ! $permissions->contains('quotes.view')) {
            return $permissions;
        }

        $abilities = collect(['viewAny', 'view'])
            ->merge(collect(self::QUOTE_VISIBILITY_TIERS)->filter(fn (string $tier): bool => $permissions->contains("quotes.{$tier}")));

        return $permissions
            ->merge(collect(RequestModule::cases())->flatMap(fn (RequestModule $module): Collection => $abilities->map($module->permission(...))))
            ->unique()
            ->values();
    }

    /**
     * Replaces the role's WHOLE field-permission matrix with the three lists,
     * plus EVERY_ROLE_HIDDEN_FIELDS — a full sync, exactly like
     * RoleService::syncFieldPermissions() on the admin UI path. ONE writer for
     * every degree, because the delete-then-create is what makes the sync
     * convergent.
     *
     * A granted row restricts nothing (the matrix intersects the code
     * ceiling, spec 0006), but it is not the same as no row: the matrix is the
     * union of the actor's roles that HAVE a row, so only an explicit grant
     * outweighs the hidden row of another role the same person holds.
     *
     * @param  array<string, array<int, string>>  $hiddenFields  resource => field keys the role never sees
     * @param  array<string, array<int, string>>  $readonlyFields  resource => field keys the role sees but cannot write
     * @param  array<string, array<int, string>>  $grantedFields  resource => field keys the role sees and writes
     */
    private function syncFieldPermissions(Role $role, array $hiddenFields = [], array $readonlyFields = [], array $grantedFields = []): void
    {
        $role->fieldPermissions()->delete();

        $hiddenFields = array_merge_recursive(self::EVERY_ROLE_HIDDEN_FIELDS, $hiddenFields);
        $this->createFieldPermissions($role, $hiddenFields, visible: false, editable: false);
        $this->createFieldPermissions($role, $readonlyFields, visible: true, editable: false);
        $this->createFieldPermissions($role, $grantedFields, visible: true, editable: true);
    }

    /**
     * @param  array<string, array<int, string>>  $fieldsByResource
     */
    private function createFieldPermissions(Role $role, array $fieldsByResource, bool $visible, bool $editable): void
    {
        foreach ($fieldsByResource as $resource => $fields) {
            foreach ($fields as $field) {
                $role->fieldPermissions()->create([
                    'resource' => $resource,
                    'field' => $field,
                    'visible' => $visible,
                    'editable' => $editable,
                    'required' => false,
                ]);
            }
        }
    }
}
