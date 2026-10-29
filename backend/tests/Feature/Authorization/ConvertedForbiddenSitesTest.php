<?php

use App\Models\Referent;
use App\Models\User;
use App\Services\Skills\UserSkillVisibility;
use App\Tables\ContractsTableDefinition;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// Spec 0328 (D-7): the abort(403) / AuthorizationException sites of app/ now say why.
uses(RefreshDatabase::class);

function convertedSiteActor(): User
{
    foreach (['rewarded-referents.view', 'org-chart.view', 'notes.create'] as $name) {
        Permission::findOrCreate($name, 'web');
    }

    $user = User::factory()->create();
    Sanctum::actingAs($user);

    return $user;
}

it('a permission gate converted from abort_unless names the missing permission', function (string $uri, string $permission) {
    convertedSiteActor();

    $this->getJson($uri, ['Accept-Language' => 'en'])
        ->assertForbidden()
        ->assertExactJson([
            'success' => false,
            'message' => "You do not have the «{$permission}» permission.",
            'authorization' => ['reason' => 'missing_permission', 'permission' => $permission],
        ]);
})->with([
    'referent rewards' => fn () => ['/api/referents/'.Referent::factory()->create()->id.'/rewards', 'rewarded-referents.view'],
    'org chart' => fn () => ['/api/org-chart/functions', 'org-chart.view'],
]);

it('a Gate::allowIf site keeps the permission it checked', function () {
    convertedSiteActor();
    Route::middleware(['api', 'auth:sanctum'])->get('/api/_converted_allow_if', function () {
        $actor = request()->user();

        Gate::allowIf($actor->can('org-chart.view'));
    });

    $this->getJson('/api/_converted_allow_if')
        ->assertForbidden()
        ->assertJsonPath('authorization.reason', 'missing_permission')
        ->assertJsonPath('authorization.permission', 'org-chart.view');
});

it('a domain rule refusal answers denied with its own reason', function (Closure $refuse, string $message) {
    convertedSiteActor();
    Route::middleware(['api', 'auth:sanctum'])->get('/api/_converted_rule', $refuse);

    $this->getJson('/api/_converted_rule', ['Accept-Language' => 'en'])
        ->assertForbidden()
        ->assertExactJson([
            'success' => false,
            'message' => $message,
            'authorization' => ['reason' => 'denied', 'permission' => null],
        ]);
})->with([
    'contract manual delete' => [
        fn () => app(ContractsTableDefinition::class)->deleteModel(new class extends Model {}),
        'A contract cannot be deleted manually.',
    ],
    'skills of another person' => [
        fn () => app(UserSkillVisibility::class)->assertCanWritePerson(request()->user(), User::factory()->create()->id),
        'You cannot edit the skills of this person.',
    ],
]);

it('a domain rule refusal answers in Italian', function () {
    convertedSiteActor();
    Route::middleware(['api', 'auth:sanctum'])->get('/api/_converted_rule_it', fn () => app(ContractsTableDefinition::class)->deleteModel(new class extends Model {}));

    $this->getJson('/api/_converted_rule_it', ['Accept-Language' => 'it'])
        ->assertForbidden()
        ->assertJsonPath('message', 'Un contratto non può essere eliminato manualmente.');
});
