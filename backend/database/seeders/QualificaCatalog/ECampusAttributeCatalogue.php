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
 * `boolean` fields; the course column, which lists no option, is free text.
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
        ['code' => 'degree_course', 'name' => 'Corso di Laurea (CdS)', 'type' => 'text'],
        ['code' => 'degree_level', 'name' => 'Triennale/Magistrale', 'type' => 'enum', 'options' => [
            ['value' => 'bachelor', 'label' => 'Triennale'],
            ['value' => 'master', 'label' => 'Magistrale'],
        ]],
        ['code' => 'identification_documents', 'name' => 'Documenti di riconoscimento', 'type' => 'boolean'],
        ['code' => 'enrollment_form', 'name' => 'Modulo di iscrizione', 'type' => 'boolean'],
        ['code' => 'diploma_or_self_certification', 'name' => 'Diploma / autocertificazione', 'type' => 'boolean'],
        ['code' => 'annex_a', 'name' => 'Allegato A', 'type' => 'boolean'],
        ['code' => 'fee_regulation', 'name' => 'Regolamento Economico', 'type' => 'boolean'],
        ['code' => 'bank_transfer', 'name' => 'Bonifico', 'type' => 'boolean'],
        ['code' => 'financing', 'name' => 'Finanziamento', 'type' => 'boolean'],
    ];

    /**
     * The offer form, in reading order, shaped like
     * QualificaQuoteLayoutSeeder::SECTIONS plus each section's style (user
     * directive 2026-10-01: a tidy form): what the candidate enrols in,
     * highlighted on top; the documents received, two per row; the payment
     * side, the three checks on one row. Every field fills its row.
     *
     * @var list<array{0: string, 1: string, 2: list<list<string>>, 3: array{variant: LayoutSectionVariant, columns: int, description: string}}>
     */
    public const array SECTIONS = [
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
