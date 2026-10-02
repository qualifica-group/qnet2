<?php

namespace Database\Seeders\QualificaCatalog;

use App\Enums\LayoutSectionVariant;

/**
 * The OFFERTA-context fields of the e-Campus branch, transcribed from the
 * "BOZZA gestionale_Progetto Università" sheet (user directive 2026-10-01):
 * its FACOLTA' to Finanziamento columns. Pure data, like
 * CourseDataAttributeCatalogue — QualificaCatalogSeeder creates and assigns
 * them on the "Corsi E-Campus" node, QualificaQuoteLayoutSeeder lays them
 * out in the three SECTIONS below.
 *
 * They are the branch's WHOLE offer form: "Corsi E-Campus" is cut off the
 * Formazione fields (CategoryInheritanceRules), which describe GOL/DOTE
 * paperwork and classroom editions an online degree has none of.
 *
 * The sheet's option lists become `enum` options; its "flag" columns become
 * `boolean` fields. Its course column is not a field (user directive
 * 2026-10-02): the course is the offer's product, filed on "Corsi E-Campus".
 * The same directive adds three document flags, replaces the payment flags
 * with the "Tipo di pagamento" select ("Finanziamento" among its options) and
 * turns the "PROGETTO FORM" fee, no longer a product, into the "Corso Form"
 * flag.
 * `code` is the English identifier; `name` and the option labels are the
 * user-facing values, kept in their original language.
 */
final class ECampusAttributeCatalogue
{
    /**
     * @var list<array{code: string, name: string, type: string, options?: list<array{value: string, label: string}>}>
     */
    public const array ATTRIBUTES = [
        ['code' => 'faculty', 'name' => 'Facoltà', 'type' => 'enum', 'options' => [
            ['value' => 'psychology', 'label' => 'Psicologia'],
            ['value' => 'economics', 'label' => 'Economia'],
            ['value' => 'law', 'label' => 'Giurisprudenza'],
            ['value' => 'engineering', 'label' => 'Ingegneria'],
            ['value' => 'humanities', 'label' => 'Lettere'],
        ]],
        ['code' => 'degree_level', 'name' => 'Triennale/Magistrale', 'type' => 'enum', 'options' => [
            ['value' => 'bachelor', 'label' => 'Triennale'],
            ['value' => 'master', 'label' => 'Magistrale'],
        ]],
        ['code' => 'form_course', 'name' => 'Corso Form', 'type' => 'boolean'],
        ['code' => 'identification_documents', 'name' => 'Documenti di riconoscimento', 'type' => 'boolean'],
        ['code' => 'enrollment_form', 'name' => 'Modulo di iscrizione', 'type' => 'boolean'],
        ['code' => 'diploma_or_self_certification', 'name' => 'Diploma / autocertificazione', 'type' => 'boolean'],
        ['code' => 'annex_a', 'name' => 'Allegato A', 'type' => 'boolean'],
        ['code' => 'ecampus_receipt', 'name' => 'Contabile e-Campus', 'type' => 'boolean'],
        ['code' => 'qualifica_receipt', 'name' => 'Contabile Qualifica', 'type' => 'boolean'],
        ['code' => 'data_collection_form', 'name' => 'Modulo raccolta dati', 'type' => 'boolean'],
        ['code' => 'payment_type', 'name' => 'Tipo di pagamento', 'type' => 'enum', 'options' => [
            ['value' => 'single_payment', 'label' => 'Unica soluzione'],
            ['value' => 'two_installments', 'label' => '2 rate'],
            ['value' => 'three_installments', 'label' => '3 rate'],
            ['value' => 'financing', 'label' => 'Finanziamento'],
        ]],
    ];

    /**
     * The offer form, in reading order, shaped like
     * QualificaQuoteLayoutSeeder::SECTIONS plus each section's style (user
     * directive 2026-10-01: a tidy form): what the candidate enrols in, on
     * top; the documents received, two per row; the payment type alone. Every
     * section is plain, none highlighted (user directive 2026-10-02). Every
     * field fills its row.
     *
     * @var list<array{0: string, 1: string, 2: list<list<string>>, 3: array{variant: LayoutSectionVariant, columns: int, description: string}}>
     */
    public const array SECTIONS = [
        ['ecampus-course', 'Corso di Laurea', [
            ['faculty', 'degree_level'],
            ['form_course'],
        ], ['variant' => LayoutSectionVariant::Default, 'columns' => 2, 'description' => 'Facoltà, livello e corso di laurea scelti dal candidato.']],
        ['ecampus-documents', 'Documenti di iscrizione', [
            ['identification_documents', 'enrollment_form'],
            ['diploma_or_self_certification', 'annex_a'],
            ['ecampus_receipt', 'qualifica_receipt'],
            ['data_collection_form'],
        ], ['variant' => LayoutSectionVariant::Default, 'columns' => 2, 'description' => 'Spunta i documenti ricevuti dal candidato.']],
        ['ecampus-payment', 'Pagamento', [
            ['payment_type'],
        ], ['variant' => LayoutSectionVariant::Default, 'columns' => 1, 'description' => 'Modalità di pagamento scelta dal candidato.']],
    ];

    /**
     * The form the 2026-10-01 revision wrote, kept so QualificaQuoteLayoutSeeder
     * recognises it — as the retirement of its course and payment fields left
     * it — and recomposes it instead of freezing it as user data.
     *
     * @var list<array{0: string, 1: string, 2: list<list<string>>, 3: array{variant: LayoutSectionVariant, columns: int, description: string}}>
     */
    public const array PREVIOUS_SECTIONS = [
        ['ecampus-course', 'Corso di Laurea', [
            ['faculty', 'degree_level'],
            ['degree_course'],
        ], ['variant' => LayoutSectionVariant::Highlighted, 'columns' => 2, 'description' => 'Facoltà, livello e corso di laurea scelti dal candidato.']],
        ['ecampus-documents', 'Documenti di iscrizione', [
            ['identification_documents', 'enrollment_form'],
            ['diploma_or_self_certification', 'annex_a'],
        ], ['variant' => LayoutSectionVariant::Default, 'columns' => 2, 'description' => 'Spunta i documenti ricevuti dal candidato.']],
        ['ecampus-payment', 'Pagamento', [
            ['fee_regulation', 'bank_transfer', 'financing'],
        ], ['variant' => LayoutSectionVariant::Default, 'columns' => 3, 'description' => 'Regolamento economico firmato e modalità di pagamento scelta.']],
    ];
}
