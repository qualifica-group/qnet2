<?php

use App\Models\Attribute;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Integrations\Formalab\Models\FormalabCallLog;
use Integrations\Formalab\Models\FormalabClass;
use Integrations\Formalab\Models\FormalabSyncRun;
use Integrations\Formalab\Models\FormalabWorkOrderLink;
use Integrations\Formalab\Services\StatusReport;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

// AC-003
it('syncs every page, stays idempotent and never logs credentials or tokens', function () {
    formalabEnable(['page_size' => 2]);
    formalabFakeApi([formalabClassPayload(1), formalabClassPayload(2), formalabClassPayload(3)]);

    $run = formalabSync();
    formalabSync();

    expect($run->outcome)->toBe('success')->and($run->classes_synced)->toBe(3)
        ->and(FormalabClass::query()->count())->toBe(3)
        ->and(FormalabSyncRun::query()->where('outcome', 'success')->count())->toBe(2);

    $class = FormalabClass::query()->where('edition_id', 1)->first();
    expect($class->label)->toBe('COD-1 - Course 1')->and($class->exam_date)->toBeNull()
        ->and($class->site_city)->toBe('Milano')->and($class->total_hours)->toBe(300);

    $first = FormalabCallLog::query()->orderBy('id')->limit(3)->get();
    expect($first->pluck('endpoint')->all())->toBe(['/api/login', '/api/classi', '/api/classi'])
        ->and($first[1]->response_summary)->toBe('2 classes, page 1/2');

    $dump = json_encode(FormalabCallLog::query()->get()->toArray());
    expect($dump)->not->toContain('p4ss-never-logged')->not->toContain('token-from-login')->not->toContain('qnet@formalab.test');
});

it('records in a successful run the course fields whose code no longer exists', function () {
    formalabEnable();
    formalabFakeApi([formalabClassPayload(1)]);
    formalabGolWorkOrder(); // REQUIREMENT CHANGED (spec 0423 D-8): only mapped codes are checked
    formalabCourseAttributes()['gol_saldo_data_accredito']->update(['code' => 'gol_balance_renamed']);

    $run = formalabSync();

    expect($run->outcome)->toBe('success')->and($run->warning_message)->toContain('gol_saldo_data_accredito');

    Attribute::query()->where('code', 'gol_balance_renamed')->update(['code' => 'gol_saldo_data_accredito']);
    expect(formalabSync()->warning_message)->toBeNull();
});

// AC-004
it('retries once with a fresh login after a 401 and fails on the second one', function () {
    formalabEnable();
    Http::preventStrayRequests();
    Http::fake([
        '*/api/login' => Http::response(['accessToken' => 'tok']),
        '*/api/classi*' => Http::response(['message' => 'Unauthenticated'], 401),
    ]);

    $run = formalabSync();

    expect($run->outcome)->toBe('failure')->and($run->error_message)->toContain('401');
    $logs = FormalabCallLog::query()->orderBy('id')->get();
    expect($logs->pluck('endpoint')->all())->toBe(['/api/login', '/api/classi', '/api/login', '/api/classi'])
        ->and($logs->where('outcome', 'failure')->count())->toBe(2);
});

// AC-005
it('records a failed run when Formalab is down and leaves the catalogue untouched', function () {
    formalabEnable();
    FormalabClass::query()->create(['edition_id' => 9, 'code' => 'X', 'title' => 'Y', 'label' => 'X - Y', 'payload_hash' => str_repeat('a', 64)]);
    Http::preventStrayRequests();
    Http::fake(['*/api/login' => Http::response(['accessToken' => 't']), '*/api/classi*' => Http::response('boom', 500)]);

    $run = formalabSync();

    expect($run->outcome)->toBe('failure')->and($run->error_message)->not->toBeNull()
        ->and(FormalabClass::query()->count())->toBe(1)
        ->and(FormalabCallLog::query()->where('outcome', 'failure')->value('error_message'))->toContain('500');

    Http::fake(fn () => throw new ConnectionException('cURL error 7: refused'));
    app('cache')->flush();
    $down = formalabSync();

    expect($down->outcome)->toBe('failure')
        ->and(FormalabCallLog::query()->whereNull('status_code')->value('error_message'))->toContain('refused');
});

it('fails the run cleanly when the integration is not configured', function () {
    formalabEnable(['user_password' => '']);

    $run = formalabSync();

    expect($run->outcome)->toBe('failure')->and(FormalabCallLog::query()->count())->toBe(0);
});

// spec 0423 D-15 — REQUIREMENT CHANGED (spec 0426): the match is the edition chosen in the class select
it('links on every sync the work orders whose chosen edition is in the catalogue, and writes their fields', function () {
    formalabEnable();
    formalabFakeApi([
        formalabClassPayload(1, ['codice' => 'AAA', 'corso' => ['title' => 'Unique']]),
        formalabClassPayload(2, ['codice' => 'BBB', 'corso' => ['title' => 'Twin']]),
        formalabClassPayload(3, ['codice' => 'BBB', 'corso' => ['title' => 'Twin']]),
    ]);
    // saved before the catalogue had their class: not linked yet
    $matching = formalabGolWorkOrder(['gol_corso' => 1]);
    $twin = formalabGolWorkOrder(['gol_corso' => 3]);
    $unmatched = formalabGolWorkOrder(['gol_corso' => 99]);
    $notLinkedCategory = formalabGolWorkOrder(['gol_corso' => 1], linkCategory: false);
    expect(FormalabWorkOrderLink::query()->count())->toBe(0);

    $run = formalabSync();

    expect($run->outcome)->toBe('success')->and($run->work_orders_updated)->toBe(2)
        ->and(FormalabWorkOrderLink::query()->pluck('edition_id', 'work_order_id')->all())->toBe([$matching->id => 1, $twin->id => 3])
        ->and($matching->refresh()->attribute_values['gol_stato_corso'])->toBe('In corso')
        ->and($twin->refresh()->attribute_values['gol_stato_corso'])->toBe('In corso')
        ->and($unmatched->refresh()->attribute_values)->not->toHaveKey('gol_stato_corso')
        ->and($notLinkedCategory->refresh()->attribute_values)->not->toHaveKey('gol_stato_corso');

    // nothing new: the next run links and rewrites nothing
    expect(formalabSync()->work_orders_updated)->toBe(0)->and(FormalabWorkOrderLink::query()->count())->toBe(2);
});

// spec 0423 D-15 — REQUIREMENT CHANGED (spec 0426 D-7): no "ambiguous" any more
it('reports on the status page why the work orders of linked categories are still unlinked', function () {
    formalabEnable();
    formalabFakeApi([formalabClassPayload(1)]);
    formalabGolWorkOrder(['gol_corso' => 1]);
    formalabGolWorkOrder(['gol_corso' => 98]);
    formalabGolWorkOrder(['gol_corso' => 99]);
    formalabGolWorkOrder();
    formalabGolWorkOrder(['gol_corso' => 99], linkCategory: false);

    formalabSync();
    // Written without events (as a data import would): the next sync links it.
    $late = formalabGolWorkOrder();
    $late->forceFill(['attribute_values' => ['gol_corso' => 1]])->saveQuietly();

    expect(app(StatusReport::class)->handle()['unlinked_work_orders'])
        ->toBe(['linkable' => 1, 'unmatched' => 2, 'without_course_name' => 1])
        ->and(FormalabWorkOrderLink::query()->count())->toBe(1);
});

// AC-006
it('propagates a changed class to the linked work orders only', function () {
    formalabEnable();
    $catalog = formalabFakeApi([formalabClassPayload(7, ['saldo_data_accredito' => '2026-09-01'])]);
    formalabSync();

    $workOrder = formalabGolWorkOrder(['gol_notice' => 'keep me']);
    FormalabWorkOrderLink::query()->create(['work_order_id' => $workOrder->id, 'edition_id' => 7]);

    $run = formalabSync();

    $values = $workOrder->refresh()->attribute_values;
    expect($run->work_orders_updated)->toBe(1)
        ->and($values['gol_corso'])->toBe(7)
        ->and($values['gol_stato_corso'])->toBe('In corso')->and($values['gol_stato_classe'])->toBe('Avviata')
        ->and($values['gol_data_inizio_corso'])->toBe('2026-03-02')->and($values['gol_ore_totali_corso'])->toBe(300)
        ->and($values['gol_saldo_data_accredito'])->toBe('2026-09-01')
        ->and($values['gol_data_esame'] ?? null)->toBeNull()
        ->and($values['gol_notice'])->toBe('keep me')
        ->and(Activity::query()->where('subject_id', $workOrder->id)->where('description', 'People and request fields change')->count())->toBe(1);

    // no change on Formalab: nothing is rewritten
    expect(formalabSync()->work_orders_updated)->toBe(0);

    $catalog['classes'] = [formalabClassPayload(7, ['stato_corso' => ['name' => 'Concluso'], 'saldo_data_accredito' => ''])];
    $third = formalabSync();

    $after = $workOrder->refresh()->attribute_values;
    expect($third->work_orders_updated)->toBe(1)->and($after['gol_stato_corso'])->toBe('Concluso')
        ->and($after['gol_saldo_data_accredito'] ?? null)->toBeNull()
        ->and(FormalabWorkOrderLink::query()->first()->sync_error)->toBeNull();
});

it('does not count an empty Formalab value on an already empty field as a change', function () {
    formalabEnable();
    formalabFakeApi([formalabClassPayload(8)]);
    formalabSync();

    // same values the class carries; its empty fields are simply absent on the work order
    $workOrder = formalabGolWorkOrder([
        'gol_corso' => 8,
        'gol_stato_corso' => 'In corso',
        'gol_stato_classe' => 'Avviata',
        'gol_data_inizio_corso' => '2026-03-02',
        'gol_data_fine_corso' => '2026-06-30',
        'gol_ore_totali_corso' => 300,
    ]);
    // REQUIREMENT CHANGED (spec 0426 D-5): saving the class select links the work order at once
    expect(FormalabWorkOrderLink::query()->where('work_order_id', $workOrder->id)->value('edition_id'))->toBe(8);

    $run = formalabSync();

    expect($run->work_orders_updated)->toBe(0)
        ->and($workOrder->refresh()->attribute_values)->not->toHaveKey('gol_data_esame')
        ->and(Activity::query()->where('subject_id', $workOrder->id)->where('description', 'People and request fields change')->count())->toBe(0);
});

it('skips a linked work order without the course fields and goes on with the others', function () {
    formalabEnable();
    formalabFakeApi([formalabClassPayload(7)]);
    $withoutFields = WorkOrder::factory()->create();
    $good = formalabGolWorkOrder();
    FormalabWorkOrderLink::query()->create(['work_order_id' => $withoutFields->id, 'edition_id' => 7]);
    FormalabWorkOrderLink::query()->create(['work_order_id' => $good->id, 'edition_id' => 7]);

    $run = formalabSync();

    expect($run->outcome)->toBe('success')->and($run->work_orders_updated)->toBe(1)
        ->and($good->refresh()->attribute_values['gol_corso'])->toBe(7);
});

it('records a sync error on the link of a work order whose fields refuse the value and goes on', function () {
    formalabEnable();
    formalabFakeApi([formalabClassPayload(7, ['stato_corso' => ['name' => '']])]);
    // REQUIREMENT CHANGED (2026-11-20): an empty value on an already empty field is not sent,
    // so the refusal needs Formalab to clear a required field that has a value
    $refusing = formalabGolWorkOrder(['gol_stato_corso' => 'In corso']);
    $refusing->quoteLines->first()->product->category->attributes()
        ->updateExistingPivot(Attribute::query()->where('code', 'gol_stato_corso')->value('id'), ['is_required' => true]);
    $good = formalabGolWorkOrder();
    FormalabWorkOrderLink::query()->create(['work_order_id' => $refusing->id, 'edition_id' => 7]);
    FormalabWorkOrderLink::query()->create(['work_order_id' => $good->id, 'edition_id' => 7]);

    $run = formalabSync();

    $error = FormalabWorkOrderLink::query()->where('work_order_id', $refusing->id)->first();
    expect($run->outcome)->toBe('success')->and($run->work_orders_updated)->toBe(1)
        ->and($error->sync_error)->not->toBeNull()->and($error->synced_hash)->toBeNull()
        ->and($good->refresh()->attribute_values['gol_corso'])->toBe(7);
});
