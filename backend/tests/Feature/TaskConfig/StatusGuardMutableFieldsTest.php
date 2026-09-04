<?php

use App\Http\Requests\ContractStatuses\StoreContractStatusRequest;
use App\Http\Requests\ContractStatuses\UpdateContractStatusRequest;
use App\Http\Requests\PipelineStatuses\StorePipelineStatusRequest;
use App\Http\Requests\PipelineStatuses\UpdatePipelineStatusRequest;
use App\Http\Requests\RewardStatuses\StoreRewardStatusRequest;
use App\Http\Requests\RewardStatuses\UpdateRewardStatusRequest;
use App\Models\ContractStatus;
use App\Models\PipelineStatus;
use App\Models\RewardStatus;
use App\Models\User;
use App\Services\Statuses\SystemStatusGuard;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| AC-057 — the invariant AC-048 rests on
|--------------------------------------------------------------------------
|
| Spec 0101 widened SystemStatusGuard::MUTABLE_SYSTEM_FIELDS from
| ['name','color'] to ['name','color','icon','completion_percentage'] for
| ALL FOUR status types at once — contract, pipeline and reward included,
| since the guard is shared.
|
| Nothing stops those two new keys from being mutated on one of THEIR system
| rows except a single fact: their FormRequests never DECLARE `icon` or
| `completion_percentage`, so validated() drops the keys and they never reach
| the guard. That fact is the entire protection, and it was unasserted: the
| day someone adds `icon` to ContractStatus's rules(), it becomes silently
| mutable on a protected row and every existing test stays green.
|
| The existing suites (176 of them) cover the guard's BEHAVIOUR. This file
| covers the precondition that makes that behaviour safe.
*/

/**
 * A FormRequest instantiated far enough to answer rules(). The Update
 * requests read `$this->route(...)` to build their unique-ignore rule, so a
 * bound route parameter is required — without it rules() fatals.
 */
function statusRequestRules(string $requestClass, string $parameterName, ?object $model): array
{
    /** @var FormRequest $request */
    $request = $requestClass::create('/stub', $model === null ? 'POST' : 'PATCH');

    $route = new Route([$model === null ? 'POST' : 'PATCH'], 'stub/{'.$parameterName.'}', static fn () => null);
    $route->bind(Request::create('/stub', $model === null ? 'POST' : 'PATCH'));
    $route->setParameter($parameterName, $model);

    $request->setRouteResolver(static fn (): Route => $route);

    return array_keys($request->rules());
}

/**
 * The three PRE-EXISTING status configurators and the route parameter their
 * update request binds. `task-statuses` is deliberately absent: there the
 * two keys are legitimately declared, which is the whole point of the
 * widening.
 *
 * @return array<string, array{0: string, 1: string, 2: string, 3: string}>
 */
dataset('preExistingStatusModules', [
    'contract-statuses' => [StoreContractStatusRequest::class, UpdateContractStatusRequest::class, 'contractStatus', ContractStatus::class, 'contract-statuses'],
    'pipeline-statuses' => [StorePipelineStatusRequest::class, UpdatePipelineStatusRequest::class, 'pipelineStatus', PipelineStatus::class, 'pipeline-statuses'],
    'reward-statuses' => [StoreRewardStatusRequest::class, UpdateRewardStatusRequest::class, 'rewardStatus', RewardStatus::class, 'reward-statuses'],
]);

it('AC-057: neither store nor update declares icon or completion_percentage, so those keys never reach the shared guard', function (string $storeClass, string $updateClass, string $parameterName, string $modelClass, string $resource) {
    $storeKeys = statusRequestRules($storeClass, $parameterName, null);
    $updateKeys = statusRequestRules($updateClass, $parameterName, $modelClass::factory()->create());

    foreach (['icon', 'completion_percentage'] as $widenedKey) {
        expect($storeKeys)->not->toContain($widenedKey, "{$storeClass} declares {$widenedKey}: it can now reach SystemStatusGuard, which permits it on a system row")
            ->and($updateKeys)->not->toContain($widenedKey, "{$updateClass} declares {$widenedKey}: it can now reach SystemStatusGuard, which permits it on a system row");
    }

    // Not vacuous: the same call does see the keys these modules DO declare.
    expect($storeKeys)->toContain('name')->toContain('color')
        ->and($updateKeys)->toContain('name');
})->with('preExistingStatusModules');

it('AC-057: the widened MUTABLE_SYSTEM_FIELDS really would permit those two keys, which is what the precondition guards against', function () {
    // Reading the constant rather than trusting the comment: this is the
    // half that makes the test above load-bearing instead of decorative. If
    // the guard ever narrows back, this assertion fails and the test above
    // can be retired rather than left as a mystery.
    $reflected = new ReflectionClass(SystemStatusGuard::class);
    $mutable = $reflected->getConstant('MUTABLE_SYSTEM_FIELDS');

    expect($mutable)->toBe(['name', 'color', 'icon', 'completion_percentage']);
});

it('AC-048: the guard still refuses a non-mutable key on a system row, and still admits the mutable ones', function () {
    // The behaviour the existing suites rely on, restated once here so this
    // file fails as a unit if the widening ever loosened the guard itself
    // rather than only its field list.
    //
    // Deliberately NOT asserting the refusal MESSAGE. That sentence
    // ("System statuses accept only name and color changes.") is factually
    // wrong now that the guard admits four fields, and is kept only because
    // nine existing assertions across pipeline/contract/reward pin it
    // verbatim and AC-048 requires that suite green. Pinning it a tenth time
    // here would buy this test nothing and make the eventual correction more
    // expensive, so the assertion is on the STATUS and on the absence of a
    // write, which is what the criterion is actually about.
    $guard = new SystemStatusGuard;
    $systemRow = ContractStatus::query()->whereNotNull('system_key')->firstOrFail();
    $originalName = $systemRow->name;

    try {
        $guard->assertUpdatable($systemRow, ['is_active' => false]);
        $this->fail('the guard admitted a non-mutable key on a system row');
    } catch (HttpException $exception) {
        expect($exception->getStatusCode())->toBe(422);
    }

    // ...and the mutable set still passes, all four keys at once.
    $guard->assertUpdatable($systemRow, ['name' => 'Nuovo nome', 'color' => 'teal', 'icon' => 'flag', 'completion_percentage' => 55]);

    // The guard only decides; it never writes. Nothing moved either way.
    expect($systemRow->fresh()->name)->toBe($originalName);
});

// ---------------------------------------------------------------------------
// AC-048, as rectified in letter (build 2026-09-04)
// ---------------------------------------------------------------------------

it('AC-048: at HTTP level the three modules IGNORE the two keys — 200 with no effect, never a 422', function (string $storeClass, string $updateClass, string $parameterName, string $modelClass, string $resource) {
    // The rectified wording matters: an undeclared key is dropped by
    // validated(), so the response is 200 and the write is a no-op. It is
    // NOT a 422 — asserting a rejection here would pin a behaviour the
    // system does not have, which is exactly what the old letter did.
    foreach (['viewAny', 'view', 'create', 'update', 'delete'] as $ability) {
        Permission::findOrCreate("{$resource}.{$ability}");
    }

    $actor = User::factory()->create();
    $actor->givePermissionTo("{$resource}.update");

    $systemRow = $modelClass::query()->whereNotNull('system_key')->firstOrFail();
    $originalName = $systemRow->name;
    $originalColor = $systemRow->color;

    Sanctum::actingAs($actor);

    foreach ([['icon' => 'flag'], ['completion_percentage' => 55], ['icon' => 'flag', 'completion_percentage' => 55]] as $payload) {
        $this->patchJson("/api/{$resource}/{$systemRow->id}", $payload)->assertOk();
    }

    $fresh = $systemRow->fresh();

    expect($fresh->name)->toBe($originalName)
        ->and($fresh->color)->toBe($originalColor)
        ->and($fresh->getAttributes())->not->toHaveKey('icon')
        ->and($fresh->getAttributes())->not->toHaveKey('completion_percentage');
})->with('preExistingStatusModules');
