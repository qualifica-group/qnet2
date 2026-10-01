<?php

use App\Enums\OutboundEmailStatus;
use App\Jobs\SendOutboundEmailJob;
use App\Mail\OutboundEmailMessage;
use App\Mail\StagingMailRedirector;
use App\Models\OutboundEmail;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/*
|--------------------------------------------------------------------------
| StagingMailRedirector x OutboundEmail (spec 0175, D-1)
|--------------------------------------------------------------------------
|
| OutboundEmail deliberately sends through a NAMED mailer
| (`Mail::mailer(config('outbound_emails.mailer'))`) rather than the app's
| default, so it can point at `microsoft-graph` independently of whatever
| `mail.default` is (D-1). `Mailer::alwaysTo()` is set per Mailer INSTANCE,
| so redirecting only the default mailer would leave a real customer address
| reachable through Graph in staging. StagingMailRedirector::handle()
| therefore redirects every DISTINCT mailer name this app sends through
| (default + `outbound_emails.mailer`), so both cases below land on the
| staging mailbox -- aligned or diverging from mail.default.
*/

uses(RefreshDatabase::class);

function stagingOutboundEmail(string $fromAddress): OutboundEmail
{
    $sender = User::factory()->create(['name' => 'Mario Rossi']);

    return OutboundEmail::factory()
        ->forEmailable(WorkOrder::factory()->create())
        ->queued()
        ->create([
            'sender_user_id' => $sender->id,
            'from_address' => $fromAddress,
            'to_recipients' => ['real-recipient@example.com'],
            'cc_recipients' => [],
            'bcc_recipients' => [],
        ]);
}

it('redirects an OutboundEmail send when outbound_emails.mailer matches mail.default', function () {
    config([
        'mail.default' => 'array',
        'mail.always_to' => 'staging-inbox@example.com',
        'outbound_emails.mailer' => 'array',
    ]);

    app(StagingMailRedirector::class)->handle();

    $email = stagingOutboundEmail('mario@example.com');

    Mail::mailer(config('outbound_emails.mailer'))->send(new OutboundEmailMessage($email));

    /** @var Email $sent */
    $sent = Mail::getSymfonyTransport()->messages()->last()->getOriginalMessage();

    expect(array_map(fn (Address $a) => $a->getAddress(), $sent->getTo()))
        ->toBe(['staging-inbox@example.com']);
});

it('also redirects an OutboundEmail send, sender mailbox included, when outbound_emails.mailer diverges from mail.default', function () {
    config([
        'mail.default' => 'array',
        'mail.always_to' => 'staging-inbox@example.com',
        'outbound_emails.mailer' => 'microsoft-graph',
        'mail.mailers.microsoft-graph.tenant' => 'contoso',
        'mail.mailers.microsoft-graph.client' => 'client-id',
        'mail.mailers.microsoft-graph.secret' => 'client-secret',
        'mail.mailers.microsoft-graph.base_url' => 'https://graph.test.example/v1.0',
        'mail.mailers.microsoft-graph.auth_url' => 'https://login.test.example',
    ]);

    app(StagingMailRedirector::class)->handle();

    Http::fake([
        'https://login.test.example/*' => Http::response(['access_token' => 'tok-1', 'expires_in' => 3600]),
        'https://graph.test.example/v1.0/users/staging-inbox@example.com/messages' => Http::response(['id' => 'draft-1'], 201),
        'https://graph.test.example/v1.0/users/staging-inbox@example.com/messages/draft-1/send' => Http::response([], 202),
    ]);

    $email = stagingOutboundEmail('mario@example.com');

    Mail::mailer(config('outbound_emails.mailer'))->send(new OutboundEmailMessage($email));

    Http::assertSent(function (Request $request) {
        if ($request->url() !== 'https://graph.test.example/v1.0/users/staging-inbox@example.com/messages') {
            return false;
        }

        $to = $request->data()['toRecipients'];

        return count($to) === 1 && $to[0]['emailAddress']['address'] === 'staging-inbox@example.com';
    });

    Http::assertNotSent(fn (Request $request) => str_contains($request->url(), '/users/mario@example.com/'));
});

it('leaves an unknown outbound_emails.mailer name from breaking the default redirect', function () {
    config([
        'mail.default' => 'array',
        'mail.always_to' => 'staging-inbox@example.com',
        'outbound_emails.mailer' => 'does-not-exist',
    ]);

    app(StagingMailRedirector::class)->handle();

    Mail::raw('body', fn ($message) => $message->to('real@example.com')->subject('probe'));

    /** @var Email $sent */
    $sent = Mail::getSymfonyTransport()->messages()->last()->getOriginalMessage();

    expect(array_map(fn (Address $a) => $a->getAddress(), $sent->getTo()))
        ->toBe(['staging-inbox@example.com']);
});

it('marks an OutboundEmail failed instead of sending it in staging without a redirect address', function () {
    app()->detectEnvironment(fn () => 'staging');

    config([
        'mail.default' => 'array',
        'mail.always_to' => null,
        'outbound_emails.mailer' => 'array',
    ]);

    app(StagingMailRedirector::class)->handle();

    $email = stagingOutboundEmail('mario@example.com');

    (new SendOutboundEmailJob($email))->handle();

    expect($email->fresh()->status)->toBe(OutboundEmailStatus::Failed)
        ->and(Mail::getSymfonyTransport()->messages())->toHaveCount(0);
});
