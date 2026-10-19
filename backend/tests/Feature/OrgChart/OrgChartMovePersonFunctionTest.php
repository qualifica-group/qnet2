<?php

use App\Models\BusinessFunction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

function functionMovePayload(BusinessFunction $from, BusinessFunction $to): array
{
    return ['from_function_id' => $from->id, 'to_function_id' => $to->id];
}

function memberOfFunctions(User $person, BusinessFunction ...$functions): User
{
    $person->businessFunctions()->attach(array_map(static fn (BusinessFunction $function): int => $function->id, $functions));

    return $person;
}

it('0275 AC-016: moves the membership from F1 to F2, keeps other functions and the competences, and logs', function () {
    [$f1, $f2, $f3] = BusinessFunction::factory()->count(3)->sequence(['name' => 'F1'], ['name' => 'F2'], ['name' => 'F3'])->create();
    $person = memberOfFunctions(orgChartPerson('Pat'), $f1, $f3);
    $person->employment->productLines()->create(['business_function_id' => $f1->id, 'product_category_id' => null]);
    Sanctum::actingAs(orgChartActor(['users.update']));

    $this->postJson("/api/org-chart/people/{$person->id}/function", functionMovePayload($f1, $f2))
        ->assertOk()->assertJsonPath('data', ['id' => $person->id, 'business_function_ids' => [$f2->id, $f3->id]]);

    $log = Activity::query()->where('log_name', 'org-chart')->sole();

    expect($person->businessFunctions()->pluck('business_functions.id')->sort()->values()->all())->toBe([$f2->id, $f3->id])
        ->and($person->employment->productLines()->pluck('business_function_id')->all())->toBe([$f1->id])
        ->and($log->event)->toBe('moved')
        ->and($log->subject_id)->toBe($person->id)
        ->and($log->properties->toArray())->toBe([
            'kind' => 'person_function',
            'from' => ['id' => $f1->id, 'name' => 'F1'],
            'to' => ['id' => $f2->id, 'name' => 'F2'],
        ]);
});

it('0275 AC-016: no duplicate row when the person is already in the destination', function () {
    [$f1, $f2] = BusinessFunction::factory()->count(2)->create();
    $person = memberOfFunctions(orgChartPerson('Pat'), $f1, $f2);
    Sanctum::actingAs(orgChartActor(['users.update']));

    $this->postJson("/api/org-chart/people/{$person->id}/function", functionMovePayload($f1, $f2))
        ->assertOk()->assertJsonPath('data.business_function_ids', [$f2->id]);

    expect($person->businessFunctions()->count())->toBe(1);
});

it('0275 AC-017: from_function_id null moves an unassigned person and logs a null source', function () {
    $f2 = BusinessFunction::factory()->create(['name' => 'F2']);
    $person = orgChartPerson('Pat');
    Sanctum::actingAs(orgChartActor(['users.update']));

    $this->postJson("/api/org-chart/people/{$person->id}/function", ['from_function_id' => null, 'to_function_id' => $f2->id])
        ->assertOk()->assertJsonPath('data.business_function_ids', [$f2->id]);

    expect(Activity::query()->where('log_name', 'org-chart')->sole()->properties['from'])->toBeNull();
});

it('0275 AC-017: refuses a null source for a person with a function, a wrong source, a non-member and the same function', function (string $case) {
    [$f1, $f2, $f3] = BusinessFunction::factory()->count(3)->create();
    $person = memberOfFunctions(orgChartPerson('Pat'), $f1);
    Sanctum::actingAs(orgChartActor(['users.update']));

    $payload = match ($case) {
        'null source but member' => ['from_function_id' => null, 'to_function_id' => $f2->id],
        'wrong source' => ['from_function_id' => $f3->id, 'to_function_id' => $f2->id],
        'same function' => ['from_function_id' => $f1->id, 'to_function_id' => $f1->id],
    };

    $response = $this->postJson("/api/org-chart/people/{$person->id}/function", $payload)->assertUnprocessable();

    if ($case !== 'same function') {
        $response->assertJsonValidationErrors(['from_function_id']);
    }

    expect($person->businessFunctions()->pluck('business_functions.id')->all())->toBe([$f1->id])
        ->and(Activity::query()->where('log_name', 'org-chart')->count())->toBe(0);
})->with(['null source but member', 'wrong source', 'same function']);

it('0275 AC-016: a person without employment profile can be moved (membership is keyed on the user)', function () {
    [$f1, $f2] = BusinessFunction::factory()->count(2)->create();
    $person = memberOfFunctions(User::factory()->create(), $f1);
    Sanctum::actingAs(orgChartActor(['users.update']));

    $this->postJson("/api/org-chart/people/{$person->id}/function", functionMovePayload($f1, $f2))->assertOk();

    expect($person->businessFunctions()->pluck('business_functions.id')->all())->toBe([$f2->id]);
});

it('0273 AC-007: refuses the person function move without users.update', function () {
    [$f1, $f2] = BusinessFunction::factory()->count(2)->create();
    $person = memberOfFunctions(orgChartPerson('Pat'), $f1);
    Sanctum::actingAs(orgChartActor(['org-chart.view']));

    $this->postJson("/api/org-chart/people/{$person->id}/function", functionMovePayload($f1, $f2))->assertForbidden();

    expect($person->businessFunctions()->pluck('business_functions.id')->all())->toBe([$f1->id]);
});
