<?php

declare(strict_types=1);

namespace App\Services\Attachments;

use App\Models\Attachment;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use ZipArchive;

/**
 * Packs the documents of one owner into a single ZIP ("Scarica tutti", spec
 * 0393), as the legacy QNet did for a work order's documents.
 *
 * Every file is first copied into a private work directory (local disk or
 * legacy proxy, streamed, never held in memory), then zipped. A file that is
 * no longer available does not abort the download: it is left out and named
 * in a text file inside the archive, so the user knows what is missing.
 */
final class AttachmentArchiveBuilder
{
    private const string FALLBACK_ENTRY_NAME = 'file';

    public function __construct(private readonly LegacyAttachmentFetcher $legacyFetcher) {}

    /**
     * @param  Collection<int, Attachment>  $attachments
     * @return string absolute path of the ZIP; the caller deletes it after sending
     */
    public function build(Collection $attachments): string
    {
        // Step 1: private work directory for the local copies
        $workDir = $this->makeWorkDir();

        try {
            // Step 2: copy every file locally, remembering the unavailable ones
            $entries = [];
            $missing = [];

            foreach ($attachments as $attachment) {
                $localPath = $workDir.DIRECTORY_SEPARATOR.$attachment->id;

                if ($this->copyLocally($attachment, $localPath)) {
                    $entries[$this->uniqueEntryName($attachment->original_name, $entries)] = $localPath;
                } else {
                    $missing[] = $attachment->original_name;
                }
            }

            // Step 3: write the archive (ZipArchive reads the files on close)
            return $this->writeZip($entries, $missing);
        } finally {
            File::deleteDirectory($workDir);
        }
    }

    private function makeWorkDir(): string
    {
        $workDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'attachment-archive-'.Str::uuid()->toString();
        File::ensureDirectoryExists($workDir);

        return $workDir;
    }

    /**
     * True when the file was copied; false when it no longer exists or the
     * legacy QNet cannot serve it right now.
     */
    private function copyLocally(Attachment $attachment, string $target): bool
    {
        if ($attachment->isLegacyLink()) {
            try {
                $this->legacyFetcher->saveTo($attachment, $target);

                return true;
            } catch (HttpExceptionInterface) {
                return false;
            }
        }

        $disk = Storage::disk($attachment->disk);
        $source = $disk->exists($attachment->path) ? $disk->readStream($attachment->path) : null;

        if (! is_resource($source)) {
            return false;
        }

        $handle = fopen($target, 'wb');

        try {
            stream_copy_to_stream($source, $handle);
        } finally {
            fclose($handle);
            fclose($source);
        }

        return true;
    }

    /**
     * The original name, flattened to a single path segment and made unique
     * inside the archive: "Preventivo.pdf", "Preventivo (2).pdf", ...
     *
     * @param  array<string, string>  $entries
     */
    private function uniqueEntryName(string $originalName, array $entries): string
    {
        $name = trim(str_replace(['/', '\\'], '_', $originalName));
        $name = $name === '' ? self::FALLBACK_ENTRY_NAME : $name;
        $taken = array_map('mb_strtolower', array_keys($entries));

        $extension = pathinfo($name, PATHINFO_EXTENSION);
        $base = $extension === '' ? $name : mb_substr($name, 0, -mb_strlen($extension) - 1);
        $candidate = $name;

        for ($copy = 2; in_array(mb_strtolower($candidate), $taken, true); $copy++) {
            $candidate = $extension === '' ? "{$base} ({$copy})" : "{$base} ({$copy}).{$extension}";
        }

        return $candidate;
    }

    /**
     * @param  array<string, string>  $entries  entry name => local path
     * @param  array<int, string>  $missing
     */
    private function writeZip(array $entries, array $missing): string
    {
        $zipPath = tempnam(sys_get_temp_dir(), 'attachment-archive-');
        $zip = new ZipArchive;

        if ($zipPath === false || $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to create the documents archive.');
        }

        foreach ($entries as $entryName => $localPath) {
            $zip->addFile($localPath, $entryName);
        }

        if ($missing !== []) {
            $zip->addFromString(
                __('Documents not included.txt'),
                __('These documents could not be included because their file is no longer available:')
                .PHP_EOL.PHP_EOL.implode(PHP_EOL, $missing).PHP_EOL,
            );
        }

        $zip->close();

        return $zipPath;
    }
}
