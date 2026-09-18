<?php

use App\Enums\PersonalDataTypeEnum;
use App\Models\BusinessFunction;
use App\Models\OperationalSite;
use App\Models\PersonalData;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\QuoteWorkflowStatus;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\QualificaCatalog\OperatorRoleCatalogue;
use Database\Seeders\QualificaCatalog\OperatorRoster;
use Database\Seeders\QualificaOperatorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;

// The client's real operators ("Mansionario Operatori", user directive
// 2026-09-15): account, role, Sedi and competence per roster row. The sites and
// the business functions come from the legacy import, so each test stands in
// the rows that import would have left.
uses(RefreshDatabase::class);

function seededOperator(string $email): User
{
    return User::query()->where('email', $email)->with('employment.operationalSites', 'employment.productLines')->firstOrFail();
}

/**
 * @param  array<int, string>  $aliases
 * @return array<string, int>
 */
function standInSites(array $aliases): array
{
    return collect($aliases)
        ->mapWithKeys(fn (string $alias): array => [$alias => OperationalSite::factory()->create(['alias' => $alias])->id])
        ->all();
}

it('creates every roster account with its role, standalone and without imported data', function (): void {
    test()->seed(QualificaOperatorSeeder::class);

    foreach (OperatorRoster::OPERATORS as [, , $email, , $role]) {
        expect(User::query()->where('email', $email)->firstOrFail()->getRoleNames()->all())->toBe([$role]);
    }

    expect(User::query()->count())->toBe(count(OperatorRoster::OPERATORS));
});

it('writes first and last name onto the anagrafica too, converging on a re-run', function (): void {
    test()->seed(QualificaOperatorSeeder::class);
    test()->seed(QualificaOperatorSeeder::class);

    $user = User::query()->where('email', 'mariaclelia.bernardi@qualificagroup.com')->with('personalData')->sole();

    expect($user->name)->toBe('Maria Clelia Bernardi')
        ->and($user->personalData->type)->toBe(PersonalDataTypeEnum::Individual)
        ->and($user->personalData->first_name)->toBe('Maria Clelia')
        ->and($user->personalData->last_name)->toBe('Bernardi')
        ->and(PersonalData::query()->count())->toBe(count(OperatorRoster::OPERATORS));
});

it('never seeds the accounts highlighted as non-existent', function (): void {
    test()->seed(QualificaOperatorSeeder::class);

    expect(User::query()->whereIn('name', ['Miriam Del Giudice', 'Maddalena Vitale', 'Elisa Finizio', 'Imma Pascale'])->exists())->toBeFalse()
        ->and(User::query()->count())->toBe(67);
});

it('sets the shared password on creation only, so an operator change survives a re-run', function (): void {
    test()->seed(QualificaOperatorSeeder::class);

    $user = User::query()->where('email', 'marco.baldi@qualificagroup.com')->firstOrFail();
    expect(Hash::check('Qualifica2026!', $user->password))->toBeTrue();

    $user->forceFill(['password' => Hash::make('changed-by-hand')])->save();
    test()->seed(QualificaOperatorSeeder::class);

    expect(Hash::check('changed-by-hand', $user->fresh()->password))->toBeTrue()
        ->and(User::query()->where('email', 'marco.baldi@qualificagroup.com')->count())->toBe(1);
});

it('expands each city to every site of it: first one physical, the rest remote', function (): void {
    $sites = standInSites(['FRATTAMAGGIORE 1 (HQ)', 'Frattamaggiore 2', 'Roma', 'Roma - 1', 'Milano', 'Pero', 'Cassino', 'Ragusa']);

    test()->seed(QualificaOperatorSeeder::class);

    $employment = seededOperator('gaetano.dellaporta@qualificagroup.com')->employment;

    expect($employment->primaryOperationalSiteId)->toBe($sites['FRATTAMAGGIORE 1 (HQ)'])
        ->and($employment->remoteOperationalSiteIds)->toEqualCanonicalizing([
            $sites['Frattamaggiore 2'], $sites['Roma'], $sites['Roma - 1'], $sites['Milano'], $sites['Cassino'],
        ]);
});

it('leaves a "no operatore" profile unassignable: physical Sede only, no competence, even when seeded as a wildcard before', function (): void {
    $sites = standInSites(['FRATTAMAGGIORE 1 (HQ)', 'Frattamaggiore 2', 'Roma']);
    $function = BusinessFunction::factory()->create();
    ProductCategory::factory()->create(['name' => 'Autoimpiego', 'business_function_id' => $function->id]);

    test()->seed(QualificaOperatorSeeder::class);
    seededOperator('rosa.falzarano@qualificagroup.com')->employment->update(['covers_all_product_categories' => true]);
    test()->seed(QualificaOperatorSeeder::class);

    $employment = seededOperator('rosa.falzarano@qualificagroup.com')->employment;

    expect($employment->primaryOperationalSiteId)->toBe($sites['FRATTAMAGGIORE 1 (HQ)'])
        ->and($employment->remoteOperationalSiteIds)->toBeEmpty()
        ->and($employment->covers_all_product_categories)->toBeFalse()
        ->and($employment->productLines)->toBeEmpty();
});

it('pairs every roster category with its effective business function, Consulenza never', function (): void {
    $formazione = BusinessFunction::factory()->create(['name' => 'FORMAZIONE']);
    $apl = BusinessFunction::factory()->create(['name' => 'APL']);
    $root = ProductCategory::factory()->create(['name' => 'Formazione', 'business_function_id' => $formazione->id]);
    $gol = ProductCategory::factory()->childOf($root)->create(['name' => 'GOL - Campania']);
    $selfFunded = ProductCategory::factory()->childOf($root)->create(['name' => 'Autofinanziato']);
    $selfEmployment = ProductCategory::factory()->childOf($root)->create(['name' => 'Autoimpiego']);
    $aplRoot = ProductCategory::factory()->create(['name' => 'APL', 'business_function_id' => $apl->id]);
    ProductCategory::factory()->create(['name' => 'Consulenza']);

    test()->seed(QualificaOperatorSeeder::class);

    $lines = seededOperator('luca.romano@qualificagroup.com')->employment->productLines
        ->map(fn ($line): array => [$line->business_function_id, $line->product_category_id])
        ->all();

    expect($lines)->toEqualCanonicalizing([
        [$formazione->id, $gol->id],
        [$formazione->id, $selfFunded->id],
        [$formazione->id, $selfEmployment->id],
        [$apl->id, $aplRoot->id],
    ]);

    // A Consulenza-only commercial is left with no competence row at all.
    expect(seededOperator('marco.baldi@qualificagroup.com')->employment->productLines)->toBeEmpty();
});

it('converges on a re-run: no duplicated memberships nor competence rows', function (): void {
    standInSites(['FRATTAMAGGIORE 1 (HQ)', 'Frattamaggiore 2', 'Roma', 'Milano', 'Cassino']);
    $function = BusinessFunction::factory()->create();
    ProductCategory::factory()->create(['name' => 'Autoimpiego', 'business_function_id' => $function->id]);

    test()->seed(QualificaOperatorSeeder::class);
    test()->seed(QualificaOperatorSeeder::class);

    $employment = seededOperator('antonio.alvoni@qualificagroup.com')->employment;

    expect($employment->operationalSites)->toHaveCount(2)
        ->and($employment->productLines)->toHaveCount(1);
});

// User directive 2026-09-18: the reach in Gestione Iscritti mirrors the reach in
// Gestione Richieste — own requests only -> own enrollees only; Sedi -> Sedi;
// every request -> every enrollee. The enrollee commercial lost `viewSite`.
it('grants the enrollee commercial read-only enrollees of their own requests, and the teaching supervisor both modules by Sede', function (): void {
    test()->seed(QualificaOperatorSeeder::class);

    $enrollee = User::query()->where('email', 'marco.fedele@qualificagroup.com')->firstOrFail();

    foreach (['viewAny', 'view'] as $ability) {
        expect($enrollee->can("enrollee-management.{$ability}"))->toBeTrue($ability);
    }

    foreach (['update', 'viewAll', 'viewSite', 'export', 'report'] as $ability) {
        expect($enrollee->can("enrollee-management.{$ability}"))->toBeFalse($ability);
    }

    expect($enrollee->can('request-management.viewSite'))->toBeFalse()
        ->and($enrollee->can('request-management.report'))->toBeFalse();

    $teaching = seededOperator('marlena.jaruga@qualificagroup.com');

    expect($teaching->employment->productLines)->toBeEmpty()
        ->and($teaching->can('request-management.viewSite'))->toBeTrue()
        ->and($teaching->can('request-management.viewAll'))->toBeFalse()
        ->and($teaching->can('enrollee-management.viewSite'))->toBeTrue();
});

it('aligns every role reach in Gestione Iscritti to its reach in Gestione Richieste', function (): void {
    test()->seed(QualificaOperatorSeeder::class);

    foreach (array_keys(OperatorRoleCatalogue::ROLES) as $name) {
        $role = Role::findByName($name);

        if (! $role->hasPermissionTo('enrollee-management.viewAny')) {
            continue;
        }

        foreach (['viewAll', 'viewSite'] as $tier) {
            expect($role->hasPermissionTo("enrollee-management.{$tier}"))
                ->toBe($role->hasPermissionTo("request-management.{$tier}"), "{$name}: {$tier}");
        }
    }
});

it('lists in Gestione Iscritti exactly the rows each role reaches in Gestione Richieste', function (): void {
    $sites = standInSites(['FRATTAMAGGIORE 1 (HQ)', 'Frattamaggiore 2', 'Roma']);
    test()->seed(QualificaOperatorSeeder::class);

    $enrollee = User::query()->where('email', 'marco.fedele@qualificagroup.com')->firstOrFail();
    $teaching = User::query()->where('email', 'marlena.jaruga@qualificagroup.com')->firstOrFail();
    $supervisor = User::query()->where('email', 'rosa.falzarano@qualificagroup.com')->firstOrFail();

    $validated = QuoteWorkflowStatus::factory()->global()->system('validated')->create()->id;
    $own = Quote::factory()->create(['operator_id' => $enrollee->id, 'quote_workflow_status_id' => $validated]);
    // Roma is a Sede of both the enrollee commercial and the teaching supervisor.
    $sameSite = Quote::factory()->create(['operational_site_id' => $sites['Roma'], 'quote_workflow_status_id' => $validated]);
    $elsewhere = Quote::factory()->create(['quote_workflow_status_id' => $validated]);

    $rowIds = function (User $user): array {
        Sanctum::actingAs($user);

        return collect(test()->postJson('/api/tables/enrollee-management/rows', ['startRow' => 0, 'endRow' => 25])
            ->assertOk()
            ->json('items'))->pluck('id')->sort()->values()->all();
    };

    expect($rowIds($enrollee))->toBe([$own->id])
        ->and($rowIds($teaching))->toBe([$sameSite->id])
        ->and($rowIds($supervisor))->toBe(collect([$own->id, $sameSite->id, $elsewhere->id])->sort()->values()->all());
});

it('gives the coordinator unrestricted requests without the field-change-requests page', function (): void {
    test()->seed(QualificaOperatorSeeder::class);

    $coordinator = User::query()->where('email', 'michela.fabozzi@qualificagroup.com')->firstOrFail();

    expect($coordinator->can('request-management.viewAll'))->toBeTrue()
        ->and($coordinator->can('request-management.report'))->toBeTrue()
        ->and($coordinator->can('leads.viewAny'))->toBeTrue()
        ->and($coordinator->can('field-change-requests.view'))->toBeFalse()
        ->and($coordinator->can('field-change-requests.manage'))->toBeFalse();
});

it('deletes the retired English roles, detaching whoever still held them', function (): void {
    $retired = Role::findOrCreate('supervisor', 'web');
    Role::findOrCreate('commercial', 'web');
    $formerTester = User::factory()->create();
    $formerTester->assignRole($retired);

    test()->seed(QualificaOperatorSeeder::class);
    test()->seed(QualificaOperatorSeeder::class);

    expect(Role::query()->whereIn('name', ['supervisor', 'commercial'])->exists())->toBeFalse()
        ->and(Role::query()->where('name', 'marketing')->exists())->toBeTrue()
        ->and($formerTester->fresh()->getRoleNames()->all())->toBe([]);
});
