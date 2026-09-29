<?php

use App\Mail\OutboundEmailMessage;
use App\Models\Attachment;
use App\Models\OutboundEmail;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\Graph\GraphMailException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

/*
|--------------------------------------------------------------------------
| Microsoft Graph transport (spec 0175, D-1, AC-015)
|--------------------------------------------------------------------------
|
| Exercised end-to-end through `Mail::mailer('microsoft-graph')->send()`
| (the exact call SendOutboundEmailJob makes) rather than by instantiating
| MicrosoftGraphTransport/GraphMailClient directly: this also proves the
| Mail::extend registration in AppServiceProvider and OutboundEmailMessage's
| own envelope/attachment mapping, not just GraphMailClient in isolation.
*/

uses(RefreshDatabase::class);

const GRAPH_AUTH_URL = 'https://login.test.example';
const GRAPH_BASE_URL = 'https://graph.test.example/v1.0';
const GRAPH_UPLOAD_URL = 'https://upload.test.example/session-abc';
const GRAPH_FROM = 'sender@example.com';

function configureGraphMailer(): void
{
    config([
        'mail.mailers.microsoft-graph.tenant' => 'contoso',
        'mail.mailers.microsoft-graph.client' => 'client-id',
        'mail.mailers.microsoft-graph.secret' => 'client-secret',
        'mail.mailers.microsoft-graph.base_url' => GRAPH_BASE_URL,
        'mail.mailers.microsoft-graph.auth_url' => GRAPH_AUTH_URL,
        'mail.mailers.microsoft-graph.timeout' => 5,
        'outbound_emails.mailer' => 'microsoft-graph',
    ]);
}

function graphOutboundEmail(): OutboundEmail
{
    // The sender's OWN `users.email` need not equal GRAPH_FROM (the User
    // factory's email must stay globally unique across the two emails built
    // by the "token cached" test): `from_address` below is the actual Graph
    // mailbox, snapshotted independently of the sender row (D-6).
    $sender = User::factory()->create(['name' => 'Mario Rossi']);

    return OutboundEmail::factory()
        ->forEmailable(WorkOrder::factory()->create())
        ->queued()
        ->create([
            'sender_user_id' => $sender->id,
            'from_address' => GRAPH_FROM,
            'to_recipients' => ['dest@example.com'],
            'cc_recipients' => ['cc@example.com'],
            'bcc_recipients' => ['bcc@example.com'],
            'subject' => 'Aggiornamento commessa',
            'body' => '<p>Corpo email</p>',
        ]);
}

function attachSmallFile(OutboundEmail $email): void
{
    $attachment = Attachment::factory()->for($email, 'attachable')->create([
        'collection' => OutboundEmail::ATTACHMENT_COLLECTION,
        'path' => 'attachments/small-'.$email->id.'.pdf',
        'original_name' => 'small.pdf',
        'mime_type' => 'application/pdf',
        'size' => 11,
    ]);

    Storage::disk($attachment->disk)->put($attachment->path, 'small-file');
}

/**
 * 3.5 MB, forcing 2 upload-session chunks with the 3.2 MB (3276800 B) chunk
 * size: a 3276800-byte chunk then a 223200-byte remainder.
 */
function attachLargeFile(OutboundEmail $email): string
{
    $content = str_repeat('A', 3_500_000);

    $attachment = Attachment::factory()->for($email, 'attachable')->create([
        'collection' => OutboundEmail::ATTACHMENT_COLLECTION,
        'path' => 'attachments/large.bin',
        'original_name' => 'large.bin',
        'mime_type' => 'application/octet-stream',
        'size' => strlen($content),
    ]);

    Storage::disk($attachment->disk)->put($attachment->path, $content);

    return $content;
}

beforeEach(function () {
    Storage::fake('local');
    configureGraphMailer();
});

it('AC-015: acquires the token once, creates the draft with recipients, attaches inline and sends', function () {
    Http::fake([
        GRAPH_AUTH_URL.'/contoso/oauth2/v2.0/token' => Http::response(['access_token' => 'tok-1', 'expires_in' => 3600]),
        GRAPH_BASE_URL.'/users/'.GRAPH_FROM.'/messages' => Http::response(['id' => 'draft-1'], 201),
        GRAPH_BASE_URL.'/users/'.GRAPH_FROM.'/messages/draft-1/attachments' => Http::response(['id' => 'att-1'], 201),
        GRAPH_BASE_URL.'/users/'.GRAPH_FROM.'/messages/draft-1/send' => Http::response([], 202),
    ]);

    $emailOne = graphOutboundEmail();
    attachSmallFile($emailOne);
    $emailTwo = graphOutboundEmail();
    attachSmallFile($emailTwo);

    // Two full sends: proves the token is fetched ONCE and reused from cache.
    Mail::mailer('microsoft-graph')->send(new OutboundEmailMessage($emailOne));
    Mail::mailer('microsoft-graph')->send(new OutboundEmailMessage($emailTwo));

    expect(Http::recorded(fn (Request $request) => str_contains($request->url(), '/oauth2/v2.0/token'))->count())->toBe(1);

    Http::assertSent(function (Request $request) {
        if ($request->url() !== GRAPH_BASE_URL.'/users/'.GRAPH_FROM.'/messages' || $request->method() !== 'POST') {
            return false;
        }

        $body = $request->data();

        return $body['subject'] === 'Aggiornamento commessa'
            && str_contains($body['body']['content'], 'Corpo email')
            && $body['toRecipients'][0]['emailAddress']['address'] === 'dest@example.com'
            && $body['ccRecipients'][0]['emailAddress']['address'] === 'cc@example.com'
            && $body['bccRecipients'][0]['emailAddress']['address'] === 'bcc@example.com';
    });

    Http::assertSent(function (Request $request) {
        if (! str_ends_with($request->url(), '/messages/draft-1/attachments') || $request->method() !== 'POST') {
            return false;
        }

        return $request->data()['name'] === 'small.pdf'
            && $request->data()['contentBytes'] === base64_encode('small-file');
    });

    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/messages/draft-1/send'));
});

it('AC-015: uploads an attachment at or above 3 MB through a chunked upload session', function () {
    Http::fake([
        GRAPH_AUTH_URL.'/contoso/oauth2/v2.0/token' => Http::response(['access_token' => 'tok-1', 'expires_in' => 3600]),
        GRAPH_BASE_URL.'/users/'.GRAPH_FROM.'/messages' => Http::response(['id' => 'draft-1'], 201),
        GRAPH_BASE_URL.'/users/'.GRAPH_FROM.'/messages/draft-1/attachments/createUploadSession' => Http::response(['uploadUrl' => GRAPH_UPLOAD_URL], 200),
        GRAPH_UPLOAD_URL => Http::response(['id' => 'session-item'], 200),
        GRAPH_BASE_URL.'/users/'.GRAPH_FROM.'/messages/draft-1/send' => Http::response([], 202),
    ]);

    $email = graphOutboundEmail();
    $content = attachLargeFile($email);

    Mail::mailer('microsoft-graph')->send(new OutboundEmailMessage($email));

    Http::assertSent(function (Request $request) {
        if (! str_ends_with($request->url(), '/attachments/createUploadSession')) {
            return false;
        }

        return $request->data()['AttachmentItem']['name'] === 'large.bin'
            && $request->data()['AttachmentItem']['size'] === 3_500_000;
    });

    $chunks = collect(Http::recorded(fn (Request $request) => $request->url() === GRAPH_UPLOAD_URL && $request->method() === 'PUT'));

    expect($chunks)->toHaveCount(2);

    [$first] = $chunks->first();
    [$second] = $chunks->last();

    expect($first->header('Content-Range'))->toBe(['bytes 0-3276799/3500000'])
        ->and($first->body())->toHaveLength(3276800)
        ->and($second->header('Content-Range'))->toBe(['bytes 3276800-3499999/3500000'])
        ->and($second->body())->toHaveLength(223200)
        ->and($first->body().$second->body())->toBe($content);
});

it('AC-015: ErrorInvalidUser raises a GraphMailException with a readable, safe message', function () {
    Http::fake([
        GRAPH_AUTH_URL.'/contoso/oauth2/v2.0/token' => Http::response(['access_token' => 'tok-1', 'expires_in' => 3600]),
        GRAPH_BASE_URL.'/users/'.GRAPH_FROM.'/messages' => Http::response([
            'error' => ['code' => 'ErrorInvalidUser', 'message' => 'The SMTP address has no mailbox associated with it.'],
        ], 400),
    ]);

    $email = graphOutboundEmail();

    $thrown = null;

    try {
        Mail::mailer('microsoft-graph')->send(new OutboundEmailMessage($email));
    } catch (Throwable $exception) {
        $thrown = $exception;
    }

    expect($thrown)->toBeInstanceOf(GraphMailException::class)
        ->and($thrown->getMessage())->toContain(GRAPH_FROM)
        ->and($thrown->getMessage())->not->toContain('SMTP address has no mailbox');
});
