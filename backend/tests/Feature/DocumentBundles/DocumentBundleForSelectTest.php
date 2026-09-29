<?php

use App\Models\DocumentBundle;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

/*
|--------------------------------------------------------------------------
| GET /api/document-bundles/for-select (spec 0175, ADR 0011)
|--------------------------------------------------------------------------
*/

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
});

it('requires authentication (401)', function () {
    $this->getJson('/api/document-bundles/for-select')->assertUnauthorized();
});

it('lists only is_active rows with meta.files_count, no permission gate beyond auth:sanctum', function () {
    // No document-bundles.* permission granted at all (ADR 0011).
    Sanctum::actingAs(User::factory()->create());

    $active = DocumentBundle::factory()->create(['name' => 'Attivo']);
    $active->attach(UploadedFile::fake()->create('doc.pdf', 8, 'application/pdf'), 'documents');
    DocumentBundle::factory()->inactive()->create(['name' => 'Inattivo']);

    $response = $this->getJson('/api/document-bundles/for-select')->assertOk();

    $items = collect($response->json('items'));
    expect($items->pluck('label'))->toContain('Attivo')->not->toContain('Inattivo')
        ->and($items->firstWhere('label', 'Attivo')['meta']['files_count'])->toBe(1);
});
