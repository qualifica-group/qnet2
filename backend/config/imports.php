<?php

use App\Imports\LeadsImportDefinition;
use App\Imports\RequestManagementImportDefinition;

return [

    /*
    |--------------------------------------------------------------------------
    | Generic Domain-driven Import Registry
    |--------------------------------------------------------------------------
    |
    | Maps each `{domain}` to its ImportDefinition class, mirroring
    | config/tables.php. One set of endpoints (GET .../template, POST ...,
    | GET .../{importRun}, POST .../{importRun}/confirm, GET .../{importRun}/
    | errors) serves every domain; ImportRegistry resolves the class below
    | through the container (so its dependencies — a domain Service,
    | GeoResolver — are injected). An unregistered {domain} resolves to
    | nothing and yields a 404.
    |
    | Adding a domain (e.g. products):
    |   1. class ProductsImportDefinition extends AbstractImportDefinition
    |   2. add 'products' => ProductsImportDefinition::class here
    | No new controller, service, request, resource or route is needed.
    |
    */

    'definitions' => [
        'leads' => LeadsImportDefinition::class,
        'request-management' => RequestManagementImportDefinition::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Magic-value constants (spec 0012 constraints)
    |--------------------------------------------------------------------------
    |
    | Centralized here so no import limit is hard-coded/scattered across
    | CsvReader, the Jobs or the Controller.
    */

    // Maximum accepted upload size, in kilobytes (Laravel's `max:` file rule unit).
    'max_file_kb' => (int) env('IMPORT_MAX_FILE_KB', 5120),

    // Maximum number of data rows CsvReader accepts (header excluded).
    'max_rows' => (int) env('IMPORT_MAX_ROWS', 5000),

    // Rows shown in the preview's valid_sample.
    'preview_valid' => (int) env('IMPORT_PREVIEW_VALID', 10),

    // Rows shown in the preview's invalid_sample (the FULL rejected set still
    // goes to the downloadable errors report regardless of this cap).
    'preview_invalid' => (int) env('IMPORT_PREVIEW_INVALID', 50),

    // Rows grouped per commit batch during ProcessStagedImportJob (also the
    // lazyById() chunk size reading persistable staged rows — spec 0136,
    // D-8): per-row transactions are the isolation unit, this only bounds
    // how many staged rows are held in memory at once.
    'batch_size' => (int) env('IMPORT_BATCH_SIZE', 200),

    // StageImportJob/ProcessStagedImportJob `$timeout` (spec 0136, D-7):
    // this — not `queue:work --timeout` — is what Laravel's worker actually
    // enforces per job (Worker::timeoutForJob favors it over the CLI flag).
    // Must stay BELOW queue.connections.database.retry_after, otherwise a
    // second worker picks the same job back up while the first is still
    // legitimately running it.
    'job_timeout' => (int) env('IMPORT_JOB_TIMEOUT', 1800),

    // Dedicated queue of every import job (Analyze/Stage/ProcessStaged and the
    // legacy Validate/ProcessImport): an import holds its worker for up to
    // job_timeout, so on the shared `default` queue it would stall emails,
    // exports and notifications behind it. Served by its own worker
    // (`queue:work --queue=imports`), never by the default one.
    'queue' => env('IMPORT_QUEUE', 'imports'),

    // Value StagedRowBuilder defaults a still-blank `requiredForCreation()`
    // field to, after recognizers ran, instead of rejecting the row outright
    // (spec 0033 delta D-2026-07-15-placeholder-review-fields): the row is
    // flagged warning and the placeholder stays editable in the review grid.
    'placeholder' => env('IMPORT_PLACEHOLDER', 'SCONOSCIUTO'),

    // Home country name used to disambiguate a geo level that would otherwise
    // be ambiguous only because a bare homonym spans several countries (a city
    // "Rome"/"Roma" with no country/region in the row matches Italy AND the
    // US). GeoResolver::resolveFuzzy() collapses such a tie to the single
    // candidate in this country. Matched case-insensitively against the
    // reference dataset (via ItalianGeoLocalizer). Empty disables the tiebreak.
    'default_country' => env('IMPORT_DEFAULT_COUNTRY', 'Italy'),

    /*
    |--------------------------------------------------------------------------
    | Column mapping aliases (spec 0033 — App\Imports\Support\ColumnMapper)
    |--------------------------------------------------------------------------
    |
    | Extra human header variants (IT/EN), on top of a field's own id/label,
    | that ColumnMapper's auto-mapping resolves to the given mappable field
    | id. Matched after normalization (lower/trim/accent-strip/underscore-
    | and-dash-to-space), so "E-mail" already matches "email" without an
    | alias — these entries cover the variants that normalization alone
    | does not bridge. Centralized here, never scattered across
    | ImportDefinition classes.
    */
    'column_aliases' => [
        // "Nome" alone carries the whole name; next to a "Cognome" column it
        // is re-targeted to `first_name` by `contextual_aliases` below.
        'full_name' => ['nome completo', 'full name', 'nominativo', 'nome', 'nome e cognome'],
        'first_name' => ['first name'],
        'last_name' => ['cognome', 'last name'],
        'email' => ['e mail', 'indirizzo email', 'indirizzo e mail'],
        // Spec 0139: one telephone field — a "cellulare" column maps here too.
        'phone' => ['telefono', 'tel', 'cellulare', 'cell'],
        'company_name' => ['ragione sociale', 'azienda', 'company', 'nome azienda'],
        'tax_code' => ['codice fiscale', 'cf'],
        'vat_number' => ['partita iva', 'piva', 'vat'],
        'street' => ['indirizzo', 'via'],
        'postal_code' => ['cap', 'zip', 'zip code'],
        'country' => ['nazione', 'stato'],
        'region' => ['regione'],
        'province' => ['provincia'],
        'city' => ['citta', 'comune', 'comune di residenza'],
        'notes' => ['note'],
        // Spec 0108: the column carries the campaign CODE (CMP-0001), never
        // its name — "campagna" is an alias of the code column on purpose,
        // a file holding names there produces motivated per-row errors.
        'campaign_code' => ['codice campagna', 'campagna', 'campaign code', 'cod campagna'],
        // Spec 0348 (request-management): ids that exist for this domain only.
        // `stato` is also an alias of `country` above: the domain lists
        // `workflow_status` before `country`, and ColumnMapper keeps the FIRST
        // field claiming a header.
        'operator_name' => ['operatore'],
        'reporter_name' => ['segnalatore'],
        'workflow_status' => ['stato'],
        'source_name' => ['fonte'],
        'next_callback_date' => ['data richiamo', 'data di richiamo'],
        'product_name' => ['prodotto'],
        'product_category_name' => ['categoria prodotto'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Contextual column aliases (App\Imports\Support\ColumnMapper)
    |--------------------------------------------------------------------------
    |
    | Header variants whose meaning depends on the rest of the file: field id
    | => the sibling field that must ALSO be matched, and the aliases that
    | then move to this field instead of their `column_aliases` target.
    | "Nome" is the full name on its own, the given name beside "Cognome".
    */
    'contextual_aliases' => [
        'first_name' => ['when_matched' => 'last_name', 'aliases' => ['nome']],
    ],

    /*
    |--------------------------------------------------------------------------
    | Request-management import (spec 0348)
    |--------------------------------------------------------------------------
    |
    | Keys of `status_aliases` / `source_aliases` are compared after
    | App\Support\Import\ImportValueMappings::normalize() (case and spacing
    | insensitive); the value is the NAME of the existing status / source the
    | file value stands for.
    */
    'request_management' => [
        // Time of day the planned callback gets (the file carries a date only), in the app timezone.
        'callback_time' => env('IMPORT_REQUEST_CALLBACK_TIME', '09:00'),

        'status_aliases' => [
            'NR' => 'Non risponde',
            'Trattativa' => 'In trattativa',
            'Attesa_App APL' => 'Attesa _ App. APL',
        ],

        'source_aliases' => [
            'social' => 'Social',
            'sito' => 'Sito',
            'spoki' => 'Spoki',
            'centralino' => 'Centralino',
            'diretto + passaparola' => 'Diretto / Passaparola',
            'passaparola' => 'Passaparola',
        ],

        // Heading used in the general notes for a value that could not be stored.
        'note_labels' => [
            'email' => 'Email',
            'phone' => 'Telefono',
            'operator_name' => 'Operatore',
            'reporter_name' => 'Segnalatore',
            'next_callback_date' => 'Data richiamo',
            'product_name' => 'Prodotto',
        ],

        // Product matching: tokens that carry no meaning, and the smallest
        // prefix of a product word a file abbreviation may be ("Agg" for "Aggiornamento").
        'product_stopwords' => ['per', 'il', 'lo', 'la', 'le', 'di', 'del', 'della', 'da', 'e', 'ed', 'con', 'in', 'a', 'i', 'un', 'una', 'su'],
        // Words of a "Prodotto" cell that name the course family or the site, not the product.
        'product_noise_words' => ['corso', 'milano'],
        'product_min_prefix' => 3,
    ],

];
