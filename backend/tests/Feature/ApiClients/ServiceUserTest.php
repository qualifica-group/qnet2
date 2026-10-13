<?php

use App\Models\ApiClient;
use App\Models\User;
use App\Services\System\OnlineUsersService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

function serviceUserAdmin(): User
{
    foreach (['viewAny', 'view', 'update', 'delete', 'impersonate'] as $ability) {
        Permission::findOrCreate("users.{$ability}");
    }

    return User::factory()->create()->givePermissionTo('users.viewAny', 'users.view', 'users.update', 'users.delete');
}

it('adds the schema columns of the client model', function () {
    expect(Schema::hasColumn('users', 'is_service_account'))->toBeTrue()
        ->and(Schema::hasColumn('api_clients', 'service_user_id'))->toBeTrue()
        ->and(Schema::hasColumn('api_clients', 'scopes'))->toBeFalse()
        ->and(Schema::hasColumn('personal_access_tokens', 'api_client_id'))->toBeTrue();
});

it('keeps service_user_id and is_service_account out of mass assignment', function () {
    $user = User::factory()->create();

    $client = new ApiClient(['name' => 'X', 'service_user_id' => $user->id]);
    $flagged = new User(['name' => 'Y', 'is_service_account' => true]);

    expect($client->service_user_id)->toBeNull()
        ->and($flagged->isServiceAccount())->toBeFalse();
});

it('cascades the client deletion to the tokens bound to it only', function () {
    $client = ApiClient::factory()->create();
    $user = User::factory()->create();
    $bound = createClientLoginToken($user, $client);
    $app = $user->createToken('app')->accessToken;

    $client->delete();

    expect(DB::table('personal_access_tokens')->where('id', explode('|', $bound)[0])->exists())->toBeFalse()
        ->and(DB::table('personal_access_tokens')->where('id', $app->id)->exists())->toBeTrue();
});

it('restricts the deletion of a user that is the technical user of a client', function () {
    $client = ApiClient::factory()->create();

    expect(fn () => $client->serviceUser->delete())->toThrow(QueryException::class);
});

// AC-009
it('is rejected by the app login with the generic message', function () {
    [$client] = createApiClientWithKey();

    $this->postJson('/api/auth/login', ['email' => $client->serviceUser->email, 'password' => 'password'])
        ->assertUnprocessable()
        ->assertJsonPath('errors.email', [__('auth.failed')]);
});

it('gets no reset email and cannot reset or set a password', function () {
    Notification::fake();
    [$client] = createApiClientWithKey();
    $email = $client->serviceUser->email;
    $before = $client->serviceUser->password;

    $this->postJson('/api/auth/forgot-password', ['email' => $email])->assertOk()->assertJsonPath('success', true);
    Notification::assertNothingSent();

    $broker = Password::broker();
    $token = $broker->createToken($client->serviceUser);
    $body = ['email' => $email, 'token' => $token, 'password' => 'NewPassw0rd!x', 'password_confirmation' => 'NewPassw0rd!x'];

    $this->postJson('/api/auth/reset-password', $body)->assertUnprocessable();
    $this->postJson('/api/auth/set-password', $body)->assertUnprocessable();

    expect($client->serviceUser->fresh()->password)->toBe($before);
});

it('is absent from the users table, for-select and 404 on the users routes', function () {
    [$client] = createApiClientWithKey();
    $serviceUserId = $client->service_user_id;
    $human = User::factory()->create(['name' => 'Zed Human']);
    Sanctum::actingAs(serviceUserAdmin());

    $rows = $this->postJson('/api/tables/users/rows', ['startRow' => 0, 'endRow' => 100])->assertOk()->json('items');
    expect(collect($rows)->pluck('id')->all())->toContain($human->id)->not->toContain($serviceUserId);

    $options = $this->getJson('/api/users/for-select?limit=100')->assertOk()->json('items');
    expect(collect($options)->pluck('id')->all())->toContain($human->id)->not->toContain($serviceUserId);

    $this->getJson("/api/users/{$serviceUserId}")->assertNotFound();
    $this->putJson("/api/users/{$serviceUserId}", ['name' => 'Hijacked'])->assertNotFound();
    $this->deleteJson("/api/users/{$serviceUserId}")->assertNotFound();
    $this->getJson("/api/users/{$human->id}")->assertOk();

    expect($client->serviceUser->fresh()->name)->not->toBe('Hijacked');
});

it('does not show up among the online users, nor do the tokens issued through a client', function () {
    [$client, $key] = createApiClientWithKey();
    $clientUser = User::factory()->create();
    $appUser = User::factory()->create();

    createClientLoginToken($clientUser, $client);
    $appUser->createToken('app');
    DB::table('personal_access_tokens')->update(['last_used_at' => now()]);

    $online = collect(app(OnlineUsersService::class)->handle()['users'])->pluck('id')->all();

    expect($online)->toBe([$appUser->id]);
});
