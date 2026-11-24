<?php

use App\Enums\QuoteLineType;
use App\Models\PersonalData;
use App\Models\Product;
use App\Models\QuoteLine;
use App\Models\Referent;
use App\Models\Registry;
use App\Models\WorkOrder;
use App\Models\WorkOrderProgressEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Integrations\Formalab\Models\FormalabLearnerCommission;
use Laravel\Sanctum\Sanctum;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/*
| Golden figures: legacy QNet ProspettoContabileDiscenti methods run on class 926
| / order 12574 (COM-12574), data anonymized (names, tax codes, partner names).
| One row per learner in roster order: presence h/m, absence h/m, passed,
| partner, commission percentage, stored amount.
*/
const LS_LEARNERS = [
    [381, 15, 18, 45, true, 'Partner A', 50, 2014.17],
    [134, 44, 265, 16, false, 'Partner B', 0, 0],
    [379, 18, 20, 42, true, 'Partner C', 0, 0],
    [326, 21, 73, 39, true, 'Partner A', 50, 2014.17],
    [108, 34, 291, 26, false, 'Partner A', 0, 0],
    [363, 16, 36, 44, true, 'Partner D', 0, 0],
    [203, 26, 196, 34, false, 'Partner A', 0, 0],
    [322, 49, 77, 11, true, null, 0, 0],
    [347, 0, 53, 0, true, null, 0, 0],
    [376, 42, 23, 18, true, null, 0, 0],
    [350, 48, 49, 12, true, 'Partner E', 50, 2014.17],
    [322, 46, 77, 14, true, null, 0, 0],
    [341, 23, 58, 37, true, 'Partner F', 0, 0],
];

const LS_EDITION = 926;

const LS_DELTA = 0.0001;

function lsUrl(WorkOrder $workOrder, string $suffix = ''): string
{
    return '/api/integrations/formalab/work-orders/'.$workOrder->id.'/learner-statement'.$suffix;
}

function lsTaxCode(int $index): string
{
    return sprintf('TSTLRN00A01H5%02dX', $index);
}

/** The Formalab class 926, anonymized. */
function lsClassPayload(): array
{
    $learners = [];
    foreach (LS_LEARNERS as $index => [$presenceH, $presenceM, $absenceH, $absenceM, $passed]) {
        $learners[] = [
            'id' => 1000 + $index, 'name' => 'Nome'.$index, 'last_name' => sprintf('Cognome%02d', $index),
            'fiscalcode' => lsTaxCode($index), 'phone' => null, 'email' => null, 'indirizzo_residenza' => null,
            'ore_presenza' => $presenceH, 'minuti_presenza' => $presenceM, 'ore_assenza' => $absenceH, 'minuti_assenza' => $absenceM,
            'corso_superato' => $passed,
        ];
    }

    // Formalab sends them unsorted: the roster sorts by last name, then name.
    return formalabClassPayload(LS_EDITION, ['corso' => ['total_hours' => 400, 'modalita_due_fasi' => false], 'discenti' => array_reverse($learners)]);
}

function lsProduct(string $source, int $oldId, ?int $categoryId = null): Product
{
    $product = Product::factory()->create(array_filter(['category_id' => $categoryId, 'name' => "{$source} {$oldId}"]));
    $product->forceFill(['old_source' => $source, 'old_id' => $oldId])->save();

    return $product;
}

/**
 * The work order of order 12574: eligible, class 926 chosen, the quote lines
 * of quotation 23272, the learners' partner data.
 *
 * @param  array<string, mixed>  $values
 */
function lsWorkOrder(array $values = [], bool $linkCategory = true): WorkOrder
{
    $workOrder = formalabGolWorkOrder(['gol_corso' => LS_EDITION, ...$values], $linkCategory);
    $classLine = QuoteLine::query()->where('quote_id', $workOrder->quote_id)->sole();
    $classLine->product->forceFill(['old_source' => 'services', 'old_id' => 1322])->save();
    $classLine->forceFill(['line_type' => QuoteLineType::Revenue, 'quantity' => 400, 'unit_price' => 122.90])->save();

    $learnerLine = QuoteLine::factory()->create(['quote_id' => $workOrder->quote_id, 'product_id' => lsProduct('services', 1326)->id, 'quantity' => 400, 'unit_price' => 10.92]);
    $teacher = lsProduct('expense_reports', 5);
    $costs = [
        [$teacher, 260, 20, $classLine->id, 'Docente uno'],
        [$teacher, 20, 20, $classLine->id, 'Docente due'],
        [lsProduct('expense_reports', 8), 400, 15, $learnerLine->id, null],
        [lsProduct('expense_reports', 7), 1, 180, $classLine->id, null],
        [lsProduct('expense_reports', 6), 1, 460.20, $classLine->id, null],
        [lsProduct('expense_reports', 9), 1, 999, $classLine->id, null],
    ];
    foreach ($costs as [$product, $quantity, $price, $offerLineId, $note]) {
        QuoteLine::factory()->cost()->create([
            'quote_id' => $workOrder->quote_id, 'product_id' => $product->id, 'quantity' => $quantity, 'unit_price' => $price,
            'offer_line_id' => $offerLineId, 'additional_description' => $note,
        ]);
    }

    foreach (LS_LEARNERS as $index => [, , , , , $partner, $percentage, $amount]) {
        if ($partner !== null) {
            FormalabLearnerCommission::query()->create(['tax_code' => lsTaxCode($index), 'partner_name' => $partner, 'percentage' => $percentage, 'amount' => $amount]);
        }
    }

    return $workOrder;
}

function lsActor(array $costAbilities = ['viewCosts']): void
{
    foreach (['viewCosts', 'manageCosts'] as $ability) {
        Permission::findOrCreate("work-orders.{$ability}");
    }
    Sanctum::actingAs(workOrderUserWith(['view', ...$costAbilities]));
}

function lsSetup(array $values = [], array $costAbilities = ['viewCosts']): WorkOrder
{
    formalabEnable();
    formalabFakeApi([lsClassPayload()]);
    formalabSync();
    $workOrder = lsWorkOrder($values);
    lsActor($costAbilities);

    return $workOrder;
}

// AC-003, AC-004, AC-005 (no payment), AC-006
it('computes the forecast like the legacy prospetto: costs, revenue, learners, partner commissions', function () {
    $workOrder = lsSetup();

    $data = $this->getJson(lsUrl($workOrder))->assertOk()->json('data');

    expect($data['class_status'])->toBe('ok')->and($data['payment_state'])->toBe('none')
        ->and($data['commission_scenario'])->toBe('economic')->and($data['financial_available'])->toBeFalse()
        ->and($data['costs']['learners'])->toEqualWithDelta(6000, LS_DELTA)
        ->and($data['costs']['total'])->toEqualWithDelta(12240.2, LS_DELTA)
        ->and(array_column($data['costs']['other_lines'], 'amount'))->toEqualWithDelta([5200, 400, 180, 460.2], LS_DELTA)
        ->and($data['costs']['other_lines'][0]['note'])->toBe('Docente uno');

    $summary = $data['summary'];
    expect($summary['training_revenue'])->toEqualWithDelta(49160, LS_DELTA)
        ->and($summary['learner_revenue'])->toEqualWithDelta(3363.67, LS_DELTA)
        ->and($summary['partial_profit'])->toEqualWithDelta(40283.47, LS_DELTA)
        ->and($summary['learners_count'])->toBe(10)
        ->and($summary['profit_per_learner'])->toEqualWithDelta(4028.347, LS_DELTA)
        ->and($summary['commissions'])->toEqualWithDelta(6042.51, LS_DELTA)
        ->and($summary['mol'])->toEqualWithDelta(34240.96, LS_DELTA)
        ->and($summary['advance'])->toBeNull()->and($summary['single_payment'])->toBeNull();

    $first = $data['learners'][0];
    expect($first['last_name'])->toBe('Cognome00')->and($first['cost'])->toEqualWithDelta(381, LS_DELTA)
        ->and($first['revenue_hours'])->toEqualWithDelta(381.25, LS_DELTA)->and($first['revenue'])->toEqualWithDelta(324.06, LS_DELTA)
        ->and($first['commission'])->toEqualWithDelta(2014.17, LS_DELTA)
        ->and($data['totals']['presence'])->toBe(['hours' => 3958, 'minutes' => 22]);

    $partners = $data['partners']['items'];
    expect(array_column($partners, 'name'))->toBe(['Partner A', 'Partner C', 'Partner D', 'Partner E', 'Partner F'])
        ->and(array_column($partners, 'total'))->toEqualWithDelta([4028.34, 0, 0, 2014.17, 0], LS_DELTA)
        ->and($partners[0]['learners_count'])->toBe(2);
});

// AC-005 state A: advance only
it('splits advance and forecast balance by the forecast total when only the advance is in', function () {
    $workOrder = lsSetup(['gol_importo_anticipo' => 20000]);

    $summary = $this->getJson(lsUrl($workOrder))->assertOk()->assertJsonPath('data.payment_state', 'advance_only')->json('data.summary');

    expect($summary['advance']['costs'])->toEqualWithDelta(4660.83196395073, LS_DELTA)
        ->and($summary['advance']['partial_profit'])->toEqualWithDelta(15339.16803604927, LS_DELTA)
        ->and($summary['advance']['commissions'])->toEqualWithDelta(2300.8752054073907, LS_DELTA)
        ->and($summary['advance']['mol'])->toEqualWithDelta(13038.29283064188, LS_DELTA)
        ->and($summary['forecast_balance']['amount'])->toEqualWithDelta(32523.67, LS_DELTA)
        ->and($summary['forecast_balance']['commissions'])->toEqualWithDelta(3741.6452945926085, LS_DELTA)
        ->and($summary['forecast_balance']['mol'])->toEqualWithDelta(21202.65666935812, LS_DELTA)
        ->and($summary['forecast_total'])->not->toBeNull()
        ->and($summary['balance'])->toBeNull()->and($summary['actual_total'])->toBeNull();
});

// AC-005 state B: single payment, AC-006 financial by default
it('uses the financial scenario by default once the balance is in (single payment)', function () {
    $workOrder = lsSetup(['gol_importo_saldo' => 52479.68]);

    $data = $this->getJson(lsUrl($workOrder))->assertOk()->json('data');

    expect($data['commission_scenario'])->toBe('financial')->and($data['payment_state'])->toBe('balance_only')
        ->and($data['summary']['commissions'])->toEqualWithDelta(6035.91, LS_DELTA)
        ->and($data['summary']['single_payment']['partial_profit'])->toEqualWithDelta(40239.48, LS_DELTA)
        ->and($data['summary']['single_payment']['profit_per_learner'])->toEqualWithDelta(4023.948, LS_DELTA)
        ->and($data['summary']['single_payment']['mol'])->toEqualWithDelta(34203.57, LS_DELTA)
        ->and($data['summary']['single_payment']['difference'])->toEqualWithDelta(-43.99, LS_DELTA)
        ->and(collect($data['partners']['items'])->firstWhere('name', 'Partner E')['total'])->toEqualWithDelta(2011.97, LS_DELTA);

    $economic = $this->getJson(lsUrl($workOrder).'?commission_scenario=economic')->assertOk()->json('data');
    expect($economic['commission_scenario'])->toBe('economic')
        ->and($economic['summary']['commissions'])->toEqualWithDelta(6042.51, LS_DELTA);
});

// AC-005 state C: advance and balance
it('splits the two actual tranches by what was received', function () {
    $workOrder = lsSetup(['gol_importo_anticipo' => 20000, 'gol_importo_saldo' => 32479.68]);

    $summary = $this->getJson(lsUrl($workOrder))->assertOk()->assertJsonPath('data.payment_state', 'advance_and_balance')->json('data.summary');

    expect($summary['commissions'])->toEqualWithDelta(6035.91, LS_DELTA)
        ->and($summary['actual_total']['mol'])->toEqualWithDelta(34203.57, LS_DELTA)
        ->and($summary['advance']['weight'])->toEqualWithDelta(0.38109988475539486, LS_DELTA)
        ->and($summary['advance']['costs'])->toEqualWithDelta(4664.738809382984, LS_DELTA)
        ->and($summary['advance']['commissions'])->toEqualWithDelta(2300.289178592552, LS_DELTA)
        ->and($summary['advance']['mol'])->toEqualWithDelta(13034.972012024464, LS_DELTA)
        ->and($summary['balance']['costs'])->toEqualWithDelta(7575.461190617017, LS_DELTA)
        ->and($summary['balance']['commissions'])->toEqualWithDelta(3735.6328214074474, LS_DELTA)
        ->and($summary['balance']['mol'])->toEqualWithDelta(21168.585987975537, LS_DELTA)
        ->and($summary['balance']['profit_per_learner'])->toEqualWithDelta(2490.4218809382983, LS_DELTA)
        ->and($summary['forecast_balance'])->toBeNull();
});

// AC-010
it('spreads the learner service revenue over the progress months', function () {
    $workOrder = lsSetup();
    foreach ([['2025-02-28', 5], ['2025-03-15', 6], ['2025-03-31', 14]] as [$date, $percentage]) {
        WorkOrderProgressEntry::factory()->create(['work_order_id' => $workOrder->id, 'percentage' => $percentage, 'recorded_on' => $date]);
    }

    $progress = $this->getJson(lsUrl($workOrder))->assertOk()->json('data.progress');

    expect(array_map(fn (array $m): array => [$m['year'], $m['month'], $m['percentage']], $progress['items']))->toBe([[2025, 2, 5], [2025, 3, 20]])
        ->and($progress['items'][1]['revenue'])->toEqualWithDelta(873.6, LS_DELTA)
        ->and($progress['total_percentage'])->toEqualWithDelta(25, LS_DELTA);
});

// AC-002
it('refuses without viewCosts, for a work order outside Formalab or without a class', function () {
    $workOrder = lsSetup([], []);
    $this->getJson(lsUrl($workOrder))->assertForbidden();

    lsActor();
    $this->getJson(lsUrl(formalabGolWorkOrder(['gol_corso' => LS_EDITION], linkCategory: false)))->assertUnprocessable();
    $this->getJson(lsUrl(formalabGolWorkOrder()))->assertUnprocessable();
});

it('does not exist while Formalab is disabled', function () {
    $workOrder = formalabGolWorkOrder();
    lsActor();

    $this->getJson(lsUrl($workOrder))->assertNotFound();
});

// AC-011
it('reports a class Formalab cannot give and a class without learners', function () {
    formalabEnable();
    $catalog = formalabFakeApi([lsClassPayload()]);
    formalabSync();
    $workOrder = lsWorkOrder();
    lsActor();

    $catalog['classes'] = [];
    $this->getJson(lsUrl($workOrder).'?refresh=1')->assertOk()
        ->assertJsonPath('data.class_status', 'unavailable')->assertJsonPath('data.learners', []);

    $catalog['classes'] = [formalabClassPayload(LS_EDITION)];
    $this->getJson(lsUrl($workOrder).'?refresh=1')->assertOk()->assertJsonPath('data.class_status', 'empty');
});

it('takes the partner from the learner registry when the stored row has none', function () {
    $workOrder = lsSetup();
    $registry = Registry::factory()->create(['commercial_partner_id' => Referent::factory()->create(['name' => 'Partner Z'])->id]);
    PersonalData::factory()->individual()->for($registry, 'personable')->create(['tax_code' => lsTaxCode(7)]);

    $this->getJson(lsUrl($workOrder))->assertOk()->assertJsonPath('data.learners.7.partner', 'Partner Z');
});

// AC-007
it('stores a learner commission: percentage within 0-100 with its amount, a fixed amount never negative', function () {
    $workOrder = lsSetup([], ['viewCosts', 'manageCosts']);

    $this->putJson(lsUrl($workOrder, '/learners/'.lsTaxCode(2).'/commission'), ['percentage' => 150, 'commission_scenario' => 'economic'])
        ->assertOk()->assertJsonPath('data.learners.2.percentage', 100);
    expect(FormalabLearnerCommission::query()->where('tax_code', lsTaxCode(2))->value('amount'))->toEqualWithDelta(4028.35, LS_DELTA);

    $this->putJson(lsUrl($workOrder, '/learners/'.lsTaxCode(7).'/commission'), ['amount' => -5])->assertOk();
    expect(FormalabLearnerCommission::query()->where('tax_code', lsTaxCode(7))->value('amount'))->toEqualWithDelta(0, LS_DELTA);

    $this->putJson(lsUrl($workOrder, '/learners/NOTINCLASS/commission'), ['amount' => 10])->assertNotFound();
    $this->putJson(lsUrl($workOrder, '/learners/'.lsTaxCode(7).'/commission'), [])->assertUnprocessable();
});

it('refuses commission and supplier edits without manageCosts', function () {
    $workOrder = lsSetup();

    $this->putJson(lsUrl($workOrder, '/learners/'.lsTaxCode(0).'/commission'), ['amount' => 10])->assertForbidden();
    $this->putJson(lsUrl($workOrder, '/partners/supplier'), ['partner' => 'Partner A', 'supplier_id' => null])->assertForbidden();
});

// AC-008
it('stores the partner supplier on every learner of the class with that partner', function () {
    $workOrder = lsSetup([], ['viewCosts', 'manageCosts']);
    $supplier = Registry::factory()->create(['name' => 'Fornitore Partner A', 'is_supplier' => true]);

    $this->putJson(lsUrl($workOrder, '/partners/supplier'), ['partner' => 'Partner A', 'supplier_id' => $supplier->id])
        ->assertOk()->assertJsonPath('data.partners.items.0.supplier.name', 'Fornitore Partner A');

    expect(FormalabLearnerCommission::query()->where('supplier_registry_id', $supplier->id)->pluck('tax_code')->sort()->values()->all())
        ->toBe([lsTaxCode(0), lsTaxCode(3), lsTaxCode(4), lsTaxCode(6)]);
});

// AC-009
it('exports the whole statement and one sheet per partner', function () {
    $workOrder = lsSetup();

    $whole = $this->get(lsUrl($workOrder, '/export'))->assertOk();
    $book = IOFactory::load($whole->baseResponse->getFile()->getPathname());
    $sheet = $book->getActiveSheet();
    expect($sheet->getCell('A1')->getValue())->toBe('PROSPETTO CONTABILITÀ DISCENTI')
        ->and(collect($sheet->toArray())->first(fn (array $row): bool => $row[0] === 'TOTALE COSTI'))->not->toBeNull()
        ->and(collect($sheet->toArray())->contains(fn (array $row): bool => $row[0] === 'MOL Qualifica'))->toBeTrue();

    $perPartner = $this->get(lsUrl($workOrder, '/export?variant=partners'))->assertOk();
    $book = IOFactory::load($perPartner->baseResponse->getFile()->getPathname());
    expect($book->getSheetNames())->toBe(['Partner A', 'Partner B', 'Partner C', 'Partner D', 'Partner E', 'Partner F'])
        ->and(collect($book->getSheet(0)->toArray())->contains(fn (array $row): bool => str_starts_with((string) $row[0], 'MOL')))->toBeFalse();
});

it('refuses the partner export when no learner has a partner', function () {
    $workOrder = lsSetup();
    FormalabLearnerCommission::query()->delete();

    $this->get(lsUrl($workOrder, '/export?variant=partners'))->assertUnprocessable();
});
