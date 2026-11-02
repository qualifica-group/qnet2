<?php

use App\Models\Attachment;
use App\Models\DocumentLayout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

// `document-layouts:restore-images` — writes back the missing binaries of the
// seeded layout images from database/seeders/assets, at the row's own path.

const RESTORE_LETTERHEAD = 'quote-letterhead.png';
const RESTORE_QUOTE_IMAGE = 'image-cffbd749045c.png';

beforeEach(function () {
    Storage::fake('local');
    $this->layout = DocumentLayout::factory()->create();
});

function restoreAssetPath(string $file): string
{
    return $file === RESTORE_LETTERHEAD
        ? database_path('seeders/assets/'.$file)
        : database_path('seeders/assets/quote-layouts/images/'.$file);
}

/**
 * @param  array<string, mixed>  $overrides
 */
function layoutImageRow(DocumentLayout $layout, string $file, array $overrides = []): Attachment
{
    return Attachment::factory()->for($layout, 'attachable')->create([
        'collection' => DocumentLayout::IMAGE_COLLECTION,
        'path' => 'attachments/'.fake()->uuid().'.png',
        'original_name' => $file,
        'mime_type' => 'image/png',
        'extension' => 'png',
        'size' => is_file(restoreAssetPath($file)) ? filesize(restoreAssetPath($file)) : 10,
        ...$overrides,
    ]);
}

it('writes a missing seeded image back at its own path, byte for byte', function () {
    $letterhead = layoutImageRow($this->layout, RESTORE_LETTERHEAD);
    $image = layoutImageRow($this->layout, RESTORE_QUOTE_IMAGE);

    $this->artisan('document-layouts:restore-images')
        ->expectsOutputToContain('2 image(s) restored, 0 not restorable.')
        ->assertSuccessful();

    expect(Storage::disk('local')->get($letterhead->path))->toBe(file_get_contents(restoreAssetPath(RESTORE_LETTERHEAD)))
        ->and(Storage::disk('local')->get($image->path))->toBe(file_get_contents(restoreAssetPath(RESTORE_QUOTE_IMAGE)));
});

it('never overwrites a file that is still on disk', function () {
    $image = layoutImageRow($this->layout, RESTORE_QUOTE_IMAGE);
    Storage::disk('local')->put($image->path, 'current bytes');

    $this->artisan('document-layouts:restore-images')
        ->expectsOutputToContain('0 image(s) restored, 0 not restorable.')
        ->assertSuccessful();

    expect(Storage::disk('local')->get($image->path))->toBe('current bytes');
});

it('reports without writing an image with no versioned asset or a different size', function () {
    $uploaded = layoutImageRow($this->layout, 'uploaded-by-hand.png');
    $resized = layoutImageRow($this->layout, RESTORE_QUOTE_IMAGE, ['size' => 1]);

    $this->artisan('document-layouts:restore-images')
        ->expectsOutputToContain('0 image(s) restored, 2 not restorable.')
        ->assertSuccessful();

    Storage::disk('local')->assertMissing([$uploaded->path, $resized->path]);
});

it('writes nothing on a dry run', function () {
    $image = layoutImageRow($this->layout, RESTORE_QUOTE_IMAGE);

    $this->artisan('document-layouts:restore-images', ['--dry-run' => true])
        ->expectsOutputToContain('1 image(s) would be restored, 0 not restorable.')
        ->assertSuccessful();

    Storage::disk('local')->assertMissing($image->path);
});

it('ignores attachments outside the layout image collection', function () {
    $document = layoutImageRow($this->layout, RESTORE_QUOTE_IMAGE, ['collection' => 'documents']);

    $this->artisan('document-layouts:restore-images')
        ->expectsOutputToContain('0 image(s) restored, 0 not restorable.')
        ->assertSuccessful();

    Storage::disk('local')->assertMissing($document->path);
});
