<?php

declare(strict_types=1);

namespace App\Services\DocumentLayouts\Rendering;

use App\Services\DocumentLayouts\Rendering\Exceptions\DocumentConversionException;
use Illuminate\Support\Str;
use Symfony\Component\Process\Exception\ExceptionInterface as ProcessException;
use Symfony\Component\Process\Process;

/**
 * Converts a `.docx` binary to a `.pdf` binary through a headless LibreOffice
 * process. The rendering pipeline keeps producing OOXML (DocxRenderer,
 * unchanged); only the delivered format changes.
 *
 * Binaries in, binaries out, nothing persisted: the throwaway workspace is
 * removed before returning, so D-2 ("nessuna persistenza") holds for the PDF
 * exactly as it did for the DOCX.
 *
 * Not `final`: the two controllers that stream a document resolve this class
 * from the container, and the feature tests replace it with a double to assert
 * the DOCX actually handed to the converter without paying for a real
 * LibreOffice run in every test.
 */
class DocxToPdfConverter
{
    private const string SOURCE_NAME = 'document.docx';

    private const string OUTPUT_NAME = 'document.pdf';

    /**
     * @throws DocumentConversionException when LibreOffice is missing, fails,
     *                                     times out, or writes no output.
     */
    public function convert(string $docx): string
    {
        $workspace = $this->makeWorkspace();

        try {
            // Step 1: hand LibreOffice a real file — it converts paths, not streams.
            $source = $workspace.'/'.self::SOURCE_NAME;
            file_put_contents($source, $docx);

            // Step 2: convert in place, inside this run's own workspace.
            $this->runLibreOffice($source, $workspace);

            // Step 3: read the produced PDF back as bytes.
            return $this->readOutput($workspace);
        } finally {
            $this->deleteWorkspace($workspace);
        }
    }

    private function makeWorkspace(): string
    {
        $workspace = sys_get_temp_dir().'/docx-pdf-'.Str::uuid()->toString();

        if (! mkdir($workspace, 0700) && ! is_dir($workspace)) {
            throw new DocumentConversionException("Unable to create the conversion workspace at {$workspace}.");
        }

        return $workspace;
    }

    private function runLibreOffice(string $source, string $workspace): void
    {
        $process = new Process([
            (string) config('documents.pdf.binary'),
            '--headless',
            '--norestore',
            '--invisible',
            // A per-run user profile is not optional: LibreOffice serialises on
            // a shared profile, so two simultaneous conversions would make the
            // second exit 0 having written nothing, and under php-fpm the
            // default profile location ($HOME) is often not writable at all.
            '-env:UserInstallation=file://'.$workspace.'/profile',
            '--convert-to',
            'pdf:writer_pdf_Export',
            '--outdir',
            $workspace,
            $source,
        ]);

        $process->setTimeout((float) config('documents.pdf.timeout'));

        try {
            $process->run();
        } catch (ProcessException $exception) {
            throw new DocumentConversionException(
                'LibreOffice could not be executed: '.$exception->getMessage(),
                previous: $exception,
            );
        }

        if (! $process->isSuccessful()) {
            throw new DocumentConversionException(sprintf(
                'LibreOffice exited with code %s: %s',
                $process->getExitCode() ?? 'unknown',
                trim($process->getErrorOutput().' '.$process->getOutput()),
            ));
        }
    }

    private function readOutput(string $workspace): string
    {
        $output = $workspace.'/'.self::OUTPUT_NAME;

        // LibreOffice reports success even when it silently skips a document it
        // cannot load, so the produced file is the only trustworthy evidence.
        if (! is_file($output)) {
            throw new DocumentConversionException('LibreOffice produced no PDF output.');
        }

        $pdf = (string) file_get_contents($output);

        if ($pdf === '') {
            throw new DocumentConversionException('LibreOffice produced an empty PDF.');
        }

        return $pdf;
    }

    private function deleteWorkspace(string $workspace): void
    {
        if (! is_dir($workspace)) {
            return;
        }

        foreach ($this->workspaceEntries($workspace) as $path) {
            is_dir($path) ? @rmdir($path) : @unlink($path);
        }

        @rmdir($workspace);
    }

    /**
     * Deepest-first listing, so directories are removed after their content
     * (the LibreOffice profile is a tree, not a flat directory).
     *
     * @return array<int, string>
     */
    private function workspaceEntries(string $workspace): array
    {
        $entries = [];

        foreach ((array) scandir($workspace) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $workspace.'/'.$entry;
            $entries = [...$entries, ...(is_dir($path) ? $this->workspaceEntries($path) : []), $path];
        }

        return $entries;
    }
}
