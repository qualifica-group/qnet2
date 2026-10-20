<?php

namespace Database\Seeders\QualificaCatalog;

/**
 * The products of the "Finanza Agevolata" branch, transcribed from the
 * "FINANZA AGEVOLATA" list (user directive 2026-10-20): one row per product,
 * filed on the subcategory of its "Categoria" column, with its price, its
 * "Mesi scadenza" and, where the list gives one, its description. No supplier
 * is named. Pure data, seeded by BranchProducts with the 22% VAT rate (also on
 * the one row the list leaves without a rate) and the "Consulenza" typology
 * (SubsidisedFinanceCatalogue::TYPOLOGIES).
 *
 * Names are the list's titles verbatim.
 */
final class SubsidisedFinanceProductCatalogue
{
    /**
     * Category name => product name => its description, price, supplier and "Mesi scadenza".
     *
     * @var array<string, array<string, array{description?: string, price: float, supplier: string|null, expiration_months: int|null}>>
     */
    public const array PRODUCTS = [
        SubsidisedFinanceCatalogue::SELF_EMPLOYMENT_CENTRE_NORTH => [
            'Autoimpiego Centro Nord -Fase I e II: Presentazione del Progetto ed Istruttoria' => ['price' => 1000.0, 'supplier' => null, 'expiration_months' => 0],
            'Autoimpiego Centro Nord -Fase III:Post Approvazione' => ['price' => 0.0, 'supplier' => null, 'expiration_months' => 0],
        ],
        SubsidisedFinanceCatalogue::TOURISM_DECREE => [
            'Decreto Turismo - Fase I' => ['price' => 2000.0, 'supplier' => null, 'expiration_months' => 0],
        ],
        SubsidisedFinanceCatalogue::SUBSIDISED_FINANCE => [
            'Campania FESR 2021-2027' => ['price' => 0.0, 'supplier' => null, 'expiration_months' => 0],
            'Progetto interconnessione Software QNET con escavatore' => ['price' => 3000.0, 'supplier' => null, 'expiration_months' => 0],
            'Vendita ed installazione Software Qnet e relativa personalizzazione per business cliente' => ['price' => 12000.0, 'supplier' => null, 'expiration_months' => 0],
            'Supporto alla presentazione bando' => ['price' => 0.0, 'supplier' => null, 'expiration_months' => 0],
            'Affiancamento dell\'Innovation Manager iscritto all\'Albo di Accredia/Ministero Made in Italy per la diagnosi progettuale' => ['price' => 0.0, 'supplier' => null, 'expiration_months' => 0],
            'FASE II - CLICK DAY PREDISPOSIZIONE RICHIESTA CONTRIBUTO 2025' => ['price' => 400.0, 'supplier' => null, 'expiration_months' => 0],
            'FASE III - CLICK DAY PREDISPOSIZIONE RICHIESTA CONTRIBUTO 2025' => ['price' => 800.0, 'supplier' => null, 'expiration_months' => 0],
            'bandi CCIAA' => ['price' => 0.0, 'supplier' => null, 'expiration_months' => 0],
            'Misure di conciliazione famiglia – lavoro' => ['price' => 0.0, 'supplier' => null, 'expiration_months' => 0],
            'FIN_Supporto alla rendicontazione avviso' => ['price' => 0.0, 'supplier' => null, 'expiration_months' => 0],
            'VOUCHER CCIAA' => ['price' => 0.0, 'supplier' => null, 'expiration_months' => 0],
        ],
        SubsidisedFinanceCatalogue::FNC => [
            'FNC_ Fondo Nuove Competenze_Fase I' => ['price' => 500.0, 'supplier' => null, 'expiration_months' => 0],
            'Attività di docenza Fondo Nuove Competenze' => ['price' => 0.0, 'supplier' => null, 'expiration_months' => 0],
            'FNC_ Fondo Nuove Competenze_Fase II' => ['price' => 0.0, 'supplier' => null, 'expiration_months' => 0],
            'FNC_ Fondo Nuove Competenze_Fase III' => ['price' => 0.0, 'supplier' => null, 'expiration_months' => 0],
            'FNC_ Fondo Nuove Competenze_Fase IV' => ['price' => 0.0, 'supplier' => null, 'expiration_months' => 0],
            'Accordo Sindacale' => ['price' => 300.0, 'supplier' => null, 'expiration_months' => 0],
        ],
        SubsidisedFinanceCatalogue::FUNDS => [
            'Consulenza per studio di prefattibilità Credito d\'imposta beni strumentali 4.0 e Bonus Sud' => ['price' => 0.0, 'supplier' => null, 'expiration_months' => 0],
            'FORME-P07- Corso "Elementi di Project management"' => ['price' => 0.0, 'supplier' => null, 'expiration_months' => 0],
            'Concessione Fondo_' => ['price' => 0.0, 'supplier' => null, 'expiration_months' => 0],
        ],
        SubsidisedFinanceCatalogue::CONTINUING_TRAINING => [
            'AVVISO FORMAZIONE CONTINUA:Fase I Supporto alla presentazione della domanda + Fase II Progettazione e Invio della pratica' => ['price' => 300.0, 'supplier' => null, 'expiration_months' => 0],
            'AVVISO FORMAZIONE CONTINUA:Fase III - IV Erogazione della Formazione e rendicontazione' => ['price' => 0.0, 'supplier' => null, 'expiration_months' => 0],
        ],
        SubsidisedFinanceCatalogue::FRC => [
            'FRC_FASE I' => ['price' => 0.0, 'supplier' => null, 'expiration_months' => 0],
            'FRC_FASE II' => ['price' => 0.0, 'supplier' => null, 'expiration_months' => 0],
            'FRC_FASE III' => ['price' => 0.0, 'supplier' => null, 'expiration_months' => 0],
        ],
        SubsidisedFinanceCatalogue::FUNT => [
            'Fase I-Consulenza e supporto alla compilazione del formulario di progetto-Avviso pubblico: Turismo Eco-Sociale Accessibile e Sostenibile” – Regione Campania (FUNT CC)' => ['price' => 1000.0, 'supplier' => null, 'expiration_months' => 0],
            'Fase II-Supporto post approvazione e rendicontazione finale_FUNT CC' => ['price' => 0.0, 'supplier' => null, 'expiration_months' => 0],
        ],
        SubsidisedFinanceCatalogue::HIRING_INCENTIVES => [
            'Avviso Incentivi Assunzioni_Fase I Pre-fattibilità' => ['price' => 0.0, 'supplier' => null, 'expiration_months' => 0],
            'Avviso Incentivi Assunzioni_Fase II Supporto alla presentazione della domanda e Fase III Supporto post-approvazione e rendicontazione finale' => ['price' => 0.0, 'supplier' => null, 'expiration_months' => 0],
        ],
        SubsidisedFinanceCatalogue::INDUSTRY_4_0 => [
            'FASE 1 - STUDIO DI PRE-FATTIBILITA\' FUNZIONALE ALLA RICHIESTA DEL CREDITO D\'IMPOSTA + FASE 2 -PREDISPOSIZIONE DELLA DOCUMENTAZIONE PER L\'INVIO ALL\'AGENZIA DELLE ENTRATE' => ['price' => 0.0, 'supplier' => null, 'expiration_months' => 0],
            'Industria - Perizia' => ['price' => 0.0, 'supplier' => null, 'expiration_months' => 0],
            'Predisposizione relazione di interconnessione per industria 4.0 con perizia tecnico abilitato' => ['price' => 0.0, 'supplier' => null, 'expiration_months' => 0],
            'Studio di fattibilità progetto industria 4.0 e rilascio attestazioni' => ['price' => 3000.0, 'supplier' => null, 'expiration_months' => 0],
        ],
        SubsidisedFinanceCatalogue::HYPER_DEPRECIATION => [
            'Consulenza Iper-ammortamento' => ['price' => 0.0, 'supplier' => null, 'expiration_months' => 0],
        ],
        SubsidisedFinanceCatalogue::ISI_INAIL => [
            'Consulenza ISI Inail - I fase' => ['price' => 0.0, 'supplier' => null, 'expiration_months' => 0],
            'Consulenza ISI Inail - II e III Fase' => ['price' => 0.0, 'supplier' => null, 'expiration_months' => 0],
            'Consulenza ISI Inail - IV fase' => ['description' => '8% calcolato sul contributo a fondo perduto.', 'price' => 0.0, 'supplier' => null, 'expiration_months' => 0],
            'Asseverazione Ingegnere abilitato' => ['price' => 1200.0, 'supplier' => null, 'expiration_months' => 0],
            'Perizia Giurata dei Beni' => ['price' => 0.0, 'supplier' => null, 'expiration_months' => 0],
            'Consulenza ISI Inail -Fase 0' => ['price' => 0.0, 'supplier' => null, 'expiration_months' => 0],
            'Consuelnza ISI INAIL - Fase I -II-III' => ['price' => 1200.0, 'supplier' => null, 'expiration_months' => 0],
        ],
        SubsidisedFinanceCatalogue::RESTO_AL_SUD => [
            'Resto al Sud' => ['price' => 0.0, 'supplier' => null, 'expiration_months' => 0],
            'RESTO AL SUD- FASE 1 E 2' => ['price' => 1300.0, 'supplier' => null, 'expiration_months' => 0],
            'RESTO AL SUD- FASE 3' => ['price' => 0.0, 'supplier' => null, 'expiration_months' => 0],
        ],
    ];
}
