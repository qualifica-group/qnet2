<?php

use App\Models\BusinessFunction;
use App\Models\OperationalSite;
use App\Models\ProductCategory;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\QualificaCatalog\OperatorRoster;
use Database\Seeders\QualificaOperatorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

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

    foreach (OperatorRoster::OPERATORS as [, $email, , $role]) {
        expect(User::query()->where('email', $email)->firstOrFail()->getRoleNames()->all())->toBe([$role]);
    }

    expect(User::query()->count())->toBe(count(OperatorRoster::OPERATORS));
});

it('sets the shared password on creation only, so an operator change survives a re-run', function (): void {
    test()->seed(QualificaOperatorSeeder::class);

    $user = User::query()->where('email', 'customer@qualificagroup.it')->firstOrFail();
    expect(Hash::check('Qualifica2026!', $user->password))->toBeTrue();

    $user->forceFill(['password' => Hash::make('changed-by-hand')])->save();
    test()->seed(QualificaOperatorSeeder::class);

    expect(Hash::check('changed-by-hand', $user->fresh()->password))->toBeTrue()
        ->and(User::query()->where('email', 'customer@qualificagroup.it')->count())->toBe(1);
});

it('expands each city to every site of it: first one physical, the rest remote', function (): void {
    $sites = standInSites(['FRATTAMAGGIORE 1 (HQ)', 'Frattamaggiore 2', 'Roma', 'Roma - 1', 'Milano', 'Pero', 'Cassino', 'Ragusa']);

    test()->seed(QualificaOperatorSeeder::class);

    $employment = seededOperator('g.dellaporta@qualificagroup.it')->employment;

    expect($employment->primaryOperationalSiteId)->toBe($sites['FRATTAMAGGIORE 1 (HQ)'])
        ->and($employment->remoteOperationalSiteIds)->toEqualCanonicalizing([
            $sites['Frattamaggiore 2'], $sites['Roma'], $sites['Roma - 1'], $sites['Milano'], $sites['Cassino'],
        ]);
});

it('keeps only the physical Sede and the wildcard competence for a "Tutte" profile', function (): void {
    standInSites(['FRATTAMAGGIORE 1 (HQ)', 'Frattamaggiore 2']);

    test()->seed(QualificaOperatorSeeder::class);

    $employment = seededOperator('commercialegol@qualificagroup.it')->employment;

    expect($employment->operationalSites)->toHaveCount(1)
        ->and($employment->covers_all_product_categories)->toBeTrue()
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

    $lines = seededOperator('casalnuovo@qualificagroup.it')->employment->productLines
        ->map(fn ($line): array => [$line->business_function_id, $line->product_category_id])
        ->all();

    expect($lines)->toEqualCanonicalizing([
        [$formazione->id, $gol->id],
        [$formazione->id, $selfFunded->id],
        [$formazione->id, $selfEmployment->id],
        [$apl->id, $aplRoot->id],
    ]);

    // A Consulenza-only commercial is left with no competence row at all.
    expect(seededOperator('customer@qualificagroup.it')->employment->productLines)->toBeEmpty();
});

it('converges on a re-run: no duplicated memberships nor competence rows', function (): void {
    standInSites(['FRATTAMAGGIORE 1 (HQ)', 'Frattamaggiore 2', 'Roma', 'Milano', 'Cassino']);
    $function = BusinessFunction::factory()->create();
    ProductCategory::factory()->create(['name' => 'Autoimpiego', 'business_function_id' => $function->id]);

    test()->seed(QualificaOperatorSeeder::class);
    test()->seed(QualificaOperatorSeeder::class);

    $employment = seededOperator('a.alvoni@qualificagroup.it')->employment;

    expect($employment->operationalSites)->toHaveCount(2)
        ->and($employment->productLines)->toHaveCount(1);
});

it('grants the enrollee commercial read-only enrollees of their Sedi, and the teaching supervisor both modules by Sede', function (): void {
    test()->seed(QualificaOperatorSeeder::class);

    $enrollee = User::query()->where('email', 'm.fedele@qualificagroup.it')->firstOrFail();

    foreach (['viewAny', 'view', 'viewSite'] as $ability) {
        expect($enrollee->can("enrollee-management.{$ability}"))->toBeTrue($ability);
    }

    foreach (['update', 'viewAll', 'export', 'report'] as $ability) {
        expect($enrollee->can("enrollee-management.{$ability}"))->toBeFalse($ability);
    }

    expect($enrollee->can('request-management.viewSite'))->toBeFalse()
        ->and($enrollee->can('request-management.report'))->toBeFalse();

    $teaching = User::query()->where('email', 'roma@qualificagroup.it')->firstOrFail();

    expect($teaching->can('request-management.viewSite'))->toBeTrue()
        ->and($teaching->can('request-management.viewAll'))->toBeFalse()
        ->and($teaching->can('enrollee-management.viewSite'))->toBeTrue();
});

it('gives the coordinator unrestricted requests without the field-change-requests page', function (): void {
    test()->seed(QualificaOperatorSeeder::class);

    $coordinator = User::query()->where('email', 'commerciale@qualificagroup.it')->firstOrFail();

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
