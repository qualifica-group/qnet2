<?php

namespace Database\Seeders\QualificaCatalog;

/**
 * The products of the "Gare" branch, transcribed from the "SERVIZI GARE" list
 * (user directive 2026-10-20): one row per product, filed on the subcategory
 * of its "Categoria" column, with its price and its "Mesi scadenza". No
 * supplier is named. Pure data, seeded by BranchProducts with the 22% VAT rate
 * and the "Consulenza" typology (TenderCatalogue::TYPOLOGIES).
 *
 * Names are the list's titles verbatim.
 */
final class TenderProductCatalogue
{
    /**
     * Category name => product name => its price, supplier and "Mesi scadenza".
     *
     * @var array<string, array<string, array{price: float, supplier: string|null, expiration_months: int|null}>>
     */
    public const array PRODUCTS = [
        TenderCatalogue::MEPA => [
            'MePa_ Iscrizione al Portale + Abilitazione a N°1 Bando' => ['price' => 250.0, 'supplier' => null, 'expiration_months' => 0],
            'MePa_ Abilitazione ulteriori bandi' => ['price' => 50.0, 'supplier' => null, 'expiration_months' => 0],
            'Variazione Rappresentante Legale' => ['price' => 50.0, 'supplier' => null, 'expiration_months' => 0],
            'Supporto Adeguamento bandi nuovo codice degli appalti D.Lgs. n. 36/2023' => ['price' => 100.0, 'supplier' => null, 'expiration_months' => 0],
            'Variazione Ragione Sociale' => ['price' => 50.0, 'supplier' => null, 'expiration_months' => 0],
            'MEPA_ Aggiornamento iscrizione' => ['price' => 100.0, 'supplier' => null, 'expiration_months' => 0],
        ],
        TenderCatalogue::TENDER_JOURNAL => [
            'GAR_ Giornale telematico gare d’appalto Abbonamento annuale' => ['price' => 450.0, 'supplier' => null, 'expiration_months' => 0],
        ],
        TenderCatalogue::TENDERS => [
            'GAR _ Predisposizione e assistenza' => ['price' => 0.0, 'supplier' => null, 'expiration_months' => 0],
            'AVCP _Iscrizione AVCPass' => ['price' => 80.0, 'supplier' => null, 'expiration_months' => 0],
            'AVCP _Richiesta PassOE' => ['price' => 30.0, 'supplier' => null, 'expiration_months' => 0],
            'GAR _ Predisposizione e assistenza - Busta amministrativa + caricamento su piattaforma' => ['price' => 115.0, 'supplier' => null, 'expiration_months' => 0],
            'Predisposizione e Assistenza Manifestazione d\'interesse' => ['price' => 50.0, 'supplier' => null, 'expiration_months' => 0],
            'Supporto Ufficio Gare Esterno comprensivo di un Accesso On Site al mese' => ['price' => 0.0, 'supplier' => null, 'expiration_months' => 0],
            'Predisposizione e Assistenza - Busta amminsitrativa' => ['price' => 100.0, 'supplier' => null, 'expiration_months' => 0],
            'Caricamento in piattaforma' => ['price' => 15.0, 'supplier' => null, 'expiration_months' => 0],
            'Aggiudicazione Gara CIG' => ['price' => 0.0, 'supplier' => null, 'expiration_months' => 0],
            'Illustrazione del Sistema RENTRI' => ['price' => 0.0, 'supplier' => null, 'expiration_months' => 0],
            'Supporto richiesta chiarimenti ente appaltante' => ['price' => 50.0, 'supplier' => null, 'expiration_months' => 0],
            'Pacchetto predisposizione e assistenza 10 buste amministrative' => ['price' => 600.0, 'supplier' => null, 'expiration_months' => 0],
            'Pacchetto predisposizione e assistenza 5 buste amministrative' => ['price' => 400.0, 'supplier' => null, 'expiration_months' => 0],
            'Consulenza per partecipazione e predisposizione gara d\'appalto' => ['price' => 0.0, 'supplier' => null, 'expiration_months' => 0],
            'servizio supporto specialistico al Rup e utilizzo per la gestione delle procedure di gara e della fase esecutiva dei contratti pubblici' => ['price' => 0.0, 'supplier' => null, 'expiration_months' => 0],
        ],
        TenderCatalogue::JOINT_VENTURES => [
            'RTI _ Supporto e preparazione di documentazione comune per costituendi RTI' => ['price' => 300.0, 'supplier' => null, 'expiration_months' => 0],
            'ATI _ Supporto e preparazione di documentazione comune per costituendi ATI' => ['price' => 300.0, 'supplier' => null, 'expiration_months' => 0],
        ],
        TenderCatalogue::TECHNICAL_PROPOSAL => [
            'GAR_ Redazione offerta tecnica' => ['price' => 0.0, 'supplier' => null, 'expiration_months' => 0],
        ],
        TenderCatalogue::RELIANCE => [
            'AVV _ PREDISPOSIZIONE E ASSISTENZA AVVALIMENTI' => ['price' => 200.0, 'supplier' => null, 'expiration_months' => 0],
            'Predisposizione e Assistenza Avvalimento' => ['price' => 200.0, 'supplier' => null, 'expiration_months' => 0],
        ],
        TenderCatalogue::ANAC => [
            'ISCRIZIONE ANAC (Profilo Contribuente + Profilo Amministratore OE)' => ['price' => 150.0, 'supplier' => null, 'expiration_months' => 0],
        ],
        TenderCatalogue::WHITE_LIST => [
            'Supporto Iscrizione White List' => ['price' => 150.0, 'supplier' => null, 'expiration_months' => 0],
        ],
        TenderCatalogue::SUPPLIER_REGISTERS => [
            'Iscrizione all\'albo Fornitori' => ['price' => 100.0, 'supplier' => null, 'expiration_months' => 0],
        ],
        TenderCatalogue::GRADE_CONSORTIUM => [
            'Adesione Consorzio Grade' => ['price' => 3900.0, 'supplier' => null, 'expiration_months' => 0],
        ],
        TenderCatalogue::TRAINING_COURSE => [
            'Corso Preparazione Gare D\'appalto' => ['price' => 450.0, 'supplier' => null, 'expiration_months' => 0],
        ],
        TenderCatalogue::ENVIRONMENTAL_OPERATORS_REGISTER => [
            'Predisposizione, assistenza e supporto Iscrizione Albo Nazionale Gestore Ambientali' => ['price' => 0.0, 'supplier' => null, 'expiration_months' => 0],
        ],
    ];
}
