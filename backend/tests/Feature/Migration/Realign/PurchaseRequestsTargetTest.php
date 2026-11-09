<?php

use App\Enums\PurchaseRequestLineStatus;
use App\Enums\PurchaseRequestStatus;
use App\Migrations\Realign\LegacyRealignReport;
use App\Migrations\Realign\LegacyRealignRunner;
use App\Migrations\Realign\Targets\PurchaseRequestsTarget;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestLine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function () {
    seedMigrationsConfig();
    $this->anchors = legacyPurchaseRequestAnchors();
    runLegacyPurchaseRequestsImport([legacyPurchaseRequestRecord([
        'lines' => [legacyPurchaseRequestLine(), legacyPurchaseRequestLine(['id' => 901, 'position' => 2, 'description' => 'To disappear'])],
    ])]);
    Http::swap(new Factory);
});

/**
 * @param  array<string, mixed>  $legacy
 */
function realignPurchaseRequest(array $legacy, bool $apply = true): LegacyRealignReport
{
    fakeLegacyRealign('purchase-requests', [$legacy['id']], [], [$legacy]);
    $report = new LegacyRealignReport;
    app(LegacyRealignRunner::class)->run(app(PurchaseRequestsTarget::class), '2026-10-23', $apply, $report);
    Http::swap(new Factory);

    return $report;
}

it('AC-016 aligns header, closure and lines by old_id, keeps a line gone and creates a new one', function () {
    $closer = User::factory()->create(['old_id' => 25]);

    $report = realignPurchaseRequest(legacyPurchaseRequestRecord([
        'subject' => 'Changed subject',
        'notes' => 'Changed notes',
        'is_closed' => true,
        'closed_by_user_id' => 25,
        'closed_at' => '2026-10-25 10:00:00',
        'close_reason' => 'Not needed',
        'updated_at' => '2026-10-25 10:00:00',
        'lines' => [
            legacyPurchaseRequestLine(['quantity' => 4, 'status' => 'approved', 'status_logs' => [
                ['user_id' => 10, 'from_status' => 'pending_approval', 'to_status' => 'approved', 'reason' => null, 'bulk_group_id' => null, 'created_at' => '2026-10-25 09:00:00'],
            ]]),
            legacyPurchaseRequestLine(['id' => 902, 'position' => 3, 'description' => 'New line', 'quantity' => 1, 'unit_price' => 5]),
        ],
    ]));

    $request = PurchaseRequest::query()->where('old_id', 600)->firstOrFail();
    $line = PurchaseRequestLine::query()->where('old_id', 900)->firstOrFail();

    expect($report->counters['purchase-requests'][LegacyRealignReport::ALIGNED])->toBe(1)
        ->and($request->subject)->toBe('Changed subject')
        ->and($request->status)->toBe(PurchaseRequestStatus::Closed)
        ->and($request->closed_by)->toBe($closer->id)
        ->and($request->close_reason)->toBe('Not needed')
        ->and($request->updated_at->toDateTimeString())->toBe('2026-10-25 10:00:00')
        ->and((float) $line->quantity)->toBe(4.0)
        ->and((float) $line->taxable_amount)->toBe(42.0)
        ->and($line->status)->toBe(PurchaseRequestLineStatus::Approved)
        ->and((float) $request->grand_total)->toBe(68.0)
        ->and(PurchaseRequestLine::query()->where('old_id', 902)->exists())->toBeTrue()
        ->and(PurchaseRequestLine::query()->where('old_id', 901)->exists())->toBeTrue()
        ->and($report->messages)->toContain(['purchase-requests', '600', 'warning', 'Line 901 is no longer in the legacy; kept.'])
        ->and(Activity::query()->where('subject_type', 'purchase_request')->count())->toBe(0);
});

it('AC-016 reports without writing in a simulation and appends a status change only once', function () {
    $legacy = legacyPurchaseRequestRecord(['subject' => 'Simulated', 'lines' => [
        legacyPurchaseRequestLine(['status' => 'approved', 'status_logs' => [
            ['user_id' => 10, 'from_status' => 'pending_approval', 'to_status' => 'approved', 'reason' => null, 'bulk_group_id' => null, 'created_at' => '2026-10-25 09:00:00'],
        ]]),
        legacyPurchaseRequestLine(['id' => 901, 'position' => 2, 'description' => 'To disappear']),
    ]]);

    $simulated = realignPurchaseRequest($legacy, apply: false);
    expect(PurchaseRequest::query()->where('old_id', 600)->value('subject'))->toBe('Legacy RDA');

    $applied = realignPurchaseRequest($legacy);
    $second = realignPurchaseRequest($legacy);
    $line = PurchaseRequestLine::query()->where('old_id', 900)->firstOrFail();

    expect($applied->changes)->toBe($simulated->changes)
        ->and($second->counters['purchase-requests'][LegacyRealignReport::UNCHANGED])->toBe(1)
        ->and($second->changes)->toBe([])
        ->and(DB::table('purchase_request_line_status_logs')->where('purchase_request_line_id', $line->id)->count())->toBe(1);
});

it('AC-025 keeps a line received on qnet-2 when the legacy says ordered', function () {
    PurchaseRequestLine::query()->where('old_id', 900)->update(['status' => PurchaseRequestLineStatus::Received]);

    $report = realignPurchaseRequest(legacyPurchaseRequestRecord([
        'lines' => [legacyPurchaseRequestLine(['status' => 'ordered', 'description' => 'Changed line']), legacyPurchaseRequestLine(['id' => 901, 'position' => 2, 'description' => 'To disappear'])],
    ]));

    $line = PurchaseRequestLine::query()->where('old_id', 900)->firstOrFail();

    expect($line->status)->toBe(PurchaseRequestLineStatus::Received)
        ->and($line->description)->toBe('Changed line')
        ->and(collect($report->messages)->pluck(3)->implode(' '))->toContain('kept as received');
});

it('fails the record when a required person is not migrated', function () {
    $report = realignPurchaseRequest(legacyPurchaseRequestRecord(['subject' => 'Not written', 'requester_user_id' => 999]));

    expect($report->counters['purchase-requests'][LegacyRealignReport::FAILED])->toBe(1)
        ->and(PurchaseRequest::query()->where('old_id', 600)->value('subject'))->toBe('Legacy RDA');
});

it('leaves a request whose line was approved on qnet-2', function () {
    $line = PurchaseRequestLine::query()->where('old_id', 900)->firstOrFail();
    activity()->performedOn($line)->event('updated')->log('updated');

    $report = realignPurchaseRequest(legacyPurchaseRequestRecord(['subject' => 'Not written']));

    expect($report->counters['purchase-requests'][LegacyRealignReport::SKIPPED_TOUCHED])->toBe(1)
        ->and(PurchaseRequest::query()->where('old_id', 600)->value('subject'))->toBe('Legacy RDA');
});
