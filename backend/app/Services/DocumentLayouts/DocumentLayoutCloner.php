<?php

declare(strict_types=1);

namespace App\Services\DocumentLayouts;

use App\DataObjects\DocumentLayouts\CloneDocumentLayoutData;
use App\Models\Attachment;
use App\Models\DocumentLayout;
use App\Models\User;
use App\Services\AttachmentService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Clones a document layout into a new one of the same module (spec 0339):
 * same description, `is_active` and `config`, never the module default (D-2).
 * The source's images are copied physically (AttachmentService::copyTo()) and
 * every `image` block of the copy is re-pointed to its own copied image — an
 * image block may only reference an image of its own layout.
 *
 * DB::transaction() does not undo the filesystem: the binaries already copied
 * are deleted before a failure is rethrown, so no orphan file survives.
 */
class DocumentLayoutCloner
{
    public function __construct(private readonly AttachmentService $attachmentService) {}

    public function handle(DocumentLayout $source, CloneDocumentLayoutData $data, User $actor): DocumentLayout
    {
        $images = $source->images()->get();

        // Step 1: a lost image file would silently drop a logo from the copy (D-4).
        $this->assertImageFilesPresent($images->all());

        /** @var array<int, Attachment> $copies */
        $copies = [];

        try {
            return DB::transaction(function () use ($source, $data, $actor, $images, &$copies): DocumentLayout {
                // Step 2: the new row, never the module default.
                $clone = DocumentLayout::create([
                    'name' => $data->name,
                    'code' => $data->code,
                    'description' => $source->description,
                    'module' => $source->module,
                    'is_active' => $source->is_active,
                    'is_default' => false,
                    'config' => $source->config,
                ]);

                // Step 3: copy every image, keyed by the source attachment id.
                foreach ($images as $image) {
                    $copies[$image->id] = $this->attachmentService->copyTo($image, $clone, DocumentLayout::IMAGE_COLLECTION, $actor);
                }

                // Step 4: re-point the copy's image blocks to the copied images.
                $clone->update(['config' => $this->remapImageBlocks($source->config, $copies)]);

                return $clone->load('images');
            });
        } catch (Throwable $exception) {
            foreach ($copies as $copy) {
                Storage::disk($copy->disk)->delete($copy->path);
            }

            throw $exception;
        }
    }

    /**
     * @param  array<int, Attachment>  $images
     */
    private function assertImageFilesPresent(array $images): void
    {
        foreach ($images as $image) {
            if (! Storage::disk($image->disk)->exists($image->path)) {
                throw ValidationException::withMessages([
                    'images' => [__('document_layouts.clone_image_missing', ['name' => $image->original_name])],
                ]);
            }
        }
    }

    /**
     * Image blocks live only at the top level of the three zones (a table
     * cell holds text blocks only, spec 0069).
     *
     * @param  array<string, mixed>  $config
     * @param  array<int, Attachment>  $copies
     * @return array<string, mixed>
     */
    private function remapImageBlocks(array $config, array $copies): array
    {
        foreach (['header', 'body', 'footer'] as $zone) {
            foreach ($config[$zone]['blocks'] ?? [] as $index => $block) {
                $sourceId = $block['attachment_id'] ?? null;

                if (($block['type'] ?? null) === 'image' && isset($copies[$sourceId])) {
                    $config[$zone]['blocks'][$index]['attachment_id'] = $copies[$sourceId]->id;
                }
            }
        }

        return $config;
    }
}
