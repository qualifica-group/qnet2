<?php

use App\Models\Role;
use App\Models\User;
use App\Services\NavigationService;
use Database\Seeders\TestUsersSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/**
 * Every route the real navigation config would render for $user, flattened —
 * the menu the frontend actually receives.
 *
 * @return array<int, string>
 */
function visibleRoutes(User $user): array
{
    $flatten = function (array $items) use (&$flatten): array {
        $routes = [];

        foreach ($items as $item) {
            if (! empty($item['route'])) {
                $routes[] = $item['route'];
            }

            $routes = array_merge($routes, $flatten($item['children'] ?? []));
        }

        return $routes;
    };

    return $flatten(app(NavigationService::class)->for($user));
}

/** Every account the seeder provisions, email => role. */
function testerAccounts(): array
{
    return [
        'rosa.falzarano@qualificagroup.com' => 'supervisor',
        'fabrizio.aliberti@qualificagroup.com' => 'supervisor',
        'ciro.cacciapuoti@qualificagroup.com' => 'super-admin',
        'campania@commerciale.com' => 'commercial',
        'lazio@commerciale.com' => 'commercial',
        'umberto.santamaria@qualificagroup.com' => 'marketing',
    ];
}

it('creates every tester account with its role, standalone on a fresh database', function () {
    $this->seed(TestUsersSeeder::class);

    foreach (testerAccounts() as $email => $role) {
        $user = User::query()->where('email', $email)->first();

        expect($user)->not->toBeNull()
            ->and($user->getRoleNames()->all())->toBe([$role])
            ->and($user->email_verified_at)->not->toBeNull();
    }
});

it('gives every account the same documented password', function () {
    $this->seed(TestUsersSeeder::class);

    foreach (array_keys(testerAccounts()) as $email) {
        $user = User::query()->where('email', $email)->firstOrFail();

        expect(Hash::check('Qualifica2026!', $user->password))->toBeTrue($email);
    }
});

it('is idempotent: a second run does not duplicate accounts or roles', function () {
    $this->seed(TestUsersSeeder::class);
    $this->seed(TestUsersSeeder::class);

    foreach (array_keys(testerAccounts()) as $email) {
        expect(User::query()->where('email', $email)->count())->toBe(1);
    }

    expect(Role::query()->whereIn('name', ['supervisor', 'commercial', 'marketing'])->count())->toBe(3);
});

it('restores the shared password on a re-run, so rotating the value reaches existing testers', function () {
    $this->seed(TestUsersSeeder::class);

    $rosa = User::query()->where('email', 'rosa.falzarano@qualificagroup.com')->firstOrFail();
    $rosa->forceFill(['password' => Hash::make('changed-by-hand')])->save();

    $this->seed(TestUsersSeeder::class);

    expect(Hash::check('Qualifica2026!', $rosa->fresh()->password))->toBeTrue();
});

it('hides administration, configuration and the four anagrafiche pick-lists from the supervisor', function () {
    $this->seed(TestUsersSeeder::class);

    $supervisor = User::query()->where('email', 'rosa.falzarano@qualificagroup.com')->firstOrFail();

    // Nothing at all on the resources with no select to feed.
    foreach (['roles', 'custom-fields', 'companies', 'company-sites', 'tags'] as $resource) {
        foreach (['viewAny', 'view', 'create', 'update', 'delete'] as $ability) {
            expect($supervisor->can("{$resource}.{$ability}"))->toBeFalse("{$resource}.{$ability}");
        }
    }

    // The select-only resources keep the option list and lose everything else —
    // `view` is what the navigation config gates each menu entry on, so the
    // menu stays clean while the relation controls keep working. `users` is
    // the one survivor of the administration block: the Operatore/Supervisore
    // selects read from it.
    foreach (['sources', 'referent-types', 'operational-sites', 'business-functions', 'sectors', 'users'] as $resource) {
        expect($supervisor->can("{$resource}.viewAny"))->toBeTrue("{$resource}.viewAny")
            ->and($supervisor->can("{$resource}.view"))->toBeFalse("{$resource}.view")
            ->and($supervisor->can("{$resource}.create"))->toBeFalse("{$resource}.create")
            ->and($supervisor->can("{$resource}.update"))->toBeFalse("{$resource}.update")
            ->and($supervisor->can("{$resource}.delete"))->toBeFalse("{$resource}.delete");
    }
});

it('leaves the supervisor the operational modules', function () {
    $this->seed(TestUsersSeeder::class);

    $supervisor = User::query()->where('email', 'fabrizio.aliberti@qualificagroup.com')->firstOrFail();

    foreach (['registries', 'referents', 'projects', 'campaigns', 'leads', 'opportunities', 'products', 'request-management'] as $resource) {
        expect($supervisor->can("{$resource}.viewAny"))->toBeTrue("{$resource}.viewAny")
            ->and($supervisor->can("{$resource}.view"))->toBeTrue("{$resource}.view")
            ->and($supervisor->can("{$resource}.update"))->toBeTrue("{$resource}.update");
    }
});

it('restricts the commercial role to request-management plus the selects it reads', function () {
    $this->seed(TestUsersSeeder::class);

    $commercial = User::query()->where('email', 'campania@commerciale.com')->firstOrFail();

    expect($commercial->can('request-management.viewAny'))->toBeTrue()
        ->and($commercial->can('request-management.view'))->toBeTrue()
        ->and($commercial->can('request-management.create'))->toBeTrue()
        ->and($commercial->can('request-management.update'))->toBeTrue()
        // The module's own permission set only — never opportunities.*.
        ->and($commercial->can('opportunities.viewAny'))->toBeFalse()
        ->and($commercial->can('opportunities.view'))->toBeFalse();

    foreach (['registries', 'sources', 'referents', 'operational-sites', 'users'] as $resource) {
        expect($commercial->can("{$resource}.viewAny"))->toBeTrue("{$resource}.viewAny")
            ->and($commercial->can("{$resource}.view"))->toBeFalse("{$resource}.view")
            ->and($commercial->can("{$resource}.create"))->toBeFalse("{$resource}.create");
    }

    foreach (['projects', 'campaigns', 'leads', 'products', 'companies', 'roles', 'reward-types'] as $resource) {
        foreach (['viewAny', 'view', 'create', 'update', 'delete'] as $ability) {
            expect($commercial->can("{$resource}.{$ability}"))->toBeFalse("{$resource}.{$ability}");
        }
    }
});

it('restricts the marketing role to the marketing-leads modules plus the selects they read', function () {
    $this->seed(TestUsersSeeder::class);

    $marketing = User::query()->where('email', 'umberto.santamaria@qualificagroup.com')->firstOrFail();

    // The "Marketing e Lead" group in full, writes included.
    foreach (['projects', 'campaigns', 'leads', 'pipeline-statuses'] as $resource) {
        foreach (['viewAny', 'view', 'create', 'update', 'delete'] as $ability) {
            expect($marketing->can("{$resource}.{$ability}"))->toBeTrue("{$resource}.{$ability}");
        }
    }

    // The lead import wizard is a lead ability, so it rides along.
    expect($marketing->can('leads.import'))->toBeTrue();

    foreach (['business-functions', 'referents', 'product-categories', 'operational-sites', 'registries', 'sources', 'users'] as $resource) {
        expect($marketing->can("{$resource}.viewAny"))->toBeTrue("{$resource}.viewAny")
            ->and($marketing->can("{$resource}.view"))->toBeFalse("{$resource}.view")
            ->and($marketing->can("{$resource}.create"))->toBeFalse("{$resource}.create")
            ->and($marketing->can("{$resource}.update"))->toBeFalse("{$resource}.update")
            ->and($marketing->can("{$resource}.delete"))->toBeFalse("{$resource}.delete");
    }

    // Everything outside marketing is closed, including the neighbouring
    // commercial domains.
    foreach (['opportunities', 'request-management', 'products', 'companies', 'roles', 'custom-fields', 'reward-types', 'tags'] as $resource) {
        foreach (['viewAny', 'view', 'create', 'update', 'delete'] as $ability) {
            expect($marketing->can("{$resource}.{$ability}"))->toBeFalse("{$resource}.{$ability}");
        }
    }
});

it('leaves the marketing menu with the marketing-leads group only', function () {
    $this->seed(TestUsersSeeder::class);

    $routes = visibleRoutes(User::query()->where('email', 'umberto.santamaria@qualificagroup.com')->firstOrFail());

    // `/dashboard` carries no permission: public to every authenticated user.
    expect($routes)->toBe(['/dashboard', '/projects', '/campaigns', '/leads', '/imports', '/pipeline-statuses']);
});

it('blocks the marketing role server-side on the modules its menu hides', function () {
    $this->seed(TestUsersSeeder::class);

    Sanctum::actingAs(User::query()->where('email', 'umberto.santamaria@qualificagroup.com')->firstOrFail());

    foreach (['projects', 'campaigns', 'leads'] as $domain) {
        $this->getJson("/api/tables/{$domain}/columns")->assertOk();
    }

    foreach (['opportunities', 'request-management', 'products', 'companies'] as $domain) {
        $this->getJson("/api/tables/{$domain}/columns")->assertForbidden();
        $this->postJson("/api/tables/{$domain}/rows", ['startRow' => 0, 'endRow' => 25])->assertForbidden();
    }

    // Every option list answers, `companies` included (ADR 0011 amended
    // 2026-07-31): the module above is closed to this role, its for-select is
    // not — that is the whole point, a form stays fillable without granting
    // browse rights on the source module.
    foreach (['business-functions', 'referents', 'product-categories', 'operational-sites', 'registries', 'sources', 'users', 'companies'] as $resource) {
        $this->getJson("/api/{$resource}/for-select")->assertOk();
    }
});

it('drops the administration, configuration and restricted anagrafiche entries from the supervisor menu', function () {
    $this->seed(TestUsersSeeder::class);

    $routes = visibleRoutes(User::query()->where('email', 'rosa.falzarano@qualificagroup.com')->firstOrFail());

    expect($routes)->not->toContain('/users', '/roles', '/custom-fields', '/migrations')
        ->and($routes)->not->toContain('/business-functions', '/sectors', '/tags', '/sources')
        ->and($routes)->not->toContain('/referent-types', '/companies', '/company-sites', '/operational-sites')
        ->and($routes)->toContain('/dashboard', '/registries', '/referents', '/projects', '/campaigns')
        ->and($routes)->toContain('/leads', '/opportunities', '/products', '/request-management');
});

it('leaves the commercial menu with request-management only', function () {
    $this->seed(TestUsersSeeder::class);

    $routes = visibleRoutes(User::query()->where('email', 'lazio@commerciale.com')->firstOrFail());

    // `/dashboard` carries no permission at all: it is public to every
    // authenticated user by design, so it is the only companion entry.
    expect($routes)->toBe(['/dashboard', '/request-management']);
});

it('blocks the commercial role server-side on the modules its menu hides', function () {
    $this->seed(TestUsersSeeder::class);

    Sanctum::actingAs(User::query()->where('email', 'campania@commerciale.com')->firstOrFail());

    // Its own module answers; every other domain is refused by the definition's
    // viewAny, so a hand-typed URL or a direct API call gains nothing.
    $this->postJson('/api/tables/request-management/rows', ['startRow' => 0, 'endRow' => 25])->assertOk();

    foreach (['opportunities', 'projects', 'campaigns', 'leads', 'products', 'companies'] as $domain) {
        $this->postJson("/api/tables/{$domain}/rows", ['startRow' => 0, 'endRow' => 25])->assertForbidden();
        $this->getJson("/api/tables/{$domain}/columns")->assertForbidden();
    }

    // The for-select endpoints the work panel needs answer. Since ADR 0011 was
    // amended (2026-07-31) they would answer even without the seed's
    // viewAny-only grants — see the note on the next test.
    foreach (['registries', 'sources', 'referents', 'operational-sites', 'users'] as $resource) {
        $this->getJson("/api/{$resource}/for-select")->assertOk();
    }
});

/**
 * Documented residual of the viewAny-only grants, pinned so it cannot change
 * unnoticed: the generic table endpoint authorizes on `<resource>.viewAny`, so
 * the resources feeding a role's relation controls stay list-readable through a
 * hand-typed URL even though their menu entry (gated on `<resource>.view`) is
 * hidden.
 *
 * Those grants existed ONLY to keep the selects populated. Since ADR 0011 was
 * amended (2026-07-31) the selects no longer need them, so dropping them from
 * TestUsersSeeder would close this residual outright — a seed change, still
 * pending an explicit decision, hence this test pins today's behaviour.
 */
it('leaves the select-only resources list-readable, writes excluded', function () {
    $this->seed(TestUsersSeeder::class);

    Sanctum::actingAs(User::query()->where('email', 'campania@commerciale.com')->firstOrFail());
    $this->getJson('/api/tables/registries/columns')->assertOk();

    Sanctum::actingAs(User::query()->where('email', 'rosa.falzarano@qualificagroup.com')->firstOrFail());
    $this->getJson('/api/tables/users/columns')->assertOk();
    // The writes behind them are still refused (`referent-types.create`).
    $this->postJson('/api/referent-types', ['name' => 'Nope'])->assertForbidden();
});

it('blocks the supervisor server-side on administration and configuration', function () {
    $this->seed(TestUsersSeeder::class);

    Sanctum::actingAs(User::query()->where('email', 'rosa.falzarano@qualificagroup.com')->firstOrFail());

    // The modules it does not hold answer nothing.
    foreach (['roles', 'companies', 'company-sites', 'tags'] as $domain) {
        $this->getJson("/api/tables/{$domain}/columns")->assertForbidden();
        $this->postJson("/api/tables/{$domain}/rows", ['startRow' => 0, 'endRow' => 25])->assertForbidden();
    }

    // The option list of a closed module still answers (ADR 0011 amended
    // 2026-07-31): browsing the module and filling a select are distinct.
    $this->getJson('/api/companies/for-select')->assertOk();

    // The operational modules it keeps do answer.
    $this->getJson('/api/tables/registries/columns')->assertOk();
    $this->getJson('/api/tables/opportunities/columns')->assertOk();
});
