<?php

declare(strict_types=1);

namespace App\Support\Attachments;

use Illuminate\Http\UploadedFile;

/**
 * A digitally signed file (CAdES, DER-encoded CMS SignedData) recognized by
 * its content. Such a file is often saved without its `.p7m` suffix (e.g.
 * "attestato.pdf"): it is still a signed envelope, never a plain PDF, so it is
 * accepted and named as what it is, keeping the signature intact.
 */
final class SignedEnvelope
{
    public const string EXTENSION = 'p7m';

    /** DER of OID 1.2.840.113549.1.7.2 (pkcs7-signedData). */
    private const string SIGNED_DATA_OID = "\x06\x09\x2a\x86\x48\x86\xf7\x0d\x01\x07\x02";

    /** DER SEQUENCE tag opening every CMS ContentInfo. */
    private const string SEQUENCE_TAG = "\x30";

    /** Enough to cover the outer SEQUENCE header plus the contentType OID. */
    private const int HEADER_BYTES = 32;

    public static function isSignedData(UploadedFile $file): bool
    {
        $handle = @fopen($file->getRealPath(), 'rb');

        if ($handle === false) {
            return false;
        }

        $header = (string) fread($handle, self::HEADER_BYTES);
        fclose($handle);

        return str_starts_with($header, self::SEQUENCE_TAG) && str_contains($header, self::SIGNED_DATA_OID);
    }

    /**
     * The client's file name, with `.p7m` appended when the content is a
     * signed envelope saved under another extension.
     */
    public static function originalName(UploadedFile $file): string
    {
        $name = $file->getClientOriginalName();

        if (strtolower($file->getClientOriginalExtension()) === self::EXTENSION || ! self::isSignedData($file)) {
            return $name;
        }

        return $name.'.'.self::EXTENSION;
    }
}
