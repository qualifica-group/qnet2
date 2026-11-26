<?php

namespace Database\Seeders\Concerns;

use App\Models\Role;
use App\Models\User;
use App\Policies\AiConversationPolicy;
use App\RequestManagement\RequestModule;
use App\Support\Notifications\AssignmentNotificationChannels;
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

    /** The quote visibility widenings the two request modules mirror (spec 0340 rev.3: the competence tier too). */
    private const array QUOTE_VISIBILITY_TIERS = ['viewAll', 'viewSite', 'viewCompetence'];

    /** Reading a request or an enrollee includes its documents, as the mansionario's enrollee read block (spec 0408 D-15). */
    private const array REQUEST_READ_ABILITIES = ['viewAny', 'view', 'viewDocuments'];

    /** The modules whose `viewAll` also carries `viewCompetence` (spec 0340, user directive 2026-11-02). */
    private const array COMPETENCE_SIGHT_RESOURCES = ['opportunities', 'quotes', 'contracts', 'work-orders'];

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
     * @param  bool  $requestSightFromQuotes  false for a role whose questionnaire answered the request
     *                                        modules itself (spec 0408 D-14): its grants are kept as given
     * @param  bool  $assignmentNotifications  false for a single-person add-on role (spec 0420 D-4)
     */
    private function syncRole(string $name, string $description, Collection $permissions, bool $requestSightFromQuotes = true, bool $assignmentNotifications = true): Role
    {
        $role = Role::findOrCreate($name, Guard::getDefaultName(User::class));
        $role->description = $description;
        $role->save();
        $permissions = $this->withCompetenceSight($permissions);
        $permissions = $requestSightFromQuotes ? $this->withRequestModulesSight($permissions) : $permissions;
        $permissions = $assignmentNotifications ? $this->withAssignmentNotifications($permissions) : $permissions;
        // Everyone has the assistant (spec 0433): the grant does not depend on the role's blocks.
        $role->syncPermissions($permissions->push(AiConversationPolicy::PERMISSION)->unique()->all());

        return $role;
    }

    /**
     * Whoever sees every record of one of COMPETENCE_SIGHT_RESOURCES also gets
     * that module's `viewCompetence` (user directive 2026-11-02, spec 0340),
     * so the competence tier is already in place on the roles that see all.
     *
     * @param  Collection<int, string>  $permissions
     * @return Collection<int, string>
     */
    private function withCompetenceSight(Collection $permissions): Collection
    {
        return $permissions
            ->merge(collect(self::COMPETENCE_SIGHT_RESOURCES)
                ->filter(fn (string $resource): bool => $permissions->contains("{$resource}.viewAll"))
                ->map(fn (string $resource): string => "{$resource}.viewCompetence"))
            ->unique()
            ->values();
    }

    /**
     * Every operational seeded role (one that sees at least one module)
     * receives the assignment notifications, bell and email, of every module
     * (spec 0420 D-4): the pre-0420 behaviour, which the administrator then
     * narrows role by role. A role that sees nothing (e.g. import only) stays as declared.
     *
     * @param  Collection<int, string>  $permissions
     * @return Collection<int, string>
     */
    private function withAssignmentNotifications(Collection $permissions): Collection
    {
        if (! $permissions->contains(fn (string $permission): bool => str_ends_with($permission, '.view'))) {
            return $permissions;
        }

        return $permissions->merge(AssignmentNotificationChannels::permissions())->unique()->values();
    }

    /**
     * Whoever sees opportunities and quotes also sees Gestione Richieste and
     * Gestione Iscritti (user directive 2026-10-27), read-only with the
     * documents, as wide as their quotes: every offer, their Sedi's, or only the ones they operate.
     *
     * @param  Collection<int, string>  $permissions
     * @return Collection<int, string>
     */
    private function withRequestModulesSight(Collection $permissions): Collection
    {
        if (! $permissions->contains('opportunities.view') || ! $permissions->contains('quotes.view')) {
            return $permissions;
        }

        $abilities = collect(self::REQUEST_READ_ABILITIES)
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
