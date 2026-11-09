<?php

use App\Enums\ProformaRequestStatus;
use App\Migrations\Realign\LegacyRealignReport;
use App\Migrations\Realign\LegacyRealignRunner;
use App\Migrations\Realign\Targets\ProformaRequestsTarget;
use App\Models\Invoice;
use App\Models\ProformaRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function legacyRealignProformaRequest(array $overrides = []): array
{
    return array_merge([
        'id' => 10907,
        'work_order_id' => 20019,
        'quote_id' => 104988,
        'kind' => 'consultancy',
        'supplier_id' => null,
        'status' => 'pending',
        'issued_at' => null,
        'note' => 'Note',
        'assigned_to_user_id' => 127,
        'assigned_by_user_id' => 116,
        'warnings' => [],
        'created_at' => '2026-10-21 19:22:41',
        'updated_at' => '2026-10-25 19:41:47',
    ], $overrides);
}

beforeEach(function () {
    seedMigrationsConfig();
    $this->assignee = User::factory()->create(['old_id' => 127]);
    $this->requester = User::factory()->create(['old_id' => 116]);
    $this->request = ProformaRequest::factory()->create([
        'status' => ProformaRequestStatus::Pending,
        'issued_at' => null,
        'note' => 'Old note',
        'assigned_to' => $this->requester->id,
        'assigned_by' => $this->requester->id,
    ]);
    $this->request->forceFill(['old_id' => 10907])->saveQuietly();
});

/**
 * @param  array<string, mixed>  $legacy
 */
function realignProformaRequest(array $legacy): LegacyRealignReport
{
    fakeLegacyRealign('proforma-requests', [$legacy['id']], [], [$legacy]);
    $report = new LegacyRealignReport;
    app(LegacyRealignRunner::class)->run(app(ProformaRequestsTarget::class), '2026-10-23', true, $report);
    Http::swap(new Factory);

    return $report;
}

it('aligns status, issue date, note and people', function () {
    $report = realignProformaRequest(legacyRealignProformaRequest(['status' => 'issued', 'issued_at' => '2026-10-25 19:41:47', 'note' => 'New note']));

    $request = $this->request->fresh();

    expect($report->counters['proforma-requests'][LegacyRealignReport::ALIGNED])->toBe(1)
        ->and($request->status)->toBe(ProformaRequestStatus::Issued)
        ->and($request->issued_at->toDateTimeString())->toBe('2026-10-25 19:41:47')
        ->and($request->note)->toBe('New note')
        ->and($request->assigned_to)->toBe($this->assignee->id)
        ->and($request->updated_at->toDateTimeString())->toBe('2026-10-25 19:41:47');
});

it('keeps the issue date of a request already issued', function () {
    $this->request->forceFill(['status' => ProformaRequestStatus::Issued, 'issued_at' => '2026-10-10 10:00:00', 'note' => 'Note', 'assigned_to' => $this->assignee->id])->saveQuietly();

    $report = realignProformaRequest(legacyRealignProformaRequest(['status' => 'issued', 'issued_at' => '2026-11-01 10:00:00']));

    expect($this->request->fresh()->issued_at->toDateTimeString())->toBe('2026-10-10 10:00:00')
        ->and($report->counters['proforma-requests'][LegacyRealignReport::UNCHANGED])->toBe(1);
});

it('keeps issued a request that holds a document', function () {
    $this->request->forceFill(['status' => ProformaRequestStatus::Issued, 'issued_at' => '2026-10-10 10:00:00'])->saveQuietly();
    Invoice::factory()->create(['proforma_request_id' => $this->request->id]);

    realignProformaRequest(legacyRealignProformaRequest());

    expect($this->request->fresh()->status)->toBe(ProformaRequestStatus::Issued);
});

it('fails the record when the assignee is not migrated', function () {
    $report = realignProformaRequest(legacyRealignProformaRequest(['assigned_to_user_id' => 999, 'note' => 'Not written']));

    expect($report->counters['proforma-requests'][LegacyRealignReport::FAILED])->toBe(1)
        ->and($this->request->fresh()->note)->toBe('Old note');
});
