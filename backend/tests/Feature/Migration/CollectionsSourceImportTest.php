<?php

use App\Migrations\MigrationOrder;
use App\Migrations\MigrationRegistry;
use App\Models\Invoice;
use App\Models\InvoiceInstallment;
use App\Models\MigrationRun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    seedMigrationsConfig();
    $this->invoice = Invoice::factory()->create(['net_amount' => 1000, 'vat_amount' => 0, 'total_amount' => 1000]);
    $this->invoice->forceFill(['old_source' => 'proformas', 'old_id' => 11030])->save();
    $this->installment = InvoiceInstallment::factory()->create([
        'invoice_id' => $this->invoice->id, 'sequence' => 1, 'due_date' => '2026-10-31', 'amount' => 1000,
        'collected_amount' => null, 'collected_at' => null,
    ]);
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function legacyCollectionRecord(array $overrides = []): array
{
    return array_merge([
        'id' => 3625,
        'kind' => 'registration',
        'collected_at' => '2026-11-04',
        'amount' => 1000,
        'description' => 'Proforma n. 858 del 2026 - saldo',
        'customer_id' => 12874,
        'movements' => [['proforma_id' => 11030, 'amount' => 1000, 'document_collected_total' => 1000]],
        'warnings' => [],
        'created_at' => '2026-11-04 18:10:26',
        'updated_at' => '2026-11-04 18:10:26',
    ], $overrides);
}

/**
 * @param  array<int, array<string, mixed>>  $records
 */
function runLegacyCollectionsImport(array $records): MigrationRun
{
    Http::fake([
        fakeMigrationsBaseUrl().'/collections*' => Http::response([
            'items' => $records,
            'pagination' => ['total' => count($records)],
        ]),
    ]);

    $run = MigrationRun::factory()->create(['user_id' => migrationsSuperAdminActor()->id, 'source' => 'collections']);
    runMigrationJobFor($run);

    return $run->fresh();
}

it('AC-001 records a missing collection on the installment at the registration date', function () {
    $run = runLegacyCollectionsImport([legacyCollectionRecord()]);

    $installment = $this->installment->fresh();

    expect($run->created_rows)->toBe(1)
        ->and($installment->collected_amount)->toBe('1000.00')
        ->and($installment->collected_at->toDateString())->toBe('2026-11-04')
        ->and(legacyTaskWarnings($run))->toBe(['Collected 1000.00 on 2026-11-04.']);
});

it('AC-001 leaves the residual of a partial collection on a new installment', function () {
    runLegacyCollectionsImport([legacyCollectionRecord(['movements' => [['proforma_id' => 11030, 'amount' => 400, 'document_collected_total' => 400]]])]);

    $installments = $this->invoice->installments()->orderBy('sequence')->get();

    expect($installments)->toHaveCount(2)
        ->and($installments[0]->collected_amount)->toBe('400.00')
        ->and($installments[1]->amount)->toBe('600.00')
        ->and($installments[1]->collected_amount)->toBeNull()
        ->and($installments[1]->due_date->toDateString())->toBe('2026-10-31');
});

it('AC-002 skips a collection already on the installments, also at a re-run', function () {
    $this->installment->update(['collected_amount' => 1000, 'collected_at' => '2026-11-01']);

    $run = runLegacyCollectionsImport([legacyCollectionRecord()]);

    expect($run->skipped_rows)->toBe(1)
        ->and($this->installment->fresh()->collected_at->toDateString())->toBe('2026-11-01');
});

it('AC-002 records only what the legacy total still misses', function () {
    $this->installment->update(['amount' => 300, 'collected_amount' => 300, 'collected_at' => '2026-11-01']);
    InvoiceInstallment::factory()->create(['invoice_id' => $this->invoice->id, 'sequence' => 2, 'due_date' => '2026-12-31', 'amount' => 700, 'collected_amount' => null, 'collected_at' => null]);

    runLegacyCollectionsImport([legacyCollectionRecord(['movements' => [['proforma_id' => 11030, 'amount' => 500, 'document_collected_total' => 500]]])]);

    expect((float) $this->invoice->installments()->sum('collected_amount'))->toBe(500.0);
});

it('AC-003 fails a collection that cannot be assigned', function (array $overrides, string $reason) {
    $run = runLegacyCollectionsImport([legacyCollectionRecord($overrides)]);

    expect($run->failed_rows)->toBe(1)
        ->and($this->installment->fresh()->collected_amount)->toBeNull()
        ->and(legacyTaskErrors($run)[0])->toContain($reason);
})->with([
    'no document' => [['movements' => [], 'description' => 'bonifico'], 'No document linked in the legacy (description: bonifico)'],
    'empty header' => [['kind' => 'empty_header', 'movements' => [], 'description' => null], 'No document linked in the legacy (description: -)'],
    'deleted document' => [['movements' => [['proforma_id' => null, 'amount' => 10, 'document_collected_total' => 10]]], 'Document deleted in the legacy'],
    'not imported' => [['movements' => [['proforma_id' => 99, 'amount' => 10, 'document_collected_total' => 10]]], 'Document (legacy id 99) not imported'],
]);

it('AC-004 records no more than the document residual, with a warning', function () {
    $run = runLegacyCollectionsImport([legacyCollectionRecord(['movements' => [['proforma_id' => 11030, 'amount' => 1200, 'document_collected_total' => 1200]]])]);

    expect($this->installment->fresh()->collected_amount)->toBe('1000.00')
        ->and(legacyTaskWarnings($run)[0])->toBe('Document (legacy id 11030): 1200.00 exceeds the residual 1000.00, only the residual is recorded.');
});

it('AC-005 registers the source in the phase after invoices', function () {
    $source = app(MigrationRegistry::class)->resolve('collections');

    expect(MigrationOrder::phaseOf('collections'))->toBe(MigrationOrder::phaseOf('invoices') + 1)
        ->and(array_map(fn ($dependency) => [$dependency->source, $dependency->required], $source->dependencies()))->toBe([['invoices', true]]);
});
