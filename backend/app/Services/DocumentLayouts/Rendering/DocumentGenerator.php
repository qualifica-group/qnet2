<?php

declare(strict_types=1);

namespace App\Services\DocumentLayouts\Rendering;

use App\Models\DocumentLayout;
use App\Models\User;
use App\Services\DocumentLayouts\DocumentLayoutConfigValidator;
use App\Services\DocumentLayouts\Rendering\Exceptions\InvalidDocumentLayoutConfigException;
use Illuminate\Support\Str;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Settings;

/**
 * The spec 0070 entry point (generalized by spec 0195 D-5): `.docx` generation for any
 * DocumentRenderSubject (Quote, Invoice) against an
 * ALREADY-RESOLVED DocumentLayout. Resolving WHICH layout to use (the
 * subject's own `layout_id` vs the module's default) is explicitly out of
 * scope here — the caller (a controller, added in a later step once
 * `quotes.layout_id` exists) does that; this class only renders.
 *
 * `generate()` returns the raw `.docx` BINARY content, not a file path: D-2
 * ("nessuna persistenza") means there is nothing to keep track of and
 * nothing to clean up — the throwaway file this method writes (to the
 * SYSTEM temp dir, never the Laravel `local` disk) is deleted before
 * returning, satisfying AC-264 by construction.
 */
final class DocumentGenerator
{
    public function __construct(
        private readonly DocumentLayoutConfigValidator $configValidator,
        private readonly DocxRenderer $docxRenderer,
    ) {}

    /**
     * @throws InvalidDocumentLayoutConfigException when $layout's persisted
     *                                              `config` no longer passes DocumentLayoutConfigValidator
     *                                              (AC-262: corrupted by a manual DB edit).
     */
    public function generate(DocumentRenderSubject $subject, DocumentLayout $layout, User $actor): string
    {
        // Step 1: a corrupted config must 422 (via the caller's exception
        // mapping), never produce a broken file.
        $errors = $this->configValidator->validate($layout->config, $layout, $layout->module, $actor);

        if ($errors !== []) {
            throw new InvalidDocumentLayoutConfigException($errors);
        }

        // Step 2: eager-load everything the subject may touch.
        $subject->prepare();

        // Step 3: build the document from the validated config.
        $phpWord = $this->docxRenderer->render($layout->config, $subject, $actor);

        // Step 4: serialize to a throwaway file and return its bytes.
        return $this->toBinary($phpWord);
    }

    private function toBinary(PhpWord $phpWord): string
    {
        // PhpWord ships with output escaping OFF (Settings::$outputEscapingEnabled
        // = false) and the writer then emits every run through writeRaw(): a
        // single `&`, `<` or `>` — in the layout's own text or in resolved data
        // such as a client named "Rossi & Figli" — produces a document.xml that
        // is not well-formed, and Word refuses the whole file ("Errore durante
        // l'apertura del file"). No renderer here ever passes markup, so every
        // run is literal text that must be escaped.
        Settings::setOutputEscapingEnabled(true);

        $path = sys_get_temp_dir().'/'.Str::uuid()->toString().'.docx';

        IOFactory::createWriter($phpWord, 'Word2007')->save($path);

        try {
            return (string) file_get_contents($path);
        } finally {
            @unlink($path);
        }
    }
}
