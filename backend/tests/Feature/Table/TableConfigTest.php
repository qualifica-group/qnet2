<?php

use App\Models\Address;
use App\Models\City;
use App\Models\Country;
use App\Models\PersonalData;
use App\Models\State;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * Create the standard users.* permissions and return a freshly-created user
 * granted the requested subset. Guarded so it can live alongside the other
 * Users feature test files in the same Pest run.
 *
 * @param  array<int, string>  $abilities  e.g. ['viewAny', 'view', 'update', 'delete']
 */
if (! function_exists('userWithUserAbilities')) {
    function userWithUserAbilities(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete'] as $ability) {
            Permission::findOrCreate("users.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("users.{$ability}");
        }

        return $user;
    }
}

it('requires authentication on the config endpoint', function () {
    $this->getJson('/api/tables/users/columns')->assertUnauthorized();
});

it('returns 404 on the config endpoint for an unregistered domain', function () {
    $user = userWithUserAbilities(['viewAny']);
    Sanctum::actingAs($user);

    // Only config-mapped domains resolve; anything else is unreachable.
    // 404 must carry the uniform fail() envelope ({success:false, message}).
    $this->getJson('/api/tables/nonexistent-domain/columns')
        ->assertNotFound()
        ->assertJson(['success' => false])
        ->assertJsonStructure(['success', 'message']);
});

it('returns 403 on config without users.viewAny', function () {
    $user = userWithUserAbilities([]); // no abilities
    Sanctum::actingAs($user);

    $this->getJson('/api/tables/users/columns')->assertForbidden();
});

it('returns the resolved config for a user with users.viewAny', function () {
    $user = userWithUserAbilities(['viewAny']);
    Sanctum::actingAs($user);

    $response = $this->getJson('/api/tables/users/columns')
        ->assertOk()
        ->assertJsonPath('success', true);

    $data = $response->json('data');

    expect($data['resource'])->toBe('users')
        ->and($data['defaultPagination']['limit'])->toBe(25)
        ->and($data['defaultSort'])->toBe([['columnId' => 'created_at', 'direction' => 'desc']])
        // Global quick-search allow-list (spec 0009): real columns only.
        ->and($data['searchable'])->toBe(['name', 'email']);
});

it('exposes roles and locale options as flat string[] arrays', function () {
    Role::create(['name' => 'editor']);
    Role::create(['name' => 'manager']);

    $user = userWithUserAbilities(['viewAny']);
    Sanctum::actingAs($user);

    $data = $this->getJson('/api/tables/users/columns')->json('data');

    $rolesColumn = collect($data['columns'])->firstWhere('id', 'roles');
    $localeColumn = collect($data['columns'])->firstWhere('id', 'locale');

    // roles options: flat string[] from real Spatie roles.
    expect($rolesColumn['options'])->toBeArray()
        ->and($rolesColumn['options'])->each->toBeString()
        ->and($rolesColumn['options'])->toEqualCanonicalizing(['editor', 'manager']);

    // locale options: flat string[].
    expect($localeColumn['options'])->toBe(['en', 'it'])
        ->and($localeColumn['options'])->each->toBeString();

    // roles filter is resolved to options (no dangling optionsSource).
    $rolesFilter = collect($data['filters'])->firstWhere('columnId', 'roles');
    expect($rolesFilter)->not->toHaveKey('optionsSource')
        ->and($rolesFilter['options'])->toEqualCanonicalizing(['editor', 'manager']);
});

it('exposes filterType in the resolved config so the frontend can pick the widget', function () {
    $user = userWithUserAbilities(['viewAny']);
    Sanctum::actingAs($user);

    $data = $this->getJson('/api/tables/users/columns')->json('data');

    // filterType is part of the column contract (drives the frontend filter
    // widget): every column carries the key, null when no filter applies.
    foreach ($data['columns'] as $column) {
        expect($column)->toHaveKey('filterType');
    }

    $columns = collect($data['columns'])->keyBy('id');

    // A text-rendered geo column nonetheless advertises a `set` filter — only
    // possible because filterType is decoupled from the render `type`.
    expect($columns['country']['type'])->toBe('text')
        ->and($columns['country']['filterType'])->toBe('set')
        ->and($columns['name']['filterType'])->toBe('text')
        ->and($columns['created_at']['filterType'])->toBe('date')
        // spec 0004: `id` is now filterable with a `number` filter (equals/
        // range/comparisons via TableService's number branch); `avatar_url`
        // is the derived, genuinely non-filterable column.
        ->and($columns['id']['filterType'])->toBe('number')
        ->and($columns['avatar_url']['filterType'])->toBeNull();
});

it('exposes hasFilterValues so the frontend knows which columns support the Set Filter (spec 0004/0005)', function () {
    $user = userWithUserAbilities(['viewAny']);
    Sanctum::actingAs($user);

    $data = $this->getJson('/api/tables/users/columns')->json('data');

    foreach ($data['columns'] as $column) {
        expect($column)->toHaveKey('hasFilterValues');
    }

    $columns = collect($data['columns'])->keyBy('id');

    // Enumerable columns (static/derived catalogue) advertise a value list.
    // spec 0005: `primary_contact` (COMPUTED) also advertises one — its
    // distinct-values resolver is a real DB query (see UserPersonalDataColumns).
    // `primary_address` is CONDITIONS-ONLY by deliberate UX decision (a
    // formatted address string has no clean single-column match): it declares
    // hasFilterValues=false, same as a genuinely non-filterable column.
    expect($columns['name']['hasFilterValues'])->toBeTrue()
        ->and($columns['id']['hasFilterValues'])->toBeTrue()
        ->and($columns['roles']['hasFilterValues'])->toBeTrue()
        ->and($columns['country']['hasFilterValues'])->toBeTrue()
        ->and($columns['primary_contact']['hasFilterValues'])->toBeTrue()
        ->and($columns['primary_address']['hasFilterValues'])->toBeFalse()
        // Non-filterable columns default to false (no filter at all).
        ->and($columns['avatar_url']['hasFilterValues'])->toBeFalse();
});

it('exposes hasFilterValues=true for the roles users_count AGGREGATE column', function () {
    Permission::findOrCreate('roles.viewAny');
    $user = User::factory()->create();
    $user->givePermissionTo('roles.viewAny');
    Sanctum::actingAs($user);

    $data = $this->getJson('/api/tables/roles/columns')->json('data');
    $columns = collect($data['columns'])->keyBy('id');

    expect($columns['users_count']['hasFilterValues'])->toBeTrue();
});

it('offers only assignable form-module permissions in the roles permissions catalogue', function () {
    Permission::findOrCreate('roles.viewAny');
    foreach (['companies.view', 'operational-sites.create', 'notes.create', 'attachments.create', 'addresses.view', 'contacts.update'] as $name) {
        Permission::findOrCreate($name);
    }
    $user = User::factory()->create();
    $user->givePermissionTo('roles.viewAny');
    Sanctum::actingAs($user);

    $data = $this->getJson('/api/tables/roles/columns')->json('data');
    $permissionsColumn = collect($data['columns'])->firstWhere('id', 'permissions');

    // Form-module permissions are offered, plus the permission-only resources
    // that own no form of their own (the agnostic `notes` and `attachments`
    // components); indirect sub-entity ones are not (they are governed via the
    // field-permission matrix on the parent form).
    expect($permissionsColumn['options'])
        ->toContain('companies.view', 'operational-sites.create', 'notes.create', 'attachments.create')
        ->and($permissionsColumn['options'])->not->toContain('addresses.view', 'contacts.update');
});

it('declares the new user_type/address/geo/contact columns with the right visibility', function () {
    $user = userWithUserAbilities(['viewAny']);
    Sanctum::actingAs($user);

    $data = $this->getJson('/api/tables/users/columns')->json('data');
    $columns = collect($data['columns'])->keyBy('id');

    foreach (['user_type', 'primary_address', 'country', 'region', 'province', 'city', 'primary_contact'] as $id) {
        expect($columns)->toHaveKey($id);
    }

    // Visible by default.
    expect($columns['user_type']['visible'])->toBeTrue()
        ->and($columns['primary_address']['visible'])->toBeTrue()
        ->and($columns['primary_contact']['visible'])->toBeTrue();

    // Geo columns hidden by default.
    expect($columns['country']['visible'])->toBeFalse()
        ->and($columns['region']['visible'])->toBeFalse()
        ->and($columns['province']['visible'])->toBeFalse()
        ->and($columns['city']['visible'])->toBeFalse();

    // All derived columns are both filterable and sortable.
    foreach (['user_type', 'primary_address', 'country', 'region', 'province', 'city', 'primary_contact'] as $id) {
        expect($columns[$id]['filterable'])->toBeTrue()
            ->and($columns[$id]['sortable'])->toBeTrue();
    }
});

it('emits badge metadata for user_type and never for plain columns', function () {
    $user = userWithUserAbilities(['viewAny']);
    Sanctum::actingAs($user);

    $data = $this->getJson('/api/tables/users/columns')->json('data');
    $columns = collect($data['columns'])->keyBy('id');

    $userType = $columns['user_type'];

    expect($userType['type'])->toBe('badge')
        // Plain string[] tokens for the set filter.
        ->and($userType['options'])->toBe(['individual', 'company'])
        // Rich per-value badge metadata for rendering.
        ->and($userType['badges'])->toBeArray()
        ->and($userType['badges'])->toHaveCount(2);

    $badges = collect($userType['badges'])->keyBy('value');

    expect($badges['individual'])->toMatchArray([
        'value' => 'individual',
        'color' => 'blue',
    ])
        ->and($badges['individual'])->toHaveKeys(['value', 'label', 'color', 'icon', 'is_default'])
        ->and($badges['company']['color'])->toBe('violet');

    // The badge column declares the domain-enum key so the frontend can localize
    // the label from its own i18n resources (enums.personal_data_type.*).
    expect($userType['enumKey'])->toBe('personal_data_type');

    // Backward-compat: non-badge columns never carry a `badges`/`enumKey` key, and
    // locale stays an `enum` column with its original flat options.
    expect($columns['roles'])->not->toHaveKey('badges')
        ->and($columns['roles'])->not->toHaveKey('enumKey')
        ->and($columns['locale'])->not->toHaveKey('badges')
        ->and($columns['locale'])->not->toHaveKey('enumKey')
        ->and($columns['locale']['type'])->toBe('enum')
        ->and($columns['locale']['options'])->toBe(['en', 'it']);
});

it('a non super-admin never sees super-admin among the assignable role options', function () {
    Role::create(['name' => 'super-admin']);
    Role::create(['name' => 'editor']);

    $user = userWithUserAbilities(['viewAny']);
    Sanctum::actingAs($user);

    $data = $this->getJson('/api/tables/users/columns')->json('data');
    $rolesColumn = collect($data['columns'])->firstWhere('id', 'roles');

    expect($rolesColumn['options'])->toBe(['editor'])
        ->and($rolesColumn['options'])->not->toContain('super-admin');
});

it('resolves geo set-filter options from the names actually in use', function () {
    $viewer = userWithUserAbilities(['viewAny']);

    // Two owners with primary addresses in distinct, ASCII-only geo (so the
    // ORDER BY name assertion is collation-independent). No provinces in use.
    $fixtures = [
        ['country' => 'France', 'region' => 'Bretagne', 'city' => 'Paris'],
        ['country' => 'Italy', 'region' => 'Lazio', 'city' => 'Rome'],
    ];

    foreach ($fixtures as $geo) {
        $country = Country::factory()->create(['name' => $geo['country']]);
        $state = State::factory()->for($country, 'country')->create(['name' => $geo['region']]);
        $city = City::factory()->forState($state)->create(['name' => $geo['city']]);

        $owner = User::factory()->create();
        $card = PersonalData::factory()->individual()->for($owner, 'personable')->create();
        Address::factory()->primary()->for($card, 'addressable')->create([
            'country_id' => $country->id,
            'state_id' => $state->id,
            'city_id' => $city->id,
            'province_id' => null,
        ]);
    }

    Sanctum::actingAs($viewer);
    $data = $this->getJson('/api/tables/users/columns')->json('data');
    $columns = collect($data['columns'])->keyBy('id');

    // Names in use, sorted; province has no values in use → empty.
    expect($columns['country']['options'])->toBe(['France', 'Italia'])
        ->and($columns['region']['options'])->toBe(['Bretagne', 'Lazio'])
        ->and($columns['city']['options'])->toBe(['Paris', 'Roma'])
        ->and($columns['province']['options'])->toBe([]);

    // The same options are mirrored onto the filter catalogue entries.
    $cityFilter = collect($data['filters'])->firstWhere('columnId', 'city');
    expect($cityFilter['options'])->toBe(['Paris', 'Roma']);
});

it('hides action keys the user has no permission for in the config catalogue', function () {
    // Only viewAny + view: edit/delete actions must NOT be advertised.
    $user = userWithUserAbilities(['viewAny', 'view']);
    Sanctum::actingAs($user);

    $data = $this->getJson('/api/tables/users/columns')->json('data');
    $actionKeys = collect($data['actions'])->pluck('key')->all();

    expect($actionKeys)->toContain('view')
        ->and($actionKeys)->not->toContain('edit')
        ->and($actionKeys)->not->toContain('delete');

    // No leaked `permission` metadata in the resolved catalogue.
    foreach ($data['actions'] as $action) {
        expect($action)->not->toHaveKey('permission');
    }
});

it('exposes all action keys to a user with the full ability set', function () {
    $user = userWithUserAbilities(['viewAny', 'view', 'update', 'delete']);
    Sanctum::actingAs($user);

    $data = $this->getJson('/api/tables/users/columns')->json('data');
    $actionKeys = collect($data['actions'])->pluck('key')->all();

    expect($actionKeys)->toEqualCanonicalizing(['view', 'edit', 'delete']);
});

/**
 * Grant a freshly-created user the `<domain>.viewAny` ability, so a
 * non-users table's config endpoint can be exercised for the default
 * hidden `id` column injection (InjectsDefaultIdColumn).
 */
if (! function_exists('userWithViewAnyOn')) {
    function userWithViewAnyOn(string $domain): User
    {
        Permission::findOrCreate("{$domain}.viewAny");

        $user = User::factory()->create();
        $user->givePermissionTo("{$domain}.viewAny");

        return $user;
    }
}

it('injects a default hidden id column into a table that does not declare one', function () {
    Sanctum::actingAs(userWithViewAnyOn('tags'));

    $idColumns = collect($this->getJson('/api/tables/tags/columns')->json('data.columns'))
        ->where('id', 'id')
        ->values();

    expect($idColumns)->toHaveCount(1);

    expect($idColumns->first())
        ->label->toBe('table.columns.id')
        ->type->toBe('number')
        ->visible->toBeFalse()
        ->sortable->toBeTrue()
        // Not filterable by default (see InjectsDefaultIdColumn): a table that
        // wants a filterable id declares its own (users/companies do).
        ->filterable->toBeFalse()
        ->filterType->toBeNull();
});

it('prepends the injected id column as the first column', function () {
    Sanctum::actingAs(userWithViewAnyOn('tags'));

    $columns = collect($this->getJson('/api/tables/tags/columns')->json('data.columns'));

    // The injected id is the leftmost column, with order 1.
    expect($columns->first()['id'])->toBe('id')
        ->and($columns->first()['order'])->toBe(1);
});

it('does not duplicate the id column when a table already declares its own', function () {
    Sanctum::actingAs(userWithUserAbilities(['viewAny']));

    $idColumns = collect($this->getJson('/api/tables/users/columns')->json('data.columns'))
        ->where('id', 'id')
        ->values();

    // users declares its own id column — the injector must not append a second
    // one, and the table's own label wins over the default.
    expect($idColumns)->toHaveCount(1)
        ->and($idColumns->first()['label'])->toBe('users.columns.id');
});

it('keeps a table-declared id column authoritative over the default (its own label wins)', function () {
    Sanctum::actingAs(userWithViewAnyOn('roles'));

    $id = collect($this->getJson('/api/tables/roles/columns')->json('data.columns'))
        ->firstWhere('id', 'id');

    // roles declares its own id column: its label (not the default
    // `table.columns.id`) proves the declared column wins over the injector.
    expect($id['label'])->toBe('roles.columns.id');
});
