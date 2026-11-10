<?php

declare(strict_types=1);

use App\Enums\ExportFormat;
use App\Enums\ExportStatus;
use App\Enums\QuoteLineType;
use App\Models\ExportRun;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductTypology;
use App\Models\Quote;
use App\Models\User;
use App\Services\ExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use OpenSpout\Reader\XLSX\Reader;
use Spatie\Permission\Models\Permission;

/**
 * Spec 0365 AC-003 (export part), AC-006 (export part), AC-011, AC-012, AC-013, AC-014: the
 * Dettagli and Righe dettaglio downloads and the access gate of the generic Sommario export.
 */
uses(RefreshDatabase::class);

/**
 * Every row of the first sheet of the downloaded xlsx, read back from the file.
 *
 * @return array<int, array<int, mixed>>
 */
function agentSalesReportSheet(TestResponse $response): array
{
    $reader = new Reader;
    $reader->open($response->baseResponse->getFile()->getPathname());
    $rows = [];

    foreach ($reader->getSheetIterator() as $sheet) {
        foreach ($sheet->getRowIterator() as $row) {
            // The reader pads shorter rows to the widest one: drop that padding.
            $cells = $row->toArray();
            while ($cells !== [] && end($cells) === '') {
                array_pop($cells);
            }
            $rows[] = $cells;
        }

        break;
    }

    $reader->close();

    return $rows;
}

function agentSalesReportProduct(string $name, string $typologyCode, ?ProductCategory $category = null): Product
{
    $typology = ProductTypology::query()->where('code', $typologyCode)->first()
        ?? ProductTypology::query()->create(['name' => ucfirst($typologyCode), 'code' => $typologyCode]);

    return Product::factory()->create([
        'name' => $name,
        'category_id' => ($category ?? ProductCategory::factory()->create(['name' => "Cat {$name}"]))->id,
        'product_typology_id' => $typology->id,
    ]);
}

/** Two offers: the first has REVENUE lines (consultancy 100, institution 50) + a COST line and commissions. */
function agentSalesReportFixture(): array
{
    $root = ProductCategory::factory()->create(['name' => 'Formazione']);
    $child = ProductCategory::factory()->create(['name' => 'Sicurezza', 'parent_id' => $root->id]);
    $consultancy = agentSalesReportProduct('Corso base', 'consultancy', $child);
    $institution = agentSalesReportProduct('Quota ente', 'institution');

    $first = agentSalesReportQuote(attributes: ['title' => 'Prima', 'quote_date' => '2026-03-10', 'revenue_net' => 150]);
    $first->forceFill(['accepted_at' => '2026-04-01'])->saveQuietly();
    agentSalesReportTeam($first);
    $first->supervisor()->associate(User::factory()->create(['name' => 'Sonia Supervisore']))->saveQuietly();
    agentSalesReportLine($first, ['product_id' => $consultancy->id, 'quantity' => 2, 'unit_price' => 50, 'net_amount' => 100, 'sort_order' => 1], ['SUPERVISOR' => 10, 'COMMERCIAL' => 20, 'REPORTER' => 5, 'SUPPLIER' => 99]);
    agentSalesReportLine($first, ['product_id' => $institution->id, 'quantity' => 1, 'unit_price' => 50, 'net_amount' => 50, 'sort_order' => 2], ['COMMERCIAL' => 5]);
    agentSalesReportLine($first, ['product_id' => $consultancy->id, 'line_type' => QuoteLineType::Cost, 'net_amount' => 777, 'sort_order' => 3]);

    $second = agentSalesReportQuote(agentSalesReportRegistry('Beta Srl', 'Roma'), ['title' => 'Seconda', 'quote_date' => '2026-02-01', 'revenue_net' => 0]);

    return compact('first', 'second');
}

it('AC-003: every report endpoint answers 403 without agent-sales-report.view', function () {
    $user = User::factory()->create()->givePermissionTo(Permission::findOrCreate('quotes.export', 'web'));
    Sanctum::actingAs($user);

    $this->postJson('/api/agent-sales-report/exports/details', [])->assertForbidden();
    $this->postJson('/api/agent-sales-report/exports/lines', [])->assertForbidden();
    $this->postJson('/api/exports/agent-sales-report', ['format' => 'xlsx', 'columns' => [['colId' => 'code', 'header' => 'N']]])->assertForbidden();
});

it('AC-003: unauthenticated calls are 401', function () {
    $this->postJson('/api/agent-sales-report/exports/details', [])->assertUnauthorized();
    $this->postJson('/api/agent-sales-report/exports/lines', [])->assertUnauthorized();
});

it('the generic Sommario export is allowed by agent-sales-report.view alone', function () {
    agentSalesReportQuote();
    Sanctum::actingAs(agentSalesReportActor());

    $this->postJson('/api/exports/agent-sales-report', ['format' => 'xlsx', 'columns' => [['colId' => 'code', 'header' => 'N']]])->assertCreated();
});

it('AC-011: Dettagli writes blocks (a)-(d) per offer with commissions, net and typology totals', function () {
    ['first' => $first, 'second' => $second] = agentSalesReportFixture();
    Sanctum::actingAs(agentSalesReportActor());

    $response = $this->postJson('/api/agent-sales-report/exports/details', [])->assertOk();
    $sheet = agentSalesReportSheet($response);

    expect($response->headers->get('Content-Disposition'))->toContain('report-vendite-dettagli-'.now()->format('Ymd').'.xlsx')
        ->and($sheet[0][0])->toBe(__('agent_sales_report.export.code'))
        ->and($sheet[0])->toHaveCount(18);

    // (a) offer row, grid order: the 10 March offer comes before the 1 February one
    expect($sheet[1])->toBe([
        $first->code, '10/03/2026', '01/04/2026', 'Prima', 'Acme Spa', 'Via Roma 1, Scala B', '20100 - Milano',
        '+39 02 1234567', 'acmespa@example.com', 'Sede Nord', $first->quoteWorkflowStatus->name,
        150, 100, 50, 'Gino Gestore', 'Sonia Supervisore', 'Carla Commerciale', 'Sara Segnalatrice',
    ]);

    // (b) lines header, (c) one row per REVENUE line in sort_order, never the COST one
    expect($sheet[2][0])->toBe(__('agent_sales_report.export.root_category'))
        ->and($sheet[3])->toBe(['Formazione', 'Sicurezza', 'Corso base', 2, 50, 100, ProductTypology::query()->where('code', 'consultancy')->value('name'), 10, 20, 5, 65])
        ->and($sheet[4][2])->toBe('Quota ente')
        ->and(array_slice($sheet[4], 3))->toBe([1, 50, 50, ProductTypology::query()->where('code', 'institution')->value('name'), 0, 5, 0, 45]);

    // (d) totals: supplier excluded; net = revenue_net - three commissions
    expect($sheet[5])->toBe([__('agent_sales_report.export.total_commissions'), '', '', '', '', '', '', 10, 25, 5, 110])
        ->and($sheet[6][0])->toBe($second->code)
        ->and($sheet[7][0])->toBe(__('agent_sales_report.export.root_category'))
        ->and($sheet[8][0])->toBe(__('agent_sales_report.export.total_commissions'))
        ->and($sheet)->toHaveCount(9);
});

it('AC-012: Righe dettaglio writes the header and one flat row per REVENUE line only', function () {
    ['first' => $first] = agentSalesReportFixture();
    Sanctum::actingAs(agentSalesReportActor());

    $response = $this->postJson('/api/agent-sales-report/exports/lines', [])->assertOk();
    $sheet = agentSalesReportSheet($response);

    expect($response->headers->get('Content-Disposition'))->toContain('report-vendite-righe-'.now()->format('Ymd').'.xlsx')
        ->and($sheet)->toHaveCount(3)
        ->and($sheet[0])->toHaveCount(17)
        ->and($sheet[1])->toBe([
            $first->code, '10/03/2026', 'Acme Spa', 'Formazione', 'Sicurezza', 'Corso base', 2, 50, 100, ProductTypology::query()->where('code', 'consultancy')->value('name'),
            'Sonia Supervisore', 10, 'Carla Commerciale', 20, 'Sara Segnalatrice', 5, 65,
        ])
        ->and($sheet[2][5])->toBe('Quota ente');
});

it('AC-013: only the grid offers, in the grid order, follow the filter and the sort', function () {
    ['first' => $first, 'second' => $second] = agentSalesReportFixture();
    Sanctum::actingAs(agentSalesReportActor());

    $filtered = agentSalesReportSheet($this->postJson('/api/agent-sales-report/exports/details', [
        'filterModel' => ['registry' => ['filterType' => 'set', 'values' => ['Beta Srl']]],
    ])->assertOk());
    $ascending = agentSalesReportSheet($this->postJson('/api/agent-sales-report/exports/details', [
        'sortModel' => [['colId' => 'quote_date', 'sort' => 'asc']],
    ])->assertOk());

    expect($filtered)->toHaveCount(4)
        ->and($filtered[1][0])->toBe($second->code)
        ->and($ascending[1][0])->toBe($second->code)
        ->and(collect($ascending)->pluck(0)->contains($first->code))->toBeTrue();

    $this->postJson('/api/agent-sales-report/exports/details', ['filterModel' => ['address' => ['filterType' => 'text']]])->assertStatus(422);
    $this->postJson('/api/agent-sales-report/exports/lines', ['sortModel' => [['colId' => 'phone', 'sort' => 'asc']]])->assertStatus(422);
});

it('AC-006: the exports never carry an offer outside the actor visibility', function () {
    $actor = agentSalesReportActor(seesAll: false);
    $mine = agentSalesReportQuote(attributes: ['supervisor_id' => $actor->id]);
    agentSalesReportQuote(attributes: ['title' => 'Altrui']);
    Sanctum::actingAs($actor);

    $details = agentSalesReportSheet($this->postJson('/api/agent-sales-report/exports/details', [])->assertOk());

    expect($details)->toHaveCount(4)
        ->and($details[1][0])->toBe($mine->code);
});

it('AC-006: Righe dettaglio never carries a line of an offer outside the actor visibility', function () {
    $actor = agentSalesReportActor(seesAll: false);
    $mine = agentSalesReportQuote(attributes: ['supervisor_id' => $actor->id]);
    $foreign = agentSalesReportQuote(attributes: ['title' => 'Altrui']);
    foreach ([$mine, $foreign] as $quote) {
        agentSalesReportLine($quote, ['product_id' => agentSalesReportProduct("P{$quote->id}", 'consultancy')->id]);
    }
    Sanctum::actingAs($actor);

    $sheet = agentSalesReportSheet($this->postJson('/api/agent-sales-report/exports/lines', [])->assertOk());

    expect($sheet)->toHaveCount(2)
        ->and($sheet[1][0])->toBe($mine->code);
});

it('AC-006: the generic Sommario export, run as the queue does, holds only the actor visible offers', function () {
    Storage::fake('local');
    $actor = agentSalesReportActor(seesAll: false);
    $mine = agentSalesReportQuote(attributes: ['supervisor_id' => $actor->id]);
    $foreign = agentSalesReportQuote(attributes: ['title' => 'Altrui']);
    Sanctum::actingAs($actor);

    $runId = $this->postJson('/api/exports/agent-sales-report', [
        'format' => 'xlsx', 'columns' => [['colId' => 'code', 'header' => 'N']],
    ])->assertCreated()->json('data.export_run.id');

    // No authenticated user in the job, as on a real queue worker.
    ExportRun::query()->whereKey($runId)->update(['status' => ExportStatus::Processing, 'file_path' => null]);
    Auth::forgetGuards();
    app(ExportService::class)->generate(ExportRun::query()->findOrFail($runId));

    $run = ExportRun::query()->findOrFail($runId);
    $reader = new Reader;
    $reader->open(Storage::disk('local')->path($run->file_path));
    $codes = [];
    foreach ($reader->getSheetIterator() as $sheet) {
        foreach ($sheet->getRowIterator() as $row) {
            $codes[] = $row->toArray()[0];
        }
        break;
    }
    $reader->close();

    expect($run->format)->toBe(ExportFormat::Xlsx)
        ->and($codes)->toBe(['N', $mine->code])
        ->and($codes)->not->toContain($foreign->code);
});

it('AC-014: above exports.max_rows both endpoints answer 422 with the too_many message and no file', function () {
    agentSalesReportQuote();
    agentSalesReportQuote();
    config(['exports.max_rows' => 1]);
    Sanctum::actingAs(agentSalesReportActor());

    foreach (['details', 'lines'] as $endpoint) {
        $this->postJson("/api/agent-sales-report/exports/{$endpoint}", [])
            ->assertStatus(422)
            ->assertJsonPath('message', __('agent_sales_report.export.too_many'));
    }

    config(['exports.max_rows' => 2]);
    $this->postJson('/api/agent-sales-report/exports/lines', [])->assertOk();
});

it('reads the offers in chunks without a query per offer', function () {
    foreach (range(1, 4) as $index) {
        $quote = agentSalesReportQuote(agentSalesReportRegistry("Cliente {$index}"));
        agentSalesReportTeam($quote);
        agentSalesReportLine($quote, ['product_id' => agentSalesReportProduct("P{$index}", 'consultancy')->id], ['COMMERCIAL' => 1]);
    }
    Sanctum::actingAs(agentSalesReportActor());

    $queries = 0;
    DB::listen(function () use (&$queries): void {
        $queries++;
    });
    $this->postJson('/api/agent-sales-report/exports/details', [])->assertOk();
    $withFour = $queries;

    foreach (range(5, 8) as $index) {
        agentSalesReportLine(agentSalesReportQuote(agentSalesReportRegistry("Cliente {$index}")), ['product_id' => agentSalesReportProduct("P{$index}", 'consultancy')->id], ['COMMERCIAL' => 1]);
    }
    $queries = 0;
    $this->postJson('/api/agent-sales-report/exports/details', [])->assertOk();

    expect(Quote::query()->count())->toBe(8)
        ->and($queries)->toBeLessThanOrEqual($withFour + 2);
});
