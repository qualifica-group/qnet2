<?php

use App\Enums\OutboundEmailRecurrenceEndReason;
use App\Enums\OutboundEmailStatus;
use App\Jobs\SendOutboundEmailJob;
use App\Models\Attachment;
use App\Models\OutboundEmail;
use App\Models\User;
use App\Models\WorkOrder;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

/*
|--------------------------------------------------------------------------
| outbound-emails:send-recurrences (spec 0362, D-5/D-7/D-9/D-10/D-11)
|--------------------------------------------------------------------------
*/

uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake();
    Storage::fake('local');
});

function runRecurrences(string $at, bool $dryRun = false): void
{
    test()->travelTo(CarbonImmutable::parse($at, config('app.timezone')));
    test()->artisan('outbound-emails:send-recurrences', $dryRun ? ['--dry-run' => true] : [])->assertSuccessful();
}

/**
 * @return array{0: WorkOrder, 1: User}
 */
function recurrenceFixture(): array
{
    $workOrder = WorkOrder::factory()->create();

    return [$workOrder, workOrderEmailActor($workOrder, ['sendEmail'])];
}

function copiesOf(OutboundEmail $programmed)
{
    return OutboundEmail::query()->where('outbound_email_recurrence_id', $programmed->recurrence->id)->orderBy('occurrence_on')->get();
}

it('AC-008: a due series creates ONE queued copy identical to the programmed email and advances', function () {
    [$workOrder, $author] = recurrenceFixture();
    $email = programmedEmail($workOrder, $author, [
        'frequency' => 'weekly', 'weekdays' => [1], 'starts_on' => '2026-11-02', 'ends_on' => '2026-12-28', 'next_send_on' => '2026-11-02',
    ], ['bcc_recipients' => ['ccn@example.com']]);
    $source = Attachment::factory()->for($email, 'attachable')->create(['collection' => OutboundEmail::ATTACHMENT_COLLECTION, 'original_name' => 'offerta.pdf', 'size' => 2048]);
    Storage::disk('local')->put($source->path, 'hello');

    runRecurrences('2026-11-02 09:00');

    $copy = copiesOf($email)->sole();
    expect($copy->status)->toBe(OutboundEmailStatus::Queued)
        ->and($copy->to_recipients)->toBe($email->to_recipients)
        ->and($copy->cc_recipients)->toBe($email->cc_recipients)
        ->and($copy->bcc_recipients)->toBe(['ccn@example.com'])
        ->and($copy->subject)->toBe($email->subject)
        ->and($copy->body)->toBe($email->body)
        ->and($copy->sender_user_id)->toBe($author->id)
        ->and($copy->from_address)->toBe($author->email)
        ->and($copy->occurrence_on->toDateString())->toBe('2026-11-02')
        ->and($copy->emailable_id)->toBe($workOrder->id);

    $copied = $copy->attachments()->where('collection', OutboundEmail::ATTACHMENT_COLLECTION)->sole();
    expect($copied->original_name)->toBe('offerta.pdf')
        ->and($copied->size)->toBe(2048)
        ->and($copied->path)->not->toBe($source->path);

    $series = $email->recurrence->fresh();
    expect($series->sent_count)->toBe(1)
        ->and($series->last_sent_on->toDateString())->toBe('2026-11-02')
        ->and($series->next_send_on->toDateString())->toBe('2026-11-09')
        ->and($email->fresh()->status)->toBe(OutboundEmailStatus::Scheduled);

    Queue::assertPushed(SendOutboundEmailJob::class, 1);
});

it('AC-009: a second run on the same day creates nothing', function () {
    [$workOrder, $author] = recurrenceFixture();
    $email = programmedEmail($workOrder, $author, ['frequency' => 'daily', 'starts_on' => '2026-11-02', 'ends_on' => '2026-12-28', 'next_send_on' => '2026-11-02']);

    runRecurrences('2026-11-02 09:00');
    runRecurrences('2026-11-02 15:00');

    expect(copiesOf($email))->toHaveCount(1);
    Queue::assertPushed(SendOutboundEmailJob::class, 1);
});

it('AC-010: before send_at nothing is sent', function () {
    [$workOrder, $author] = recurrenceFixture();
    $email = programmedEmail($workOrder, $author, ['frequency' => 'daily', 'starts_on' => '2026-11-02', 'ends_on' => '2026-12-28', 'next_send_on' => '2026-11-02']);

    runRecurrences('2026-11-02 07:59');
    expect(copiesOf($email))->toHaveCount(0);

    runRecurrences('2026-11-02 08:00');
    expect(copiesOf($email))->toHaveCount(1);
});

it('AC-011: the last send on ends_on ends the series; so does a next date beyond ends_on', function () {
    [$workOrder, $author] = recurrenceFixture();
    $onEnd = programmedEmail($workOrder, $author, ['frequency' => 'daily', 'starts_on' => '2026-11-02', 'ends_on' => '2026-11-04', 'next_send_on' => '2026-11-04']);
    $beyond = programmedEmail($workOrder, $author, ['frequency' => 'weekly', 'weekdays' => [1], 'starts_on' => '2026-11-02', 'ends_on' => '2026-11-04', 'next_send_on' => '2026-11-02']);

    runRecurrences('2026-11-04 09:00');
    runRecurrences('2026-11-02 09:00');

    foreach ([$onEnd, $beyond] as $email) {
        expect(copiesOf($email))->toHaveCount(1)
            ->and($email->fresh()->status)->toBe(OutboundEmailStatus::ScheduleEnded)
            ->and($email->recurrence->fresh()->end_reason)->toBe(OutboundEmailRecurrenceEndReason::EndsOnReached)
            ->and($email->recurrence->fresh()->next_send_on)->toBeNull();
    }
});

it('AC-012: a work order no longer in progress ends the series without a copy', function () {
    [$workOrder, $author] = recurrenceFixture();
    $email = programmedEmail($workOrder, $author, ['next_send_on' => '2026-11-02', 'starts_on' => '2026-11-02', 'ends_on' => '2026-12-28']);
    $workOrder->forceFill(['is_force_closed' => true])->save();

    runRecurrences('2026-11-02 09:00');

    expect(copiesOf($email))->toHaveCount(0)
        ->and($email->fresh()->status)->toBe(OutboundEmailStatus::ScheduleEnded)
        ->and($email->recurrence->fresh()->end_reason)->toBe(OutboundEmailRecurrenceEndReason::OwnerClosed);
    Queue::assertNothingPushed();
});

it('AC-013: an unavailable author ends the series without a copy', function (array $authorState) {
    [$workOrder, $author] = recurrenceFixture();
    $email = programmedEmail($workOrder, $author, ['next_send_on' => '2026-11-02', 'starts_on' => '2026-11-02', 'ends_on' => '2026-12-28']);
    $author->forceFill($authorState)->save();

    runRecurrences('2026-11-02 09:00');

    expect(copiesOf($email))->toHaveCount(0)
        ->and($email->fresh()->status)->toBe(OutboundEmailStatus::ScheduleEnded)
        ->and($email->recurrence->fresh()->end_reason)->toBe(OutboundEmailRecurrenceEndReason::SenderUnavailable);
    Queue::assertNothingPushed();
})->with([
    'deactivated' => [['is_active' => false]],
    'without email' => [['email' => '']],
]);

it('AC-014: a series late by days sends once and moves to tomorrow', function () {
    [$workOrder, $author] = recurrenceFixture();
    $email = programmedEmail($workOrder, $author, ['frequency' => 'daily', 'starts_on' => '2026-10-23', 'ends_on' => '2026-12-28', 'next_send_on' => '2026-10-23']);

    runRecurrences('2026-11-02 09:00');

    $copy = copiesOf($email)->sole();
    expect($copy->occurrence_on->toDateString())->toBe('2026-11-02')
        ->and($email->recurrence->fresh()->next_send_on->toDateString())->toBe('2026-11-03');
});

it('AC-015: workdays_only shifts a Saturday occurrence to the following Monday', function () {
    [$workOrder, $author] = recurrenceFixture();
    $email = programmedEmail($workOrder, $author, [
        'frequency' => 'weekly', 'weekdays' => [6], 'workdays_only' => true,
        'starts_on' => '2026-11-02', 'ends_on' => '2026-12-28', 'next_send_on' => '2026-11-02',
    ]);

    runRecurrences('2026-11-02 09:00');

    expect($email->recurrence->fresh()->next_send_on->toDateString())->toBe('2026-11-09');

    runRecurrences('2026-11-09 09:00');

    expect(copiesOf($email))->toHaveCount(2)
        ->and($email->recurrence->fresh()->next_send_on->toDateString())->toBe('2026-11-16');
});

it('AC-016: a failed copy does not stop the series', function () {
    [$workOrder, $author] = recurrenceFixture();
    $email = programmedEmail($workOrder, $author, ['frequency' => 'daily', 'starts_on' => '2026-11-02', 'ends_on' => '2026-12-28', 'next_send_on' => '2026-11-02']);

    runRecurrences('2026-11-02 09:00');
    copiesOf($email)->sole()->markFailed('boom');
    runRecurrences('2026-11-03 09:00');

    $copies = copiesOf($email);
    expect($copies)->toHaveCount(2)
        ->and($copies->first()->status)->toBe(OutboundEmailStatus::Failed)
        ->and($copies->last()->status)->toBe(OutboundEmailStatus::Queued)
        ->and($email->fresh()->status)->toBe(OutboundEmailStatus::Scheduled);
});

it('AC-017: an unscheduled (draft) email is ignored by the command', function () {
    [$workOrder, $author] = recurrenceFixture();
    $email = programmedEmail($workOrder, $author, ['next_send_on' => null, 'starts_on' => '2026-11-02', 'ends_on' => '2026-12-28'], ['status' => OutboundEmailStatus::Draft]);
    $stale = programmedEmail($workOrder, $author, ['next_send_on' => '2026-11-02', 'starts_on' => '2026-11-02', 'ends_on' => '2026-12-28'], ['status' => OutboundEmailStatus::Draft]);

    runRecurrences('2026-11-02 09:00');

    expect(copiesOf($email))->toHaveCount(0)->and(copiesOf($stale))->toHaveCount(0);
    Queue::assertNothingPushed();
});

it('AC-008: --dry-run reports without writing', function () {
    [$workOrder, $author] = recurrenceFixture();
    $email = programmedEmail($workOrder, $author, ['frequency' => 'daily', 'starts_on' => '2026-11-02', 'ends_on' => '2026-12-28', 'next_send_on' => '2026-11-02']);

    runRecurrences('2026-11-02 09:00', dryRun: true);

    expect(copiesOf($email))->toHaveCount(0)
        ->and($email->recurrence->fresh()->next_send_on->toDateString())->toBe('2026-11-02');
    Queue::assertNothingPushed();
});

it('AC-025: the command is scheduled hourly', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($event) => str_contains($event->command, 'outbound-emails:send-recurrences'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('0 * * * *')
        ->and($event->withoutOverlapping)->toBeTrue()
        ->and($event->onOneServer)->toBeTrue();
});
