<?php

declare(strict_types=1);

namespace App\Services\DocumentLayouts\Rendering;

use App\Models\DocumentLayout;
use App\Models\Quote;
use App\Models\User;
use App\Services\DocumentLayouts\DocumentLayoutConfigValidator;
use App\Services\DocumentLayouts\Rendering\Exceptions\InvalidDocumentLayoutConfigException;
use Illuminate\Support\Str;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;

/**
 * The spec 0070 entry point: `.docx` generation for a Quote against an
 * ALREADY-RESOLVED DocumentLayout. Resolving WHICH layout to use (the
 * Quote's own `layout_id` vs the module's default) is explicitly out of
 * scope here — the caller (a controller, added in a later step once
 * `quotes.layout_id` exists) does that; this class only renders.
 *
 * `generate()` returns the raw `.docx` BINARY content, not a file path: D-2
 * ("nessuna persistenza") means there is nothing to keep track of and
 * nothing to clean up — the throwaway file this method writes (to the
 * SYSTEM temp dir, never the Laravel `local` disk) is deleted before
 * returning, satisfying AC-264 by construction.
 */
final class QuoteDocumentGenerator
{
    /**
     * Every relation VariableResolver/ProductsTableRenderer may read.
     * Model::preventLazyLoading() is active outside production (backend.md
     * §3), so a relation missing from this list fails loudly rather than
     * silently degrading — eager-loaded explicitly rather than reused from
     * QuoteService::DETAIL_RELATIONS, which does not cover the personal-data/
     * address/contact depth the rendering-only VariableResolver needs.
     *
     * @var array<int, string>
     */
    private const array DETAIL_RELATIONS = [
        'opportunity.registry.personalData.contacts',
        'opportunity.registry.personalData.addresses.city',
        'opportunity.registry.personalData.addresses.province',
        'opportunity.registry.personalData.addresses.state',
        'opportunity.registry.personalData.addresses.country',
        'opportunity.referent.personalData.contacts',
        'opportunity.referent.referentType',
        'opportunity.opportunityStatus',
        'commercial.personalData.contacts',
        'reporter.personalData.contacts',
        'supervisor',
        'company.addresses.city',
        'company.addresses.province',
        'company.addresses.state',
        'company.addresses.country',
        'companySite.personalData.addresses.city',
        'companySite.personalData.addresses.province',
        'companySite.personalData.addresses.state',
        'companySite.personalData.addresses.country',
        'companySite.banks',
        'operationalSite.addresses.city',
        'quoteStatus',
        'offerLines.product',
        'offerLines.vatRate',
        'costLines.product',
        'costLines.vatRate',
    ];

    public function __construct(
        private readonly DocumentLayoutConfigValidator $configValidator,
        private readonly DocxRenderer $docxRenderer,
    ) {}

    /**
     * @throws InvalidDocumentLayoutConfigException when $layout's persisted
     *                                              `config` no longer passes DocumentLayoutConfigValidator
     *                                              (AC-262: corrupted by a manual DB edit).
     */
    public function generate(Quote $quote, DocumentLayout $layout, User $actor): string
    {
        // Step 1: a corrupted config must 422 (via the caller's exception
        // mapping), never produce a broken file.
        $errors = $this->configValidator->validate($layout->config, $layout, $layout->module, $actor);

        if ($errors !== []) {
            throw new InvalidDocumentLayoutConfigException($errors);
        }

        // Step 2: eager-load everything the resolver may touch.
        $quote->loadMissing(self::DETAIL_RELATIONS);

        // Step 3: build the document from the validated config.
        $phpWord = $this->docxRenderer->render($layout->config, $quote, $actor);

        // Step 4: serialize to a throwaway file and return its bytes.
        return $this->toBinary($phpWord);
    }

    private function toBinary(PhpWord $phpWord): string
    {
        $path = sys_get_temp_dir().'/'.Str::uuid()->toString().'.docx';

        IOFactory::createWriter($phpWord, 'Word2007')->save($path);

        try {
            return (string) file_get_contents($path);
        } finally {
            @unlink($path);
        }
    }
}
