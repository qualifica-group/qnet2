<?php

declare(strict_types=1);

namespace App\Http\Requests\DocumentLayouts;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

/**
 * Validates a document layout image upload (POST
 * /api/document-layouts/{documentLayout}/images, spec 0069 MT-4).
 * Authorization stays in the controller (`document-layouts.update`, gated
 * same as editing the layout's own config).
 *
 * Deliberately narrower than the shared UploadCompanySiteLogoRequest
 * allow-list: only `jpeg`/`png` are accepted, because those are the two
 * formats the spec 0070 `.docx` renderer embeds reliably — `webp`/`gif` are
 * excluded on purpose even though they are accepted elsewhere in the repo for
 * avatars/logos. `extensions:` additionally checks the real file extension
 * against the declared mimes (backend.md §8 — beats a spoofed MIME).
 */
class UploadDocumentLayoutImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'image', 'mimes:jpeg,png', 'extensions:jpg,jpeg,png', 'max:'.(int) config('attachments.max_size')],
        ];
    }

    public function imageFile(): UploadedFile
    {
        /** @var UploadedFile $file */
        $file = $this->file('file');

        return $file;
    }
}
