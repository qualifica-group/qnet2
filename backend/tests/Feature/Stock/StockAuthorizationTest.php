<?php

use App\Authorization\AssignablePermissionCatalogue;
use App\Models\Role;
use App\Models\User;
use App\Services\NavigationService;
use Database\Seeders\QualificaCatalog\HrOfficeRoleCatalogue;
use Database\Seeders\QualificaCatalog\OperatorRoleCatalogue;
use Database\Seeders\QualificaCatalog\StockKeeperRoleCatalogue;
use Database\Seeders\QualificaRoleSeeder;
use Database\Seeders\QualificaStockKeeperSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

it('0307 AC-015: permissions:sync creates stock.viewAny and stock.manage and the Role form offers them', function () {
    $this->artisan('permissions:sync')->assertSuccessful();

    expect(Permission::query()->where('name', 'like', 'stock.%')->pluck('name')->sort()->values()->all())
        ->toBe(['stock.manage', 'stock.viewAny']);

    expect(app(AssignablePermissionCatalogue::class)->isAssignable('stock.viewAny'))->toBeTrue()
        ->and(app(AssignablePermissionCatalogue::class)->isAssignable('stock.manage'))->toBeTrue();

    $superAdmin = User::factory()->create();
    $superAdmin->assignRole(Role::findOrCreate('super-admin', 'web'));
    Sanctum::actingAs($superAdmin);

    $tree = $this->getJson('/api/authorization/permission-catalogue')->assertOk()->json();

    expect(json_encode($tree))->toContain('stock');
});

it('0307 AC-015: the keeper role holds only stock.*, admin receives them, the HR role and the mansioni do not', function () {
    $this->seed(QualificaRoleSeeder::class);

    $keeper = Role::findByName(StockKeeperRoleCatalogue::ROLE);
    expect($keeper->permissions->pluck('name')->sort()->values()->all())->toBe(['stock.manage', 'stock.viewAny'])
        ->and($keeper->fieldPermissions()->exists())->toBeFalse()
        ->and(Role::findByName(OperatorRoleCatalogue::ADMIN_ROLE)->hasPermissionTo('stock.manage'))->toBeTrue()
        ->and(Role::findByName(HrOfficeRoleCatalogue::ROLE)->hasPermissionTo('stock.viewAny'))->toBeFalse();

    foreach (array_keys(OperatorRoleCatalogue::ROLES) as $mansione) {
        expect(Role::findByName($mansione)->hasPermissionTo('stock.viewAny'))->toBeFalse($mansione);
    }
});

it('0307 AC-015: the seeder gives the keeper role on top of the mansione, skips a missing account and converges', function () {
    $this->seed(QualificaRoleSeeder::class);
    [$email] = StockKeeperRoleCatalogue::USERS;

    $this->seed(QualificaStockKeeperSeeder::class);
    expect(User::query()->where('email', $email)->exists())->toBeFalse();

    $flavio = User::factory()->create(['email' => $email]);
    $flavio->assignRole(OperatorRoleCatalogue::BASE_ROLE);

    $this->seed(QualificaStockKeeperSeeder::class);
    $this->seed(QualificaStockKeeperSeeder::class);

    expect($flavio->fresh()->getRoleNames()->sort()->values()->all())
        ->toBe([StockKeeperRoleCatalogue::ROLE, OperatorRoleCatalogue::BASE_ROLE]);
});

it('0307 AC-015: the Dotazioni menu entry shows only with stock.viewAny', function () {
    $this->seed(QualificaRoleSeeder::class);

    Sanctum::actingAs(User::factory()->create());
    expect(visibleRoutes(auth()->user()))->not->toContain('/stock');

    $keeper = User::factory()->create();
    $keeper->assignRole(StockKeeperRoleCatalogue::ROLE);
    Sanctum::actingAs($keeper);

    expect(visibleRoutes($keeper))->toContain('/stock');
});

it('0307 AC-015: Dotazioni sits in the Prodotti group, alone for a keeper without catalogue rights', function () {
    $this->seed(QualificaRoleSeeder::class);

    $keeper = User::factory()->create();
    $keeper->assignRole(StockKeeperRoleCatalogue::ROLE);

    $productsGroup = collect(app(NavigationService::class)->for($keeper))->firstWhere('key', 'products-group');

    expect($productsGroup)->not->toBeNull()
        ->and(collect($productsGroup['children'])->pluck('key')->all())->toBe(['stock']);
});
