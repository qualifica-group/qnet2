<?php

use App\Models\Opportunity;
use App\Models\Quote;
use App\Models\User;
use App\Services\NavigationService;
use Database\Seeders\QualificaOperatorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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

// Mansionario 2026-09-15: the supervisor matrix follows the CSV literally —
// prodotti, categorie prodotti, anagrafiche, referenti, Marketing e Lead,
// Gestione Richieste + report, configuratore di stati, buoni e incentivi,
// Richieste di modifica, gestione iscritti — no longer "everything but administration".
it('closes administration and configuration to the supervisor, selects aside', function () {
    $this->seed(QualificaOperatorSeeder::class);

    $supervisor = User::query()->where('email', 'rosa.falzarano@qualificagroup.com')->firstOrFail();

    // `opportunities` left this list with the lead conversion grant (user
    // directive 2026-09-16), pinned in QualificaLeadConversionPermissionTest.
    foreach (['custom-fields', 'company-sites', 'tasks'] as $resource) {
        foreach (['viewAny', 'view', 'create', 'update', 'delete'] as $ability) {
            expect($supervisor->can("{$resource}.{$ability}"))->toBeFalse("{$resource}.{$ability}");
        }
    }

    // The select-only resources keep the option list and lose everything else:
    // `view` is what the navigation config gates each menu entry on.
    foreach (['sources', 'operational-sites', 'business-functions', 'companies', 'vat-rates', 'referent-types'] as $resource) {
        expect($supervisor->can("{$resource}.viewAny"))->toBeTrue("{$resource}.viewAny")
            ->and($supervisor->can("{$resource}.view"))->toBeFalse("{$resource}.view")
            ->and($supervisor->can("{$resource}.create"))->toBeFalse("{$resource}.create")
            ->and($supervisor->can("{$resource}.update"))->toBeFalse("{$resource}.update")
            ->and($supervisor->can("{$resource}.delete"))->toBeFalse("{$resource}.delete");
    }
});

// User directive 2026-09-18: the Utenti and Ruoli sections open to the
// supervisor with every ability but `create`.
it('opens users and roles to the supervisor, creation excluded', function () {
    $this->seed(QualificaOperatorSeeder::class);

    $supervisor = User::query()->where('email', 'rosa.falzarano@qualificagroup.com')->firstOrFail();

    foreach (['users', 'roles'] as $resource) {
        foreach (['viewAny', 'view', 'update', 'delete', 'export', 'import', 'viewActivity'] as $ability) {
            expect($supervisor->can("{$resource}.{$ability}"))->toBeTrue("{$resource}.{$ability}");
        }

        expect($supervisor->can("{$resource}.create"))->toBeFalse("{$resource}.create");
    }

    expect(visibleRoutes($supervisor))->toContain('/users', '/roles');

    // The coordinator's mansione does not include them.
    $coordinator = User::query()->where('email', 'umberto.santamaria@qualificagroup.com')->firstOrFail();

    expect($coordinator->can('users.view'))->toBeFalse()
        ->and($coordinator->can('roles.viewAny'))->toBeFalse();
});

it('lets the supervisor view and edit every module of its mansione', function () {
    $this->seed(QualificaOperatorSeeder::class);

    $supervisor = User::query()->where('email', 'fabrizio.aliberti@qualificagroup.com')->firstOrFail();

    $modules = [
        'projects', 'campaigns', 'leads', 'request-management', 'products', 'product-categories',
        'registries', 'referents', 'quote-workflows', 'reward-types', 'reward-statuses',
    ];

    foreach ($modules as $resource) {
        foreach (['viewAny', 'view', 'create', 'update'] as $ability) {
            expect($supervisor->can("{$resource}.{$ability}"))->toBeTrue("{$resource}.{$ability}");
        }
    }

    // Gestione Iscritti has no `create` at all (spec 0130 D-8): the rest of the
    // module is theirs.
    expect($supervisor->can('rewarded-referents.viewAny'))->toBeTrue()
        ->and($supervisor->can('enrollee-management.view'))->toBeTrue()
        ->and($supervisor->can('enrollee-management.update'))->toBeTrue();

    foreach (['viewAll', 'report', 'assignOperator', 'updateSource', 'delete'] as $ability) {
        expect($supervisor->can("request-management.{$ability}"))->toBeTrue($ability)
            ->and($supervisor->can("enrollee-management.{$ability}"))->toBeTrue("enrollee-management.{$ability}");
    }
});

it('gives the coordinator the catalogue, anagrafiche and enrollees, not the status configurator nor the rewards', function () {
    $this->seed(QualificaOperatorSeeder::class);

    $coordinator = User::query()->where('email', 'umberto.santamaria@qualificagroup.com')->firstOrFail();

    foreach (['products', 'product-categories', 'registries', 'referents', 'enrollee-management'] as $resource) {
        expect($coordinator->can("{$resource}.view"))->toBeTrue("{$resource}.view")
            ->and($coordinator->can("{$resource}.update"))->toBeTrue("{$resource}.update");
    }

    foreach (['quote-workflows', 'reward-types', 'reward-statuses', 'rewarded-referents', 'field-change-requests'] as $resource) {
        expect($coordinator->can("{$resource}.viewAny"))->toBeFalse("{$resource}.viewAny");
    }
});

it('restricts the commercial role to request-management plus the selects it reads', function () {
    $this->seed(QualificaOperatorSeeder::class);

    $commercial = User::query()->where('email', 'marco.baldi@qualificagroup.com')->firstOrFail();

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
        // "no report" (Mansionario 2026-09-15).
        ->and($commercial->can('request-management.report'))->toBeFalse()
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
    $this->seed(QualificaOperatorSeeder::class);

    $marketing = User::query()->where('email', 'sabino.figurelli@qualificagroup.com')->firstOrFail();

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
    // `opportunities` left this list with the lead conversion grant (user
    // directive 2026-09-16), pinned in QualificaLeadConversionPermissionTest.
    foreach (['request-management', 'products', 'companies', 'roles', 'custom-fields', 'reward-types', 'tags'] as $resource) {
        foreach (['viewAny', 'view', 'create', 'update', 'delete'] as $ability) {
            expect($marketing->can("{$resource}.{$ability}"))->toBeFalse("{$resource}.{$ability}");
        }
    }
});

it('leaves the marketing menu with the marketing-leads group only', function () {
    $this->seed(QualificaOperatorSeeder::class);

    $routes = visibleRoutes(User::query()->where('email', 'sabino.figurelli@qualificagroup.com')->firstOrFail());

    // `/dashboard` carries no permission: public to every authenticated user.
    expect($routes)->toBe(['/dashboard', '/projects', '/campaigns', '/leads', '/imports', '/pipeline-statuses']);
});

it('blocks the marketing role server-side on the modules its menu hides', function () {
    $this->seed(QualificaOperatorSeeder::class);

    Sanctum::actingAs(User::query()->where('email', 'sabino.figurelli@qualificagroup.com')->firstOrFail());

    foreach (['projects', 'campaigns', 'leads'] as $domain) {
        $this->getJson("/api/tables/{$domain}/columns")->assertOk();
    }

    // `opportunities` left this list with the lead conversion grant (user
    // directive 2026-09-16), pinned in QualificaLeadConversionPermissionTest.
    foreach (['request-management', 'products', 'companies'] as $domain) {
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

it('shows the supervisor menu exactly the modules of its mansione', function () {
    $this->seed(QualificaOperatorSeeder::class);

    $routes = visibleRoutes(User::query()->where('email', 'rosa.falzarano@qualificagroup.com')->firstOrFail());

    expect($routes)->not->toContain('/users', '/roles', '/custom-fields', '/migrations')
        ->and($routes)->not->toContain('/business-functions', '/sectors', '/tags', '/sources')
        ->and($routes)->not->toContain('/referent-types', '/companies', '/company-sites', '/operational-sites')
        ->and($routes)->not->toContain('/opportunities', '/attributes', '/vat-rates')
        ->and($routes)->toContain('/enrollee-management')
        ->and($routes)->toContain('/dashboard', '/projects', '/campaigns', '/leads', '/request-management', '/field-change-requests')
        ->and($routes)->toContain('/registries', '/referents', '/products', '/product-categories', '/quote-workflows')
        ->and($routes)->toContain('/reward-types', '/reward-statuses', '/rewarded-referents');
});

// The Commercial holds no `request-management.viewAll`, so the module's D-9
// scoping (RequestManagementTableDefinition::baseQuery) applies to them: the
// list is exactly the offers they operate (`quotes.operator_id`).
// Granting viewAll to the role silently lifted this for every commercial.
it('scopes the commercial request list to the offers they operate', function () {
    $this->seed(QualificaOperatorSeeder::class);

    $lazio = User::query()->where('email', 'biagio.fusco@qualificagroup.it')->firstOrFail();
    $campania = User::query()->where('email', 'marco.baldi@qualificagroup.com')->firstOrFail();

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
    $this->seed(QualificaOperatorSeeder::class);

    $actor = User::query()->where('email', 'marco.baldi@qualificagroup.com')->firstOrFail();
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
    $this->seed(QualificaOperatorSeeder::class);

    Sanctum::actingAs(User::query()->where('email', 'marco.baldi@qualificagroup.com')->firstOrFail());

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
    $this->seed(QualificaOperatorSeeder::class);

    $actor = User::query()->where('email', 'marco.baldi@qualificagroup.com')->firstOrFail();
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
    $this->seed(QualificaOperatorSeeder::class);

    $opportunity = Opportunity::factory()->create();

    foreach (['rosa.falzarano@qualificagroup.com', 'marco.baldi@qualificagroup.com'] as $email) {
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

it('leaves the commercial menu with request-management and enrollee-management only', function () {
    $this->seed(QualificaOperatorSeeder::class);

    $routes = visibleRoutes(User::query()->where('email', 'biagio.fusco@qualificagroup.it')->firstOrFail());

    // `/dashboard` carries no permission at all: it is public to every
    // authenticated user by design, so it is the only companion entry.
    // `/field-change-requests` (spec 0078) is NOT one: user directive
    // 2026-08-04 made that page supervisor-only, so the seed dropped
    // `field-change-requests.view` — the very permission the navigation entry
    // is gated on (config/navigation/opportunities.php). The role keeps
    // `.create`, which carries no menu entry. `/enrollee-management` joined
    // with the user directive 2026-09-18 (read-only, own enrollees only).
    expect($routes)->toBe(['/dashboard', '/request-management', '/enrollee-management']);
});

it('blocks the commercial role server-side on the modules its menu hides', function () {
    $this->seed(QualificaOperatorSeeder::class);

    Sanctum::actingAs(User::query()->where('email', 'marco.baldi@qualificagroup.com')->firstOrFail());

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
 * QualificaRoleSeeder would close this residual outright — a seed change, still
 * pending an explicit decision, hence this test pins today's behaviour.
 */
it('leaves the select-only resources list-readable, writes excluded', function () {
    $this->seed(QualificaOperatorSeeder::class);

    Sanctum::actingAs(User::query()->where('email', 'marco.baldi@qualificagroup.com')->firstOrFail());
    $this->getJson('/api/tables/registries/columns')->assertOk();

    Sanctum::actingAs(User::query()->where('email', 'rosa.falzarano@qualificagroup.com')->firstOrFail());
    $this->getJson('/api/tables/users/columns')->assertOk();
    // The writes behind them are still refused (`referent-types.create`).
    $this->postJson('/api/referent-types', ['name' => 'Nope'])->assertForbidden();
});

it('blocks the supervisor server-side on administration and configuration', function () {
    $this->seed(QualificaOperatorSeeder::class);

    Sanctum::actingAs(User::query()->where('email', 'rosa.falzarano@qualificagroup.com')->firstOrFail());

    // The modules it does not hold answer nothing.
    // `opportunities` left this list with the lead conversion grant (user
    // directive 2026-09-16), pinned in QualificaLeadConversionPermissionTest.
    foreach (['company-sites', 'custom-fields'] as $domain) {
        $this->getJson("/api/tables/{$domain}/columns")->assertForbidden();
        $this->postJson("/api/tables/{$domain}/rows", ['startRow' => 0, 'endRow' => 25])->assertForbidden();
    }

    // The option list of a closed module still answers (ADR 0011 amended
    // 2026-07-31): browsing the module and filling a select are distinct.
    $this->getJson('/api/companies/for-select')->assertOk();

    // The modules of its mansione do answer.
    $this->getJson('/api/tables/request-management/columns')->assertOk();
    $this->getJson('/api/tables/products/columns')->assertOk();
    $this->getJson('/api/tables/leads/columns')->assertOk();

    // Utenti and Ruoli answer too (user directive 2026-09-18), creation aside.
    foreach (['users', 'roles'] as $domain) {
        $this->getJson("/api/tables/{$domain}/columns")->assertOk();
        $this->postJson("/api/tables/{$domain}/rows", ['startRow' => 0, 'endRow' => 25])->assertOk();
    }

    $this->postJson('/api/roles', ['name' => 'nuovo-ruolo'])->assertForbidden();
});
