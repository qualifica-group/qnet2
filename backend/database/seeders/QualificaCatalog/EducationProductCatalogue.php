<?php

namespace Database\Seeders\QualificaCatalog;

/**
 * The products of the "Istruzione" branch, transcribed from the "ISTRUZIONE"
 * list (user directive 2026-10-20): one row per product, filed on the
 * subcategory of its "Categoria" column, with its price (0 for every row), its
 * "Mesi scadenza" (0 for every row) and, where the list gives one, its
 * description. No supplier is named. Pure data, seeded by BranchProducts with
 * the 22% VAT rate and the "Consulenza" typology (EducationCatalogue::TYPOLOGIES).
 *
 * Names and descriptions are the list's verbatim.
 */
final class EducationProductCatalogue
{
    private const string THERMAL_ACCOUNT_DESCRIPTION = <<<'TEXT'
        Gli incentivi sono determinati in funzione delle spese ammissibili previste per la realizzazione dell'intervento, nel rispetto dei massimali specifici per:

        unità di superficie
        unità di potenza
        producibilità degli impianti
        Il limite massimo, fatta eccezione per alcune tipologie di intervento, è del 65%.

        Le tipologie di intervento che fanno eccezione e che prevedono quindi un limite massimo del 100% sono:

        interventi realizzati su edifici di proprietà di Comuni con popolazione fino a 15.000 abitanti utilizzati dagli stessi Comuni o utilizzati da soggetti terzi che non siano imprese per lo svolgimento di attività di carattere pubblico-sociale e servizi di interesse collettivo attribuite all'ente locale;
        interventi realizzati su scuole e su strutture ospedaliere e di altre strutture sanitarie pubbliche del Servizio Sanitario Nazionale.
        È prevista, per gli interventi del Titolo II (art. 5, comma 1, lett. a)-f)) sempre nel rispetto dei limiti massimi del 65% e del 100%, una maggiorazione del 10% nel caso in cui vengano utilizzati componenti esclusivamente prodotti nell'Unione Europea.
        TEXT;

    /**
     * Category name => product name => its description, price, supplier and "Mesi scadenza".
     *
     * @var array<string, array<string, array{description?: string, price: float, supplier: string|null, expiration_months: int|null}>>
     */
    public const array PRODUCTS = [
        EducationCatalogue::EDUCATION => [
            'LABORATORI PROFESSIONALIZZANTI' => ['price' => 0.0, 'supplier' => null, 'expiration_months' => 0],
        ],
        EducationCatalogue::PNRR => [
            'Istruzione' => ['price' => 0.0, 'supplier' => null, 'expiration_months' => 0],
            'Corso Mentoring e Orientamento' => ['price' => 0.0, 'supplier' => null, 'expiration_months' => 0],
            'Corso Digital Lab' => ['price' => 0.0, 'supplier' => null, 'expiration_months' => 0],
            'Corso Fornitura' => ['price' => 0.0, 'supplier' => null, 'expiration_months' => 0],
            'Corso Laboratori Green' => ['price' => 0.0, 'supplier' => null, 'expiration_months' => 0],
            'Corso Competenze di base' => ['price' => 0.0, 'supplier' => null, 'expiration_months' => 0],
            'Corso Competenze Curriculari' => ['price' => 0.0, 'supplier' => null, 'expiration_months' => 0],
            'Mentoring ed orientamento alle famiglie' => ['price' => 0.0, 'supplier' => null, 'expiration_months' => 0],
            'BARCAMP_DM66_CHROMEBOOK' => ['price' => 0.0, 'supplier' => null, 'expiration_months' => 0],
            'BARCAMP_DM66_TABLET' => ['price' => 0.0, 'supplier' => null, 'expiration_months' => 0],
            'BARCAMP_DM66_ROBOTICA' => ['price' => 0.0, 'supplier' => null, 'expiration_months' => 0],
            'BARCAMP_DM66_AI NELLA DIDATTICA' => ['price' => 0.0, 'supplier' => null, 'expiration_months' => 0],
            'BARCAMP_DM66_LA DIDATTICA PERSONALIZZATA' => ['price' => 0.0, 'supplier' => null, 'expiration_months' => 0],
            'BARCAMP_DM66_AI PERSONALE ATA' => ['price' => 0.0, 'supplier' => null, 'expiration_months' => 0],
        ],
        EducationCatalogue::SCHOOL_CONSULTING => [
            'Consulenza amministrativa - contabile' => ['price' => 0.0, 'supplier' => null, 'expiration_months' => 0],
            'DM88' => ['description' => 'ALTERNANZA SCUOLA LAVORO', 'price' => 0.0, 'supplier' => null, 'expiration_months' => 0],
        ],
        EducationCatalogue::THERMAL_ACCOUNT => [
            'CONTO TERMICO 3.0' => ['description' => self::THERMAL_ACCOUNT_DESCRIPTION, 'price' => 0.0, 'supplier' => null, 'expiration_months' => 0],
        ],
    ];
}
