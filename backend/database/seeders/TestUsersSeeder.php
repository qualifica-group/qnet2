<?php

namespace Database\Seeders;

use App\Enums\LocaleEnum;
use App\Models\Role;
use App\Models\User;
use App\Services\RoleAssignmentGuard;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Spatie\Permission\Guard;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * The named tester accounts plus the three application roles they exercise
 * (`supervisor`, `commercial`, `marketing`). Standalone and idempotent: it is
 * NOT wired into DatabaseSeeder nor DemoDataSeeder — it runs on demand, like
 * QualificaTemplateSeeder (`php artisan db:seed --class=TestUsersSeeder`).
 *
 * Self-sufficient by design: it re-runs `permissions:sync` and
 * `roles:create-super-admin` first, so a role can never end up silently empty
 * because the catalogue had not been bootstrapped yet.
 *
 * Every account is upserted by EMAIL (the natural key) and every role by name,
 * so a re-run never duplicates rows. They all share one documented credential
 * (`config('seeding.test_users_password')`), reasserted on every run — see
 * seedAccount().
 */
class TestUsersSeeder extends Seeder
{
    private const string SUPERVISOR_ROLE = 'supervisor';

    private const string COMMERCIAL_ROLE = 'commercial';

    private const string MARKETING_ROLE = 'marketing';

    /**
     * Resources the Supervisor must not reach: the whole "Amministrazione"
     * and "Configurazione" areas, plus the four "Anagrafiche" pick-lists
     * (referent types, companies, company sites, operational sites).
     *
     * @var array<int, string>
     */
    private const array SUPERVISOR_DENIED_RESOURCES = [
        'users',
        'roles',
        'custom-fields',
        'business-functions',
        'sectors',
        'tags',
        'sources',
        'referent-types',
        'companies',
        'company-sites',
        'operational-sites',
    ];

    /**
     * Of the denied resources above, the ones whose `for-select` endpoint
     * feeds a relation control on a module the Supervisor DOES use (project/
     * campaign classification, registry, referent, lead, opportunity and
     * request-management forms). They keep `viewAny` and nothing else.
     *
     * `viewAny` is deliberately the only survivor: the navigation config gates
     * every menu entry on `<resource>.view`, so the menu item stays hidden,
     * and no write ability is granted — the option list is all that leaks.
     *
     * @var array<int, string>
     */
    private const array SUPERVISOR_SELECT_ONLY_RESOURCES = [
        'business-functions',
        'sectors',
        'sources',
        'referent-types',
        'operational-sites',
        'users',
    ];

    /**
     * The only module the Commercial role may use, through its OWN permission
     * set (spec 0049 D-2) — `opportunities.*` is never granted.
     */
    private const string COMMERCIAL_MODULE = 'request-management';

    /**
     * The abilities of COMMERCIAL_MODULE the Commercial role must NOT hold:
     *  - `delete` (user directive 2026-07-31): deleting a request is not
     *    theirs. Dropping the permission removes both affordances at once,
     *    server-side — the `delete` row action and the "elimina selezionati"
     *    bulk flow are emitted by RequestManagementTableDefinition only for an
     *    actor holding `request-management.delete`, and authorizeDelete()
     *    refuses the endpoint.
     *  - `viewAll` (user directive 2026-07-31): a Commercial sees ONLY the
     *    requests where they are the GA2 "Operatore". Granting it silently
     *    lifted the whole D-3 scoping — RequestManagementTableDefinition::
     *    baseQuery(), RequestCategoryTabsResolver, RequestManagementScope and
     *    RequestAssignmentService all widen to every request for an actor who
     *    holds it. It is a supervisor-level ability, which is why the
     *    Supervisor role (whose matrix is deny-list based) keeps it.
     *  - `updateSource` (spec 0078, D-1): a Commercial does not write the
     *    Fonte of an existing request directly on any of the three write
     *    channels — they PROPOSE a change instead (COMMERCIAL_EXTRA_PERMISSIONS
     *    below). The create form is unaffected: F-4a's initial attribution is
     *    authorized wholesale by `request-management.create`, never by this
     *    per-field permission.
     *  - `assignOperator` (user directive 2026-08-03): the write half of the
     *    GA2 "Operatore" restriction below. The field matrix governs the work
     *    panel and the grid, but NOT the two channels gated by this ability
     *    alone — the create form's Operatore control (rendered only for a
     *    holder, and rejected by RequestManagementController::store()) and the
     *    bulk POST /request-management/assign-operators, which writes Sede AND
     *    Operatore at once.
     *
     * @var array<int, string>
     */
    private const array COMMERCIAL_DENIED_MODULE_ABILITIES = [
        'delete',
        'viewAll',
        'updateSource',
        'assignOperator',
    ];

    /**
     * Resources feeding the request-management work panel and create form
     * (Cliente / Fonte / Segnalatore / Operatore / categoria prodotto). Same
     * `viewAny`-only treatment as the Supervisor list above: without them
     * those selects answer 403 and the module is unusable.
     *
     * `product-categories` is here because the "categoria prodotto" half of
     * the product-lines row is the one picker that does NOT read a for-select:
     * since the user directive 2026-08-03 it reads the STRUCTURAL tree
     * (`GET /product-categories/tree`, ProductCategoryTreeSelect), which is
     * gated by ProductCategoryPolicy::viewAny — not by the ungated for-select
     * standard (ADR 0011, amended 2026-07-31) its "funzione aziendale" sibling
     * still reads. Without this grant the function select answered and the
     * category select stayed empty. `view` is deliberately not granted: the
     * menu entry is gated on `product-categories.view`
     * (config/navigation/products.php), so the module stays out of the
     * navigation and no write ability comes along.
     *
     * `operational-sites` is deliberately NOT here (user directive
     * 2026-08-03), and its absence is load-bearing rather than cosmetic:
     * RequestManagementAuthorization's ceiling makes `operational_site_id`
     * readonly for an actor lacking `operational-sites.viewAny`, so revoking
     * it locks the "Sede operativa" even where the per-field matrix cannot
     * reach — creation, which resolves no field permission at all. (The select
     * itself would answer either way since ADR 0011 was amended; the ceiling
     * is what this grant still decides.)
     *
     * `users` stays: no ceiling hangs off it, and it is a bare list of names
     * the role already read. Revoking it would be a separate least-privilege
     * decision, not part of this restriction.
     *
     * @var array<int, string>
     */
    private const array COMMERCIAL_SELECT_ONLY_RESOURCES = [
        'registries',
        'sources',
        'referents',
        'product-categories',
        'users',
    ];

    /**
     * Grants on OTHER modules that go beyond their `viewAny`, because a
     * control of the request-management work panel or create form needs them
     * (user directive 2026-07-31):
     *
     * - `referents.create`: the "Segnalatore" relation carries the shared
     *   quick-create "+" (spec 0028), and `QuickCreateButton` renders it only
     *   for an actor holding the linked module's `{domain}.create` — the same
     *   permission POST /api/referents and the duplicate-check endpoint
     *   enforce. Nothing else on `referents` comes along: the module stays out
     *   of the menu (gated on `referents.view`) and unwritable beyond creation.
     * - `notes.create`: writing a collaborative note is gated by this single
     *   agnostic permission (spec 0052, D-6) IN AND with read access to the
     *   host record — which the role already has via `request-management.view`.
     *   Without it the notes composer of the work panel answers 403 on POST
     *   /api/notes. Reading notes needs no permission, so omitting it left the
     *   section visible but unusable.
     * - `attachments.*`: the module's OWN `request-management.viewDocuments`
     *   (already granted, it belongs to COMMERCIAL_MODULE) only opens the
     *   Documents tab; every attachment endpoint behind it is gated by the
     *   polymorphic subsystem's own permissions — list (`viewAny`), download
     *   and preview (`view`), upload (`create`), removal (`delete`, user
     *   directive 2026-07-31: unlike a request, a document of theirs they may
     *   remove). Without them the tab opened onto a 403.
     * - `field-change-requests.create` (spec 0078, D-1): the counterpart of
     *   losing `request-management.updateSource` above — a Commercial
     *   proposes a change to the Fonte instead of writing it. It is the ONLY
     *   `field-change-requests.*` grant of the role (user directive
     *   2026-08-04: the "Richieste di modifica" page is supervisor-only).
     *   `.view` is deliberately absent and its absence is load-bearing twice
     *   over: the navigation entry is gated on it (config/navigation/
     *   opportunities.php), so the menu item disappears, and the browse page
     *   behind it is gated on `.viewAny`, which the role never had — granting
     *   `.view` alone only showed a link to a "forbidden" screen. Nothing the
     *   role legitimately does needs it: reviewing their OWN proposals works
     *   without it, in the work panel's section (GET /for-record falls back to
     *   the requester's own rows, FieldChangeRequestController::forRecord())
     *   and on the record itself (FieldChangeRequestPolicy::view() lets the
     *   richiedente through, AC-039). `.manage` was never granted either:
     *   approving/rejecting stays a supervisor-level act.
     *
     * @var array<int, string>
     */
    private const array COMMERCIAL_EXTRA_PERMISSIONS = [
        'referents.create',
        'notes.create',
        'attachments.viewAny',
        'attachments.view',
        'attachments.create',
        'attachments.delete',
        'field-change-requests.create',
    ];

    /**
     * Per-FIELD restrictions on top of the resource grants above: the spec
     * 0006 `role_field_permissions` matrix, resource => field keys the role
     * neither sees nor writes.
     *
     * User directive 2026-08-03: the "Sede operativa" and the GA2 "Operatore"
     * of a request are supervisory attribution — decided FOR a Commercial, not
     * BY them. Spec 0097, D-3: the operator's field key is now the TEAM's
     * (`manager_slots`), the block that absorbed him — restricting it closes
     * exactly the same channels, the grid's `operator_ga2` cell included. Supervisor and Marketing are untouched (no row = the full code
     * ceiling, spec 0006's "unrestricted" default).
     *
     * The matrix only ever RESTRICTS the ceiling
     * (AbstractResourceAuthorization::fieldPermissions() intersects the two),
     * and `visible: false` collapses editable/required with it, so one row per
     * field closes read AND write on every channel that resolves field
     * permissions: the work panel's controls (MetaField renders nothing),
     * UpdateRequestRequest (422 via EnforcesFieldPermissions) and the grid's
     * inline cell edit (TableCellUpdateService). The channels that do NOT
     * resolve them are closed by the two grants above instead — see
     * COMMERCIAL_DENIED_MODULE_ABILITIES and COMMERCIAL_SELECT_ONLY_RESOURCES.
     *
     * @var array<string, array<int, string>>
     */
    private const array COMMERCIAL_HIDDEN_FIELDS = [
        'request-management' => ['operational_site_id', 'manager_slots'],
    ];

    /**
     * The modules the Marketing role owns in full: exactly the "Marketing e
     * Lead" navigation group (config/navigation.php) — projects, campaigns,
     * leads (the `leads.import` wizard included, it is a lead ability) and the
     * pipeline-status pick-list those two classify against. Nothing else:
     * opportunities, request-management, rewards, registries and the whole
     * administration/configuration area stay out.
     *
     * @var array<int, string>
     */
    private const array MARKETING_MODULES = [
        'projects',
        'campaigns',
        'leads',
        'pipeline-statuses',
    ];

    /**
     * Resources feeding the relation selects of the project, campaign and lead
     * forms (business function / referent / product category / sede / cliente /
     * fonte / operatore). Same `viewAny`-only treatment as the two lists above:
     * without them those selects answer 403 and the modules are unusable, while
     * the menu entries stay hidden (gated on `<resource>.view`).
     *
     * @var array<int, string>
     */
    private const array MARKETING_SELECT_ONLY_RESOURCES = [
        'business-functions',
        'referents',
        'product-categories',
        'operational-sites',
        'registries',
        'sources',
        'users',
    ];

    /**
     * Public because QualificaOperatorSiteLinkSeeder assigns these accounts
     * their operational site after the legacy import, and reads the roster
     * from here rather than restating it.
     *
     * @var array<int, array{name: string, email: string, role: string}>
     */
    public const array TEST_USERS = [
        [
            'name' => 'Rosa Falzarano',
            'email' => 'rosa.falzarano@qualificagroup.com',
            'role' => self::SUPERVISOR_ROLE,
        ],
        [
            'name' => 'Fabrizio Aliberti',
            'email' => 'fabrizio.aliberti@qualificagroup.com',
            'role' => self::SUPERVISOR_ROLE,
        ],
        [
            'name' => 'Ciro Cacciapuoti',
            'email' => 'ciro.cacciapuoti@qualificagroup.com',
            'role' => RoleAssignmentGuard::PRIVILEGED_ROLE,
        ],
        [
            'name' => 'Commerciale Campania',
            'email' => 'campania@commerciale.com',
            'role' => self::COMMERCIAL_ROLE,
        ],
        [
            'name' => 'Commerciale Lazio',
            'email' => 'lazio@commerciale.com',
            'role' => self::COMMERCIAL_ROLE,
        ],
        [
            'name' => 'Umberto Santamaria',
            'email' => 'umberto.santamaria@qualificagroup.com',
            'role' => self::MARKETING_ROLE,
        ],
    ];

    public function run(): void
    {
        // Step 1: guarantee the catalogue this seed depends on, so a standalone
        // run on a fresh database still produces populated roles.
        Artisan::call('permissions:sync');
        Artisan::call('roles:create-super-admin');

        $catalogue = Permission::query()->pluck('name');

        // Step 2: the three roles introduced here, with their permission matrix.
        $this->syncRole(self::SUPERVISOR_ROLE, 'Supervisore operativo', $this->supervisorPermissions($catalogue));
        $commercial = $this->syncRole(self::COMMERCIAL_ROLE, 'Commerciale', $this->commercialPermissions($catalogue));
        $this->syncRole(self::MARKETING_ROLE, 'Marketing', $this->marketingPermissions($catalogue));

        // Step 2-bis: the only per-FIELD restriction of the three roles.
        $this->syncHiddenFields($commercial, self::COMMERCIAL_HIDDEN_FIELDS);

        // Step 3: the accounts themselves, upserted by email.
        foreach (self::TEST_USERS as $account) {
            $this->seedAccount($account['name'], $account['email'], $account['role']);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Everything the system offers minus the denied resources, which keep at
     * most their `viewAny` (see SUPERVISOR_SELECT_ONLY_RESOURCES).
     *
     * `request-management` is NOT among the denied resources, so this role
     * also receives `request-management.receiveTransferNotifications` — the
     * grant that decides who is copied on the transfer notifications (spec
     * 0081). That is the intended binding, not an accident: revoking it on a
     * role is the supported way to stop those emails.
     *
     * @param  Collection<int, string>  $catalogue
     * @return Collection<int, string>
     */
    private function supervisorPermissions(Collection $catalogue): Collection
    {
        return $catalogue->filter(function (string $permission): bool {
            $resource = $this->resourceOf($permission);

            if (! in_array($resource, self::SUPERVISOR_DENIED_RESOURCES, true)) {
                return true;
            }

            return $this->isSelectOnlyGrant($permission, self::SUPERVISOR_SELECT_ONLY_RESOURCES);
        })->values();
    }

    /**
     * The request-management module minus its denied abilities, plus the
     * `viewAny` of the resources its own selects read from and the handful of
     * extra grants its create form needs. Nothing else.
     *
     * @param  Collection<int, string>  $catalogue
     * @return Collection<int, string>
     */
    private function commercialPermissions(Collection $catalogue): Collection
    {
        return $catalogue->filter(function (string $permission): bool {
            if ($this->resourceOf($permission) === self::COMMERCIAL_MODULE) {
                return ! in_array($this->abilityOf($permission), self::COMMERCIAL_DENIED_MODULE_ABILITIES, true);
            }

            if (in_array($permission, self::COMMERCIAL_EXTRA_PERMISSIONS, true)) {
                return true;
            }

            return $this->isSelectOnlyGrant($permission, self::COMMERCIAL_SELECT_ONLY_RESOURCES);
        })->values();
    }

    /**
     * The "Marketing e Lead" modules in full, plus the `viewAny` of the
     * resources their own selects read from. Nothing else.
     *
     * @param  Collection<int, string>  $catalogue
     * @return Collection<int, string>
     */
    private function marketingPermissions(Collection $catalogue): Collection
    {
        return $catalogue->filter(function (string $permission): bool {
            if (in_array($this->resourceOf($permission), self::MARKETING_MODULES, true)) {
                return true;
            }

            return $this->isSelectOnlyGrant($permission, self::MARKETING_SELECT_ONLY_RESOURCES);
        })->values();
    }

    /**
     * @param  array<int, string>  $resources
     */
    private function isSelectOnlyGrant(string $permission, array $resources): bool
    {
        return $this->abilityOf($permission) === 'viewAny'
            && in_array($this->resourceOf($permission), $resources, true);
    }

    private function resourceOf(string $permission): string
    {
        return Str::beforeLast($permission, '.');
    }

    private function abilityOf(string $permission): string
    {
        return Str::afterLast($permission, '.');
    }

    /**
     * @param  Collection<int, string>  $permissions
     */
    private function syncRole(string $name, string $description, Collection $permissions): Role
    {
        $role = Role::findOrCreate($name, Guard::getDefaultName(User::class));
        $role->description = $description;
        $role->save();
        $role->syncPermissions($permissions->all());

        return $role;
    }

    /**
     * Replaces the role's WHOLE field-permission matrix with $hiddenFields —
     * a full sync, exactly like syncPermissions() above and like
     * RoleService::syncFieldPermissions() on the admin UI path. A re-run
     * therefore converges, and a field dropped from the list stops being
     * restricted instead of leaving an orphan row behind.
     *
     * @param  array<string, array<int, string>>  $hiddenFields  resource => field keys
     */
    private function syncHiddenFields(Role $role, array $hiddenFields): void
    {
        $role->fieldPermissions()->delete();

        foreach ($hiddenFields as $resource => $fields) {
            foreach ($fields as $field) {
                $role->fieldPermissions()->create([
                    'resource' => $resource,
                    'field' => $field,
                    'visible' => false,
                    'editable' => false,
                    'required' => false,
                ]);
            }
        }
    }

    /**
     * Unlike DemoUserSeeder, the password is rewritten on EVERY run, not only
     * on creation: these accounts are handed out with one documented shared
     * credential, so a re-seed must be able to restore it — otherwise rotating
     * the value would silently leave already-seeded testers on the old one.
     * The cost is deliberate: a password changed from the UI is reset here.
     */
    private function seedAccount(string $name, string $email, string $role): void
    {
        $user = User::firstOrNew(['email' => $email]);
        $user->name = $name;
        $user->locale = LocaleEnum::It->value;
        $user->email_verified_at ??= now();
        $user->password = config('seeding.test_users_password');

        $user->save();
        $user->syncRoles([$role]);
    }
}
