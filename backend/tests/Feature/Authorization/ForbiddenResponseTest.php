<?php

use App\Authorization\Denials\AuthorizationDenialLog;
use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// Spec 0327: every 403 of /api/* shares one envelope with an `authorization` block.
uses(RefreshDatabase::class);

function forbiddenActor(array $permissions = []): User
{
    foreach (['opportunities.delete', 'opportunities.viewAll', 'users.update'] as $name) {
        Permission::findOrCreate($name, 'web');
    }

    $user = User::factory()->create();
    $user->givePermissionTo($permissions);
    Sanctum::actingAs($user);

    return $user;
}

it('AC-001: a Policy ability denied reports the missing permission (it)', function () {
    forbiddenActor();
    $target = Opportunity::factory()->create();

    $this->deleteJson("/api/opportunities/{$target->id}", [], ['Accept-Language' => 'it'])
        ->assertForbidden()
        ->assertExactJson([
            'success' => false,
            'message' => 'Non hai il permesso «opportunities.delete».',
            'authorization' => ['reason' => 'missing_permission', 'permission' => 'opportunities.delete'],
        ]);
});

it('AC-006: the same denial answers in English', function () {
    forbiddenActor();
    $target = Opportunity::factory()->create();

    $this->deleteJson("/api/opportunities/{$target->id}", [], ['Accept-Language' => 'en'])
        ->assertForbidden()
        ->assertJsonPath('message', 'You do not have the «opportunities.delete» permission.')
        ->assertJsonPath('authorization.permission', 'opportunities.delete');
});

it('AC-002: a FormRequest authorize() denial keeps the envelope', function () {
    forbiddenActor();

    $this->postJson('/api/users/bulk-activation', ['is_active' => true, 'ids' => [1]], ['Accept-Language' => 'it'])
        ->assertForbidden()
        ->assertExactJson([
            'success' => false,
            'message' => 'Non hai il permesso «users.update».',
            'authorization' => ['reason' => 'missing_permission', 'permission' => 'users.update'],
        ]);
});

it('AC-005: an explicit reason beats a permission denied earlier in the request', function () {
    forbiddenActor();
    Route::middleware(['api', 'auth:sanctum'])->get('/api/_forbidden_explicit', function () {
        Gate::allows('opportunities.viewAll');

        abort(403, 'You cannot touch this record.');
    });

    $this->getJson('/api/_forbidden_explicit', ['Accept-Language' => 'en'])
        ->assertForbidden()
        ->assertExactJson([
            'success' => false,
            'message' => 'You cannot touch this record.',
            'authorization' => ['reason' => 'denied', 'permission' => null],
        ]);
});

it('a permission checked directly and then refused names itself', function () {
    forbiddenActor();
    Route::middleware(['api', 'auth:sanctum'])->get('/api/_forbidden_direct', function () {
        Gate::authorize('opportunities.delete');
    });

    $this->getJson('/api/_forbidden_direct')
        ->assertForbidden()
        ->assertJsonPath('authorization.permission', 'opportunities.delete');
});

it('a bare abort(403) after an allowed check is a generic denial', function () {
    forbiddenActor(['opportunities.viewAll']);
    Route::middleware(['api', 'auth:sanctum'])->get('/api/_forbidden_bare', function () {
        Gate::allows('opportunities.viewAll');

        abort(403);
    });

    $this->getJson('/api/_forbidden_bare', ['Accept-Language' => 'it'])
        ->assertForbidden()
        ->assertExactJson([
            'success' => false,
            'message' => 'Non sei autorizzato a eseguire questa operazione.',
            'authorization' => ['reason' => 'denied', 'permission' => null],
        ]);
});

it('AC-007: the super-admin middleware answers with the envelope and its reason', function () {
    forbiddenActor();

    $this->getJson('/api/system-health', ['Accept-Language' => 'it'])
        ->assertForbidden()
        ->assertExactJson([
            'success' => false,
            'message' => 'Sezione riservata al super-admin.',
            'authorization' => ['reason' => 'denied', 'permission' => null],
        ]);
});

describe('AuthorizationDenialLog', function () {
    it('blames the permission matching the denied Policy ability', function () {
        $log = new AuthorizationDenialLog;
        $log->evaluated('opportunities.viewAll', false);
        $log->evaluated('opportunities.delete', false);
        $log->evaluated('delete', false);

        expect($log->deniedPermission())->toBe('opportunities.delete');
    });

    it('falls back to the first cause when none matches the ability', function () {
        $log = new AuthorizationDenialLog;
        $log->evaluated('quotes.viewSite', false);
        $log->evaluated('quotes.viewAll', false);
        $log->evaluated('update', false);

        expect($log->deniedPermission())->toBe('quotes.viewSite');
    });

    it('resets the causes after every Policy evaluation', function () {
        $log = new AuthorizationDenialLog;
        $log->evaluated('opportunities.viewAll', false);
        $log->evaluated('view', true);

        expect($log->deniedPermission())->toBeNull();

        $log->evaluated('update', false);

        expect($log->deniedPermission())->toBeNull();
    });

    it('is a fresh instance per request scope', function () {
        app(AuthorizationDenialLog::class)->evaluated('a.b', false);
        app()->forgetScopedInstances();

        expect(app(AuthorizationDenialLog::class)->deniedPermission())->toBeNull();
    });
});
