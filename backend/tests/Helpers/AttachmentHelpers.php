<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;

if (! function_exists('realUploadedFile')) {
    /**
     * A real (non-fake) upload, so validation sniffs the MIME from the bytes
     * the way it does in production: a fake file reports its declared MIME.
     */
    function realUploadedFile(string $name, string $contents, string $clientMimeType): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'upload');
        file_put_contents($path, $contents);

        return new UploadedFile($path, $name, $clientMimeType, null, true);
    }
}

if (! function_exists('signedP7mBytes')) {
    /**
     * Leading bytes of a DER-encoded CMS SignedData (a CAdES .p7m): finfo
     * cannot recognize them and reports application/octet-stream.
     */
    function signedP7mBytes(): string
    {
        return "\x30\x80\x06\x09\x2a\x86\x48\x86\xf7\x0d\x01\x07\x02\xa0\x80\x30\x80\x02\x01\x01".str_repeat("\x00\x11\x22", 64);
    }
}
