<?php

use App\Models\Opportunity;
use App\Models\Quote;
use App\Models\Role;
use App\Models\User;
use App\Services\NavigationService;
use Database\Seeders\TestUsersSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
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

/**
 * Creates an offer on $opportunity operated by $user (spec 0087, D-9) —
 * the only way a role without `request-management.viewAll` reaches a
 * request now: `quotes.operator_id`, no longer the GA2 "Operatore" pivot
 * slot on its own.
 */
function asOperatorOf(Opportunity $opportunity, User $user): Quote
{
    return Quote::factory()->for($opportunity)->create(['operator_id' => $user->id]);
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
        // Deleting a request is not theirs (user directive 2026-07-31).
        ->and($commercial->can('request-management.delete'))->toBeFalse()
        // Nor is seeing the requests of the other commercials: without
        // `viewAll` the module's D-3 scoping applies (see the test below).
        ->and($commercial->can('request-management.viewAll'))->toBeFalse()
        // Deciding who works a request is supervisory (user directive
        // 2026-08-03): without it the create form's Operatore control is not
        // rendered and both write endpoints refuse it.
        ->and($commercial->can('request-management.assignOperator'))->toBeFalse()
        // The module's own permission set only — never opportunities.*.
        ->and($commercial->can('opportunities.viewAny'))->toBeFalse()
        ->and($commercial->can('opportunities.view'))->toBeFalse();

    foreach (['registries', 'sources', 'product-categories', 'users'] as $resource) {
        expect($commercial->can("{$resource}.viewAny"))->toBeTrue("{$resource}.viewAny")
            ->and($commercial->can("{$resource}.view"))->toBeFalse("{$resource}.view")
            ->and($commercial->can("{$resource}.create"))->toBeFalse("{$resource}.create");
    }

    // The Sede operativa's own select is closed too (user directive
    // 2026-08-03): the ceiling of `operational_site_id` hangs off this exact
    // ability, so revoking it locks the field even where the per-field matrix
    // cannot reach (creation).
    expect($commercial->can('operational-sites.viewAny'))->toBeFalse();

    // `referents` is the one exception (user directive 2026-07-31): `create`
    // rides along so the "Segnalatore" quick-create "+" of the create form
    // renders and its POST is authorized. Nothing else opens up.
    expect($commercial->can('referents.viewAny'))->toBeTrue()
        ->and($commercial->can('referents.create'))->toBeTrue()
        ->and($commercial->can('referents.view'))->toBeFalse()
        ->and($commercial->can('referents.update'))->toBeFalse()
        ->and($commercial->can('referents.delete'))->toBeFalse();

    // Writing a collaborative note on a request is gated by the single
    // agnostic `notes.create` (spec 0052 D-6): without it the composer of the
    // work panel is visible (reading needs no permission) but every POST
    // /api/notes answers 403.
    expect($commercial->can('notes.create'))->toBeTrue();

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

// The Commercial holds no `request-management.viewAll`, so the module's D-9
// scoping (RequestManagementTableDefinition::baseQuery) applies to them: the
// list is exactly the offers they operate (`quotes.operator_id`).
// Granting viewAll to the role silently lifted this for every commercial.
it('scopes the commercial request list to the offers they operate', function () {
    $this->seed(TestUsersSeeder::class);

    $lazio = User::query()->where('email', 'lazio@commerciale.com')->firstOrFail();
    $campania = User::query()->where('email', 'campania@commerciale.com')->firstOrFail();

    $own = asOperatorOf(Opportunity::factory()->create(), $lazio);
    $othersRequest = asOperatorOf(Opportunity::factory()->create(), $campania);
    $unassigned = Quote::factory()->create();

    Sanctum::actingAs($lazio);

    $items = $this->postJson('/api/tables/request-management/rows', ['startRow' => 0, 'endRow' => 25])
        ->assertOk()
        ->json('items');

    expect(collect($items)->pluck('id')->all())->toBe([$own->id]);

    // The work panel of a scoped-out request is closed too (RequestManagementScope).
    $this->getJson("/api/request-management/{$othersRequest->id}")->assertForbidden();
    $this->getJson("/api/request-management/{$unassigned->id}")->assertForbidden();
});

it('closes the commercial delete of a request, row action and bulk engine alike', function () {
    $this->seed(TestUsersSeeder::class);

    $actor = User::query()->where('email', 'campania@commerciale.com')->firstOrFail();
    $quote = asOperatorOf(Opportunity::factory()->create(), $actor);

    Sanctum::actingAs($actor);

    // The row action is not even offered (RequestManagementTableDefinition::
    // actionsFor gates it on `request-management.delete`)...
    $items = $this->postJson('/api/tables/request-management/rows', ['startRow' => 0, 'endRow' => 25])
        ->assertOk()
        ->json('items');

    expect(collect($items)->firstWhere('id', $quote->id)['actions'])->not->toContain('delete');

    // ...and the generic bulk engine refuses the id: the endpoint's baseline
    // gate is the definition's viewAny, the per-row check is authorizeDelete().
    $result = $this->postJson('/api/tables/request-management/bulk-delete', ['ids' => [$quote->id]])
        ->assertOk()
        ->json('data');

    expect($result['deleted'])->toBe(0);
    $this->assertDatabaseHas('quotes', ['id' => $quote->id]);
});

it('lets the commercial role create a referent, for the create form quick-create "+"', function () {
    $this->seed(TestUsersSeeder::class);

    Sanctum::actingAs(User::query()->where('email', 'campania@commerciale.com')->firstOrFail());

    // Both endpoints behind the "+" are gated by `referents.create`: the live
    // duplicate check the dialog runs while typing (shared with the anagrafica
    // form since the directive 2026-09-09, hence the `identity/` path), and the
    // write itself.
    $this->postJson('/api/identity/duplicate-check', ['tax_code' => 'RSSMRA80A01H501U'])->assertOk();

    // `GET /meta/referents` (the dialog's field/permission envelope) rides on
    // the pre-existing `referents.viewAny`.
    $this->getJson('/api/meta/referents')->assertOk();
});

it('lets the commercial role write a collaborative note on a request', function () {
    $this->seed(TestUsersSeeder::class);

    $actor = User::query()->where('email', 'campania@commerciale.com')->firstOrFail();
    // A request of theirs: note authorization runs the same D-3 record
    // boundary (RequestManagementNotable), which no longer opens via viewAll.
    $opportunity = Opportunity::factory()->create();
    asOperatorOf($opportunity, $actor);

    Sanctum::actingAs($actor);

    $this->postJson('/api/notes', [
        'entity_type' => 'request-management',
        'entity_id' => $opportunity->id,
        'body' => 'Richiamare il cliente domani mattina.',
    ])->assertCreated();
});

// `request-management.viewDocuments` only opens the Documents tab: every
// endpoint behind it belongs to the polymorphic attachment subsystem and is
// gated by `attachments.*`, which no scoping ties to the host record. Both
// roles therefore need the component's own permissions on top of the module's.
it('lets the supervisor and the commercial role list, upload and remove request documents', function () {
    Storage::fake('local');
    $this->seed(TestUsersSeeder::class);

    $opportunity = Opportunity::factory()->create();

    foreach (['rosa.falzarano@qualificagroup.com', 'campania@commerciale.com'] as $email) {
        $actor = User::query()->where('email', $email)->firstOrFail();

        expect($actor->can('request-management.viewDocuments'))->toBeTrue($email);

        Sanctum::actingAs($actor);

        $attachmentId = $this->postJson('/api/attachments', [
            'attachable_type' => 'opportunity',
            'attachable_id' => $opportunity->id,
            'file' => UploadedFile::fake()->create('contratto.pdf', 16, 'application/pdf'),
        ])->assertCreated()->json('data.id');

        $this->getJson("/api/attachments?attachable_type=opportunity&attachable_id={$opportunity->id}")->assertOk();
        $this->get("/api/attachments/{$attachmentId}/download")->assertOk();
        // Removing a document of theirs is granted to both (user directive
        // 2026-07-31) — unlike deleting the request itself.
        $this->deleteJson("/api/attachments/{$attachmentId}")->assertNoContent();
    }
});

it('leaves the commercial menu with request-management only', function () {
    $this->seed(TestUsersSeeder::class);

    $routes = visibleRoutes(User::query()->where('email', 'lazio@commerciale.com')->firstOrFail());

    // `/dashboard` carries no permission at all: it is public to every
    // authenticated user by design, so it is the only companion entry.
    // `/field-change-requests` (spec 0078) is NOT one: user directive
    // 2026-08-04 made that page supervisor-only, so the seed dropped
    // `field-change-requests.view` — the very permission the navigation entry
    // is gated on (config/navigation/opportunities.php). The role keeps
    // `.create`, which carries no menu entry.
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
