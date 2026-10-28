<?php

declare(strict_types=1);

namespace Database\Seeders\QualificaCatalog;

/**
 * The client's other quote layouts: every template of the legacy QNet
 * "Layout preventivi" page (`layout_quotes`), plus the Academy one they
 * supplied alongside. The standard layout every quote falls back to is NOT
 * here: it is StandardQuoteLayout, owned by QualificaDocumentLayoutSeeder.
 *
 * Code => [name, legacy file]. The name is the legacy title, verbatim (a UI
 * value, hence Italian); the file is the `.docx` the layout was converted
 * from, kept for traceability in the row's description. Each code has its
 * converted `config` in `assets/quote-layouts/{code}.json`, image blocks
 * naming their file in `assets/quote-layouts/images/` instead of an
 * attachment id (QualificaQuoteDocumentLayoutSeeder swaps it in).
 *
 * How the `.docx` were converted (the legacy `${...}` placeholders of PhpWord's
 * TemplateProcessor mapped onto this module's catalogue):
 *   - QUOTATION_NR / DATE -> {quote.code} / {quote.created_at};
 *   - CLIENT_INFO -> three paragraphs {client.name} / {client.address} /
 *     "P.Iva: {client.vat_number}"; CLIENT_INFO_LINEAR -> the same on one line;
 *   - the SID row table -> a `products_table` (code-name, product description,
 *     line note, quantity, unit price, VAT, net line total, the legacy
 *     SLINE_TOTAL being quantity x price), the totals and VAT tables folded
 *     into its `totals` rows, STOTAL_INCL_VAT -> {totals.revenue_gross};
 *   - the grey floating rules -> `divider`, list numbering -> a literal prefix,
 *     line breaks -> one paragraph per line, sizes under 6pt -> 6pt.
 * What the contract cannot express was left out: the second "folder" products
 * table of `services` and `iso` (one table lists every line), multi-column
 * sections, floating decorative shapes in the header.
 */
final class QuoteDocumentLayoutCatalogue
{
    public const string CODE_PREFIX = 'qualifica_quote_';

    /** @var array<string, array{0: string, 1: string}> */
    public const array LAYOUTS = [
        'services' => ['Layout servizi', 'LAYOUT_PREVENTIVO_GENERICO_SERVIZI rev.4.01.2026.docx'],
        'services_previous' => ['Vecchio Layout Servizi (non toccare)', 'LAYOUT_PREVENTIVO_GENERICO_SERVIZI.docx'],
        'iso' => ['Preventivo ISO', 'Template preventivo_ISO Rev. 5.01.2026.docx'],
        'mepa' => ['PREVENTIVO Mepa', 'Template preventivo_MEPA Rev. 3.01.2026.docx'],
        'tender_preparation' => ['PREVENTIVO PREPARAZIONE GARE', 'Template preventivo _Preparazione gare Rev. 4.01.2026.docx'],
        'safety_haccp' => ['PREVENTIVO SIC_HACCP', 'Template preventivo _SIC_HACCP Rev. 3.01.2026.docx'],
        'availment_qualifica' => ['PREVENTIVO AVV_ QUALIFICA', 'Template preventivo_avvalimenti_qualifica Rev.5.01.2026.docx'],
        'alfa_academy' => ['PREVENTIVO ALFA ACADEMY', 'LAYOUT_PREVENTIVO_ALFA_ACADEMY.docx'],
        'tender_information' => ['PREVENTIVO INFO GARE', 'Quotation reparto gare Rev. 2.01.2026.docx'],
        'supplier_register' => ['PREVENTIVO ISCRIZIONE ALBO FORNITORI', 'Template preventivo_albo fornitori Rev.2.01.2026.docx'],
        'availment_grade' => ['PREVENTIVO AVV_GRADE', 'Template preventivo_avvalimenti_GRADE Rev. 4.01.2026.docx'],
        'availment_real' => ['PREVENTIVO AVV_REAL', 'Template preventivo_avvalimenti_REAL Rev. 3.01.2026.docx'],
        'recruitment_fee_200' => ['PREVENTIVO R&S ACC. 200€', 'PROPOSTA COMM.LE RICERCA E SELEZIONE APL 2024.  200€ REV.01.2026x.docx'],
        'recruitment_fee_300' => ['PREVENTIVO R&S ACC. 300€', 'PROPOSTA COMM.LE RICERCA E SELEZIONE APL 2024.  300€.01.2026.docx'],
        'pal' => ['LAYOUT PREVENTIVO PAL', 'LAYOUT_PREVENTIVO_PAL GENERICO Rev. 2.01.2026.docx'],
        'recruitment_fee_400' => ['PREVENTIVO R&S ACC. 400€', 'PROPOSTA COMM.LE RICERCA E SELEZIONE APL 2024.  400€.01.2026ocx.docx'],
        'recruitment_fee_500' => ['PREVENTIVO R&S ACC. 500€', 'PROPOSTA COMM.LE RICERCA E SELEZIONE APL 2024.  500€ocx.docx'],
        'apprenticeship_contract' => ['Layout_CONTRATTO DI SERVIZIO DI APPRENDISTATO PROFESSIONALIZZANTE', 'Contratto di Formazione per Apprendistato agg. 16.09.2026.docx'],
        'training' => ['LAYOUT FORMAZIONE- qualifica formazione & lavoro', 'Preventivi - Qualifica Group Formazione e Lavoro Impresa Sociale Srl (2) (1).docx'],
        'recruitment_fee_500_previous' => ['PREVENTIVO R&S vecchio', 'PROPOSTA COMM.LE RICERCA E SELEZIONE APL 2024.  500€ rev.01.2026.docx'],
        'fnc_training' => ['Layout preventivo FNC_ Qualifica Group Training S.r.l.', 'Layout preventivo FNC_ Qualifica Group Training S.r.l..docx'],
        'union_agreement' => ['Layout Accordo Sindacale_ Qualifica Group Training S.r.l.', 'Layout Accordo Sindacale_ Qualifica Group Training S.r.l..docx'],
        'teaching_edujob' => ['Layout Docenze _Qualifica Group EduJob s.r.l.', 'Layout Docenze _Qualifica Group EduJob s.r.l..docx'],
        'recruitment_contract' => ['LAYOUT PREVENTIVO R&S', 'CONTRATTO R&S - NUOVO FORMAT 09.2026.docx'],
        'training_oss' => ['LAYOUT_ FORMAZIONE OSS', 'Preventivi - FORMAZIONE OSS_ Qualifica Group Formazione e Lavoro Impresa Sociale Srl rev.01.2026.docx'],
        'training_no_instalments' => ['LAYOUT FORMAZIONE_NO RATE- qualifica formazione & lavoro', 'Preventivi - Qualifica Group Formazione e Lavoro Impresa Sociale Srl _NO RATE (1) (1).docx'],
        'private_internship' => ['Tirocini Extracurriculari Privati', 'Proposta commerciale Tirocinio Extracurriculare Privato 09.26 .docx'],
        'training_academy' => ['Preventivi - Qualifica Group Academy Srl', 'Preventivi - Qualifica Group Academy Srl.docx'],
    ];
}
