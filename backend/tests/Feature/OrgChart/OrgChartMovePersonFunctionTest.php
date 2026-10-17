<?php

use App\Models\BusinessFunction;
use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

function functionMovePayload(BusinessFunction $from, BusinessFunction $to): array
{
    return ['from_function_id' => $from->id, 'to_function_id' => $to->id];
}

it('0273 AC-006: replaces the source rows with one whole-function row, keeps other functions and logs', function () {
    [$f1, $f2, $f3] = BusinessFunction::factory()->count(3)->sequence(['name' => 'F1'], ['name' => 'F2'], ['name' => 'F3'])->create();
    $person = orgChartPerson('Pat');
    $person->employment->productLines()->createMany([
        ['business_function_id' => $f1->id, 'product_category_id' => null],
        ['business_function_id' => $f3->id, 'product_category_id' => null],
    ]);
    Sanctum::actingAs(orgChartActor(['users.update']));

    $this->postJson("/api/org-chart/people/{$person->id}/function", functionMovePayload($f1, $f2))
        ->assertOk()->assertJsonPath('data', ['id' => $person->id, 'business_function_ids' => [$f2->id, $f3->id]]);

    $rows = $person->employment->productLines()->get(['business_function_id', 'product_category_id'])
        ->map(fn ($row): array => [$row->business_function_id, $row->product_category_id])->sortBy(0)->values()->all();
    $log = Activity::query()->where('log_name', 'org-chart')->sole();

    expect($rows)->toBe([[$f2->id, null], [$f3->id, null]])
        ->and($log->event)->toBe('moved')
        ->and($log->subject_id)->toBe($person->id)
        ->and($log->properties->toArray())->toBe([
            'kind' => 'person_function',
            'from' => ['id' => $f1->id, 'name' => 'F1'],
            'to' => ['id' => $f2->id, 'name' => 'F2'],
        ]);
});

it('0273 AC-006: drops category rows of the source and keeps the person on the destination when already there', function () {
    [$f1, $f2] = BusinessFunction::factory()->count(2)->create();
    $category = ProductCategory::factory()->create();
    $person = orgChartPerson('Pat');
    $person->employment->productLines()->createMany([
        ['business_function_id' => $f1->id, 'product_category_id' => $category->id],
        ['business_function_id' => $f2->id, 'product_category_id' => null],
    ]);
    Sanctum::actingAs(orgChartActor(['users.update']));

    $this->postJson("/api/org-chart/people/{$person->id}/function", functionMovePayload($f1, $f2))
        ->assertOk()->assertJsonPath('data.business_function_ids', [$f2->id]);

    expect($person->employment->productLines()->count())->toBe(1);
});

it('0273 AC-006: refuses a covers_all person, a missing source row, the same function and a person without profile', function (string $case) {
    [$f1, $f2] = BusinessFunction::factory()->count(2)->create();
    $person = match ($case) {
        'covers all' => orgChartPerson('Pat', ['covers_all_product_categories' => true]),
        'no row on source' => orgChartPerson('Pat'),
        'same function' => orgChartPerson('Pat'),
        'no profile' => User::factory()->create(),
    };
    if ($case === 'same function') {
        $person->employment->productLines()->create(['business_function_id' => $f1->id, 'product_category_id' => null]);
    }
    Sanctum::actingAs(orgChartActor(['users.update']));

    $this->postJson("/api/org-chart/people/{$person->id}/function", functionMovePayload($f1, $case === 'same function' ? $f1 : $f2))
        ->assertUnprocessable();

    expect(Activity::query()->where('log_name', 'org-chart')->count())->toBe(0);
})->with(['covers all', 'no row on source', 'same function', 'no profile']);

it('0273 AC-007: refuses the person function move without users.update', function () {
    [$f1, $f2] = BusinessFunction::factory()->count(2)->create();
    $person = orgChartPerson('Pat');
    $person->employment->productLines()->create(['business_function_id' => $f1->id, 'product_category_id' => null]);
    Sanctum::actingAs(orgChartActor(['org-chart.view']));

    $this->postJson("/api/org-chart/people/{$person->id}/function", functionMovePayload($f1, $f2))->assertForbidden();

    expect($person->employment->productLines()->pluck('business_function_id')->all())->toBe([$f1->id]);
});
