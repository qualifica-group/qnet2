<?php

use App\Enums\OutboundEmailStatus;
use App\Jobs\SendOutboundEmailJob;
use App\Mail\OutboundEmailMessage;
use App\Models\Attachment;
use App\Models\OutboundEmail;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\Graph\GraphMailException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

/*
|--------------------------------------------------------------------------
| SendOutboundEmailJob (spec 0175, D-12, AC-013)
|--------------------------------------------------------------------------
*/

uses(RefreshDatabase::class);

function queuedOutboundEmailWithAttachment(): OutboundEmail
{
    $sender = User::factory()->create(['name' => 'Mario Rossi', 'email' => 'mario@example.com']);

    $email = OutboundEmail::factory()
        ->forEmailable(WorkOrder::factory()->create())
        ->queued()
        ->create([
            'sender_user_id' => $sender->id,
            'from_address' => 'mario@example.com',
            'to_recipients' => ['dest@example.com'],
            'cc_recipients' => ['cc@example.com'],
            'bcc_recipients' => [],
            'subject' => 'Aggiornamento commessa',
            'body' => '<p>Corpo email</p>',
        ]);

    $attachment = Attachment::factory()->for($email, 'attachable')->create([
        'collection' => OutboundEmail::ATTACHMENT_COLLECTION,
        'path' => 'attachments/report.pdf',
        'original_name' => 'report.pdf',
        'mime_type' => 'application/pdf',
    ]);

    Storage::disk($attachment->disk)->put($attachment->path, 'pdf-bytes');

    return $email;
}

beforeEach(function () {
    Storage::fake('local');
});

it('AC-013: sends one OutboundEmailMessage with from/to/cc/subject/body/attachments and marks the email sent', function () {
    Mail::fake();

    $email = queuedOutboundEmailWithAttachment();

    (new SendOutboundEmailJob($email))->handle();

    Mail::assertSentCount(1);

    Mail::assertSent(OutboundEmailMessage::class, function (OutboundEmailMessage $mail) {
        $envelope = $mail->envelope();
        $content = $mail->content();

        return (string) $envelope->from->address === 'mario@example.com'
            && $envelope->from->name === 'Mario Rossi'
            && $envelope->to[0]->address === 'dest@example.com'
            && $envelope->cc[0]->address === 'cc@example.com'
            && $envelope->bcc === []
            && $envelope->subject === 'Aggiornamento commessa'
            && $content->with['body'] === '<p>Corpo email</p>'
            && count($mail->attachments()) === 1;
    });

    $email->refresh();

    expect($email->status)->toBe(OutboundEmailStatus::Sent)
        ->and($email->sent_at)->not->toBeNull()
        ->and($email->failed_at)->toBeNull();
});

it('AC-013: a mailer exception moves the email to failed with error_message and does not rethrow', function () {
    Mail::shouldReceive('mailer')
        ->once()
        ->with('log')
        ->andReturnSelf();
    Mail::shouldReceive('send')
        ->once()
        ->andThrow(GraphMailException::invalidSender('mario@example.com'));

    config(['outbound_emails.mailer' => 'log']);

    $email = queuedOutboundEmailWithAttachment();

    (new SendOutboundEmailJob($email))->handle();

    $email->refresh();

    expect($email->status)->toBe(OutboundEmailStatus::Failed)
        ->and($email->failed_at)->not->toBeNull()
        ->and($email->error_message)->toBe(__('outbound_emails.graph_invalid_sender', ['email' => 'mario@example.com']));
});

it('AC-013: a non-Graph mailer exception falls back to the generic translated message', function () {
    Mail::shouldReceive('mailer')->once()->with('log')->andReturnSelf();
    Mail::shouldReceive('send')->once()->andThrow(new RuntimeException('connection refused'));

    config(['outbound_emails.mailer' => 'log']);

    $email = queuedOutboundEmailWithAttachment();

    (new SendOutboundEmailJob($email))->handle();

    expect($email->fresh()->error_message)->toBe(__('outbound_emails.job_generic_failure'));
});

it('AC-013: a job whose email is not queued sends nothing', function () {
    Mail::fake();

    $email = OutboundEmail::factory()->forEmailable(WorkOrder::factory()->create())->create();

    expect($email->status)->toBe(OutboundEmailStatus::Draft);

    (new SendOutboundEmailJob($email))->handle();

    Mail::assertNothingSent();
    expect($email->fresh()->status)->toBe(OutboundEmailStatus::Draft);
});

it('failed(): a queue-worker kill marks a still-queued email failed', function () {
    $email = OutboundEmail::factory()->forEmailable(WorkOrder::factory()->create())->queued()->create();

    (new SendOutboundEmailJob($email))->failed(new RuntimeException('worker timed out'));

    $email->refresh();

    expect($email->status)->toBe(OutboundEmailStatus::Failed)
        ->and($email->error_message)->toBe(__('outbound_emails.job_generic_failure'));
});

it('failed(): a no-longer-queued email is left untouched', function () {
    $email = OutboundEmail::factory()->forEmailable(WorkOrder::factory()->create())->sent()->create();

    (new SendOutboundEmailJob($email))->failed(new RuntimeException('worker timed out'));

    expect($email->fresh()->status)->toBe(OutboundEmailStatus::Sent);
});
