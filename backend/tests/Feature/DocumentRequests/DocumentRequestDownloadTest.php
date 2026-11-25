<?php

use App\Models\ArchivioSmartSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;

/*
| Download of an uploaded file through the backend (spec 0429, D-1): AC-001, AC-002.
*/

uses(RefreshDatabase::class);

const DOWNLOAD_URL = 'https://files.test/signed/abc123?sig=SECRETSIGNATURE';

function downloadPath(int $requestId, int $documentId): string
{
    return "/api/document-requests/{$requestId}/documents/{$documentId}/download";
}

beforeEach(function () {
    documentRequestConnection();
    $this->documentRequest = documentRequestWithFiles(documentRequestWorkOrder(), ['status' => 'in_progress']);
    Sanctum::actingAs(documentRequestUserWith(['view']));
});

it('AC-001: asks ArchivioSmart for the URL, fetches it and streams the file with its name and type', function () {
    Http::fake([
        DR_BASE_URL.'/*' => Http::response(['data' => ['url' => DOWNLOAD_URL, 'expires_in' => 300]], 200),
        'files.test/*' => Http::response('%PDF-binary-content', 200, ['Content-Type' => 'application/octet-stream']),
    ]);

    $response = $this->get(downloadPath($this->documentRequest->id, 101))->assertOk();

    expect($response->streamedContent())->toBe('%PDF-binary-content')
        ->and($response->headers->get('Content-Type'))->toContain('application/pdf')
        ->and($response->headers->get('Content-Disposition'))->toContain('attachment')->toContain('Carta identita.pdf');
    Http::assertSent(fn (Request $sent) => $sent->method() === 'GET'
        && $sent->url() === DR_BASE_URL."/requests/{$this->documentRequest->id}/documents/101/download"
        && $sent->hasHeader('Authorization', 'Bearer '.DR_TOKEN));
    Http::assertSent(fn (Request $sent) => $sent->url() === DOWNLOAD_URL && ! $sent->hasHeader('Authorization'));
});

it('AC-002: a document outside the snapshot answers 404 without calling ArchivioSmart', function () {
    Http::fake();
    $other = documentRequestWithFiles(documentRequestWorkOrder());
    $other->forceFill(['snapshot' => ['sections' => [], 'items' => [['id' => 11, 'documents' => [['id' => 777]]]]]])->save();

    $this->getJson(downloadPath($this->documentRequest->id, 999))->assertNotFound()->assertJsonPath('success', false);
    $this->getJson(downloadPath($this->documentRequest->id, 777))->assertNotFound();

    Http::assertNothingSent();
});

it('AC-002: answers 403 without view, or without view on the work order, and calls nothing', function (bool $canViewWorkOrders, array $abilities) {
    Http::fake();
    Sanctum::actingAs(documentRequestUserWith($abilities, $canViewWorkOrders));

    $this->getJson(downloadPath($this->documentRequest->id, 101))->assertForbidden();

    Http::assertNothingSent();
})->with([
    'no permission' => [true, []],
    'no work order' => [false, ['view']],
    'other abilities only' => [true, ['review', 'update']],
]);

it('AC-002: an ArchivioSmart 404 answers 404', function () {
    Http::fake([DR_BASE_URL.'/*' => Http::response(['message' => 'Not found'], 404)]);

    $this->getJson(downloadPath($this->documentRequest->id, 101))->assertNotFound()->assertJsonPath('success', false);
});

it('AC-002: failures of ArchivioSmart or of the file host answer 502 with a message and leak no URL, token or signature', function (Closure $fake) {
    $fake();
    $logged = [];
    Event::listen(MessageLogged::class, function (MessageLogged $event) use (&$logged) {
        $logged[] = $event->message.json_encode($event->context);
    });

    $response = $this->getJson(downloadPath($this->documentRequest->id, 101))->assertStatus(502)->assertJsonPath('success', false);

    expect($response->json('message'))->toBeString()->not->toBe('')
        ->and($response->getContent())->not->toContain('files.test')->not->toContain(DR_TOKEN)->not->toContain('SECRETSIGNATURE')
        ->and(json_encode($logged))->not->toContain('files.test')->not->toContain('SECRETSIGNATURE')->not->toContain(DR_TOKEN);
})->with([
    'api 500' => [fn () => Http::fake([DR_BASE_URL.'/*' => Http::response([], 500)])],
    'api unreachable' => [fn () => Http::fake([DR_BASE_URL.'/*' => fn () => throw new ConnectionException('cURL error for '.DOWNLOAD_URL)])],
    'file host 500' => [fn () => Http::fake([DR_BASE_URL.'/*' => Http::response(['data' => ['url' => DOWNLOAD_URL]], 200), 'files.test/*' => Http::response('', 500)])],
    'file host unreachable' => [fn () => Http::fake([DR_BASE_URL.'/*' => Http::response(['data' => ['url' => DOWNLOAD_URL]], 200), 'files.test/*' => fn () => throw new ConnectionException('cURL error for '.DOWNLOAD_URL)])],
    'url missing' => [fn () => Http::fake([DR_BASE_URL.'/*' => Http::response(['data' => []], 200)])],
    'url not http' => [fn () => Http::fake([DR_BASE_URL.'/*' => Http::response(['data' => ['url' => 'file:///etc/passwd']], 200)])],
    'url javascript' => [fn () => Http::fake([DR_BASE_URL.'/*' => Http::response(['data' => ['url' => 'javascript:alert(1)']], 200)])],
]);

it('AC-002: with no connection configured answers 502 and calls nothing', function () {
    ArchivioSmartSetting::query()->delete();
    Http::fake();

    $this->getJson(downloadPath($this->documentRequest->id, 101))->assertStatus(502);
    Http::assertNothingSent();
});
