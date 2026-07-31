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
     *
     * @var array<int, string>
     */
    private const array COMMERCIAL_DENIED_MODULE_ABILITIES = [
        'delete',
        'viewAll',
    ];

    /**
     * Resources feeding the request-management work panel and create form
     * (Cliente / Fonte / Segnalatore / Sede / Operatore). Same `viewAny`-only
     * treatment as the Supervisor list above: without them those selects
     * answer 403 and the module is unusable.
     *
     * @var array<int, string>
     */
    private const array COMMERCIAL_SELECT_ONLY_RESOURCES = [
        'registries',
        'sources',
        'referents',
        'operational-sites',
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
        $this->syncRole(self::COMMERCIAL_ROLE, 'Commerciale', $this->commercialPermissions($catalogue));
        $this->syncRole(self::MARKETING_ROLE, 'Marketing', $this->marketingPermissions($catalogue));

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
    private function syncRole(string $name, string $description, Collection $permissions): void
    {
        $role = Role::findOrCreate($name, Guard::getDefaultName(User::class));
        $role->description = $description;
        $role->save();
        $role->syncPermissions($permissions->all());
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
