<?php

declare(strict_types=1);

use App\Models\Attachment;
use App\Models\Company;
use App\Models\CompanySite;
use App\Models\Contact;
use App\Models\DocumentLayout;
use App\Models\OperationalSite;
use App\Models\Opportunity;
use App\Models\PersonalData;
use App\Models\Product;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\Models\QuoteStatus;
use App\Models\Referent;
use App\Models\ReferentType;
use App\Models\Registry;
use App\Models\Role;
use App\Models\User;
use App\Models\VatRate;
use App\Services\DocumentLayouts\Rendering\QuoteDocumentGenerator;
use Database\Factories\DocumentLayoutFactory;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

// Shared builder helpers for the spec 0070 renderer unit tests, split out of
// each test file per the DocumentLayoutConfigValidatorTestSupport.php
// precedent (engineering.md §6). Every function is `if (! function_exists())`
// guarded so multiple test files can `require_once` this one safely.

if (! function_exists('dlrDocConfig')) {
    /**
     * A structurally-valid config tree layered on
     * DocumentLayoutFactory::minimalConfig() — pass `$overrides` for
     * `page`/`header`/`body`/`footer` to replace those top-level keys.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    function dlrDocConfig(array $overrides = []): array
    {
        return array_replace(DocumentLayoutFactory::minimalConfig(), $overrides);
    }

    /**
     * @param  array<int, array<string, mixed>>  $runs
     * @return array<string, mixed>
     */
    function dlrTextBlock(array $runs, string $align = 'left'): array
    {
        return [
            'id' => 'text-'.Str::random(6),
            'type' => 'text',
            'align' => $align,
            'space_before' => 0,
            'space_after' => 0,
            'line_height' => 1.0,
            'runs' => $runs,
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    function dlrRun(array $overrides = []): array
    {
        return array_replace([
            'text' => 'Sample',
            'field' => null,
            'bold' => false,
            'italic' => false,
            'underline' => false,
            'font' => null,
            'size' => null,
            'color' => null,
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    function dlrImageBlockConfig(int $attachmentId, array $overrides = []): array
    {
        return array_replace([
            'id' => 'image-'.Str::random(6),
            'type' => 'image',
            'attachment_id' => $attachmentId,
            'width' => 100,
            'height' => 100,
            'align' => 'left',
            'wrap' => 'inline',
        ], $overrides);
    }

    /**
     * @return array<string, mixed>
     */
    function dlrDividerBlock(int $widthPct = 100, int $thickness = 6, string $color = '000000'): array
    {
        return [
            'id' => 'divider-'.Str::random(6),
            'type' => 'divider',
            'width_pct' => $widthPct,
            'thickness' => $thickness,
            'color' => $color,
            'space_before' => 0,
            'space_after' => 0,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $columns
     * @param  array<string, mixed>  $totals
     * @return array<string, mixed>
     */
    function dlrProductsTableBlock(array $columns, string $source = 'offer_lines', array $totals = [], string $emptyText = 'No products'): array
    {
        return [
            'id' => 'products-'.Str::random(6),
            'type' => 'products_table',
            'source' => $source,
            'width_pct' => 100,
            'borders' => null,
            'show_header' => true,
            'header_background' => '323E4F',
            'columns' => $columns,
            'totals' => array_replace(['show' => false, 'rows' => []], $totals),
            'empty_text' => $emptyText,
        ];
    }

    /**
     * @param  array<int, string>  $keys
     * @return array<string, mixed>
     */
    function dlrProductColumn(array $keys, string $label, int $widthPct, string $separator = '', string $align = 'left'): array
    {
        return [
            'lines' => [['keys' => $keys, 'separator' => $separator, 'bold' => false, 'italic' => false, 'size' => null]],
            'label' => $label,
            'width_pct' => $widthPct,
            'align' => $align,
        ];
    }

    /**
     * A DocumentLayout persisted with $config, module `quotes`.
     */
    function dlrLayout(array $config): DocumentLayout
    {
        return DocumentLayout::factory()->create(['config' => $config]);
    }

    /**
     * Render $quote against a layout built from $config, for $actor
     * (defaulting to a plain new User) — the raw `.docx` binary.
     */
    function dlrRender(array $config, Quote $quote, ?User $actor = null): string
    {
        return app(QuoteDocumentGenerator::class)->generate($quote, dlrLayout($config), $actor ?? User::factory()->create());
    }

    /**
     * Open a `.docx` binary as a ZipArchive by writing it to a throwaway
     * temp file (ZipArchive has no in-memory-string API) — the file is
     * deleted automatically at script shutdown.
     */
    function dlrOpenZip(string $binary): ZipArchive
    {
        $path = sys_get_temp_dir().'/dl-render-test-'.Str::uuid()->toString().'.docx';
        file_put_contents($path, $binary);
        register_shutdown_function(static fn () => @unlink($path));

        $zip = new ZipArchive;
        expect($zip->open($path))->toBe(true);

        return $zip;
    }

    /**
     * A well-formed-XML assertion via DOMDocument — throws/records libxml
     * errors instead of silently swallowing them.
     */
    function dlrAssertWellFormedXml(string $xml): void
    {
        libxml_use_internal_errors(true);
        $document = new DOMDocument;
        $ok = $document->loadXML($xml);
        $errors = libxml_get_errors();
        libxml_clear_errors();

        expect($ok)->toBe(true)
            ->and($errors)->toBeEmpty();
    }

    /**
     * Every part path declared in `[Content_Types].xml` (Override PartName
     * attributes) — used to assert each one actually exists in the archive.
     *
     * @return array<int, string>
     */
    function dlrDeclaredContentTypeParts(ZipArchive $zip): array
    {
        $xml = new DOMDocument;
        $xml->loadXML((string) $zip->getFromName('[Content_Types].xml'));

        $parts = [];

        foreach ($xml->getElementsByTagName('Override') as $override) {
            $parts[] = ltrim($override->getAttribute('PartName'), '/');
        }

        return $parts;
    }

    /**
     * Every `<w:t>` text run's content in word/document.xml, concatenated —
     * enough to assert a resolved variable's value appears (and no raw
     * `{...}` token remains) without parsing the full paragraph structure.
     */
    function dlrDocumentText(ZipArchive $zip, string $part = 'word/document.xml'): string
    {
        $document = new DOMDocument;
        $document->loadXML((string) $zip->getFromName($part));

        $text = '';

        foreach ($document->getElementsByTagNameNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 't') as $node) {
            $text .= $node->textContent;
        }

        return $text;
    }

    /**
     * The WordprocessingML namespace URI, shared by every DOM helper below.
     */
    function dlrWmlNs(): string
    {
        return 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
    }

    /**
     * Structured dump of the Nth `w:tbl` in word/document.xml: a list of rows,
     * each `['is_header' => bool, 'cells' => [ ['grid_span' => int,
     * 'paragraphs' => array<int, string>], ... ]]` — one entry per `w:p`
     * inside the cell (AC-242's "due paragrafi nella stessa cella" is exactly
     * `count($cell['paragraphs']) === 2`).
     *
     * @return array<int, array{is_header: bool, cells: array<int, array{grid_span: int, bold: bool, paragraphs: array<int, string>}>}>
     */
    function dlrExtractTable(ZipArchive $zip, int $tableIndex = 0): array
    {
        $ns = dlrWmlNs();
        $document = new DOMDocument;
        $document->loadXML((string) $zip->getFromName('word/document.xml'));

        $table = $document->getElementsByTagNameNS($ns, 'tbl')->item($tableIndex);
        $rows = [];

        foreach ($table->getElementsByTagNameNS($ns, 'tr') as $tr) {
            $isHeader = $tr->getElementsByTagNameNS($ns, 'tblHeader')->length > 0;
            $cells = [];

            foreach ($tr->childNodes as $child) {
                if (! ($child instanceof DOMElement) || $child->localName !== 'tc') {
                    continue;
                }

                $gridSpanNode = $child->getElementsByTagNameNS($ns, 'gridSpan')->item(0);
                $gridSpan = $gridSpanNode !== null ? (int) $gridSpanNode->getAttributeNS($ns, 'val') : 1;

                $paragraphs = [];

                foreach ($child->childNodes as $cellChild) {
                    if (! ($cellChild instanceof DOMElement) || $cellChild->localName !== 'p') {
                        continue;
                    }

                    $text = '';

                    foreach ($cellChild->getElementsByTagNameNS($ns, 't') as $t) {
                        $text .= $t->textContent;
                    }

                    $paragraphs[] = $text;
                }

                $bold = $child->getElementsByTagNameNS($ns, 'b')->length > 0;

                $cells[] = ['grid_span' => $gridSpan, 'bold' => $bold, 'paragraphs' => $paragraphs];
            }

            $rows[] = ['is_header' => $isHeader, 'cells' => $cells];
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<int, string>
     */
    function dlrRowText(array $row): array
    {
        return array_map(
            static fn (array $cell): string => implode('', $cell['paragraphs']),
            $row['cells'],
        );
    }

    /**
     * A fully-populated Quote: opportunity/registry/personal-data/contacts/
     * addresses, referent/commercial/reporter/supervisor, company/company
     * site/operational site — everything VariableResolver's categories can
     * read, wired with recognizable, assertable values.
     *
     * @param  array<string, mixed>  $quoteOverrides
     */
    function dlrFullQuote(array $quoteOverrides = []): Quote
    {
        $supervisor = User::factory()->create(['name' => 'Luca Supervisore', 'email' => 'luca.supervisore@example.com']);

        $commercial = Referent::factory()->create(['name' => 'Marco Commerciale']);
        $commercialCard = PersonalData::factory()->for($commercial, 'personable')->create();
        Contact::factory()->email()->for($commercialCard, 'contactable')->create(['value' => 'marco.commerciale@example.com', 'is_primary' => true]);

        $reporter = Referent::factory()->create(['name' => 'Anna Segnalatrice']);
        $reporterCard = PersonalData::factory()->for($reporter, 'personable')->create();
        Contact::factory()->email()->for($reporterCard, 'contactable')->create(['value' => 'anna.segnalatrice@example.com', 'is_primary' => true]);

        $referentType = ReferentType::factory()->create(['name' => 'Responsabile Acquisti']);
        $referent = Referent::factory()->create(['name' => 'Giulia Referente', 'referent_type_id' => $referentType->id]);

        $registry = Registry::factory()->create(['name' => 'Cliente Rossi S.r.l.']);
        $registryCard = PersonalData::factory()->company()->for($registry, 'personable')->create([
            'company_name' => 'Cliente Rossi S.r.l.',
            'tax_code' => 'RSSMRA80A01H501U',
            'vat_number' => 'IT01234567890',
            'sdi_code' => 'ABCDE12',
        ]);
        Contact::factory()->email()->for($registryCard, 'contactable')->create(['value' => 'info@clienterossi.example.com', 'is_primary' => true]);

        $opportunity = Opportunity::factory()->create([
            'registry_id' => $registry->id,
            'referent_id' => $referent->id,
            'name' => 'Opportunita Test',
        ]);

        $company = Company::factory()->create(['denomination' => 'Azienda Emittente S.r.l.']);

        $companySite = CompanySite::factory()->create(['name' => 'Sede di Milano', 'company_id' => $company->id]);
        $companySite->banks()->create(['name' => 'Banca Intesa', 'iban' => 'IT60X0542811101000000123456', 'is_primary' => true]);

        $operationalSite = OperationalSite::factory()->create();

        // NOT 'Bozza': the quote_statuses migration already seeds a system
        // row of that exact name, and `name` is unique — a fixed literal
        // here would collide with it.
        $quoteStatus = QuoteStatus::factory()->create();

        return Quote::factory()->create(array_replace([
            'opportunity_id' => $opportunity->id,
            'quote_status_id' => $quoteStatus->id,
            'commercial_id' => $commercial->id,
            'reporter_id' => $reporter->id,
            'supervisor_id' => $supervisor->id,
            'company_id' => $company->id,
            'company_site_id' => $companySite->id,
            'operational_site_id' => $operationalSite->id,
            'title' => 'Preventivo Test',
            'revenue_net' => 1000.00,
            'revenue_vat' => 220.00,
            'cost_net' => 500.00,
            'cost_vat' => 110.00,
            'margin_net' => 500.00,
        ], $quoteOverrides));
    }

    /**
     * An actor whose `registries` field permissions are restricted by
     * $matrixRow (e.g. hiding `personal_data.tax_code`) — mirrors
     * DocumentLayoutVariableCatalogTest's own helper of the same name, since
     * VariableResolver's D-6 masking reuses the exact same mechanism.
     *
     * @param  array<string, mixed>|null  $matrixRow
     */
    function dlrActorWithFieldPermissionRole(?array $matrixRow = null): User
    {
        $role = Role::create(['name' => 'dl-rendering-role-'.Str::random(8)]);

        if ($matrixRow !== null) {
            $role->fieldPermissions()->create($matrixRow);
        }

        $actor = User::factory()->create();
        $actor->assignRole($role);

        return $actor;
    }

    /**
     * A minimal, valid 1x1 PNG binary — enough for ImageBlockRenderer to
     * find a real file on disk without pulling in a GD dependency.
     */
    function dlrTinyPngBinary(): string
    {
        return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');
    }

    /**
     * A DocumentLayout with a real Attachment (`layout_image` collection, a
     * real 1x1 PNG on the `local` disk — the caller must `Storage::fake('local')`
     * first) it owns, so an `image` block referencing it passes
     * DocumentLayoutConfigValidator's ownership check (called by
     * QuoteDocumentGenerator before rendering). $configBuilder receives the
     * attachment id and returns the full config tree to persist.
     *
     * @param  callable(int): array<string, mixed>  $configBuilder
     */
    function dlrLayoutWithImage(callable $configBuilder): DocumentLayout
    {
        $layout = DocumentLayout::factory()->create();

        $path = 'document-layouts/'.Str::uuid()->toString().'.png';
        Storage::disk('local')->put($path, dlrTinyPngBinary());

        $attachment = Attachment::factory()->for($layout, 'attachable')->create([
            'collection' => DocumentLayout::IMAGE_COLLECTION,
            'disk' => 'local',
            'path' => $path,
            'mime_type' => 'image/png',
            'extension' => 'png',
        ]);

        $layout->config = $configBuilder($attachment->id);
        $layout->save();

        return $layout->fresh();
    }

    /**
     * @param  array<string, mixed>  $lineOverrides
     */
    function dlrAddLine(Quote $quote, int $sortOrder, string $type = 'offer', array $lineOverrides = []): QuoteLine
    {
        $vatRate = VatRate::factory()->create(['name' => 'IVA 22%', 'rate' => 22.00]);
        $product = Product::factory()->create(['name' => 'Prodotto '.$sortOrder, 'description' => 'Descrizione '.$sortOrder]);

        $factory = QuoteLine::factory();

        if ($type === 'cost') {
            $factory = $factory->cost();
        }

        return $factory->create(array_replace([
            'quote_id' => $quote->id,
            'product_id' => $product->id,
            'vat_rate_id' => $vatRate->id,
            'sort_order' => $sortOrder,
        ], $lineOverrides));
    }
}
