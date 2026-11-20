<?php

declare(strict_types=1);

namespace App\Rules;

use App\Support\Attachments\SignedEnvelope;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * Server-side type allowlist of an uploaded attachment (config/attachments.php):
 * the MIME detected from the content must be in `allowed_mime_types`, or, for
 * an opaque type accepted by extension (e.g. a signed .p7m), in
 * `opaque_detected_mime_types`. A signed envelope recognized by its content
 * passes whatever its extension (SignedEnvelope). An empty
 * `allowed_mime_types` lifts the restriction. Whether a file is present is the FormRequest's call.
 */
final class AllowedAttachmentFile implements ValidationRule
{
    /**
     * @param  Closure(string): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile) {
            return;
        }

        $allowedMimeTypes = (array) config('attachments.allowed_mime_types');

        if ($allowedMimeTypes === [] || in_array($value->getMimeType(), $this->acceptedMimeTypes($value, $allowedMimeTypes), true)) {
            return;
        }

        if (SignedEnvelope::isSignedData($value)) {
            return;
        }

        $fail('validation.mimes')->translate(['values' => implode(', ', (array) config('attachments.allowed_extensions'))]);
    }

    /**
     * @param  array<int, string>  $allowedMimeTypes
     * @return array<int, string>
     */
    private function acceptedMimeTypes(UploadedFile $file, array $allowedMimeTypes): array
    {
        $opaqueTypes = (array) config('attachments.opaque_types');

        if (! array_key_exists(strtolower($file->getClientOriginalExtension()), $opaqueTypes)) {
            return $allowedMimeTypes;
        }

        return [...$allowedMimeTypes, ...(array) config('attachments.opaque_detected_mime_types')];
    }
}
