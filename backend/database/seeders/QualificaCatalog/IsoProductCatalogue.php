<?php

namespace Database\Seeders\QualificaCatalog;

/**
 * The products of the "ISO" branch, transcribed from the "iso" service list
 * (user directive 2026-10-21), one row per product, filed on the IsoSubcategories
 * line of its "Categoria": this class holds the single numbered standards,
 * IsoNamedStandardProductCatalogue the named ones and
 * IsoMultiStandardProductCatalogue the combined standards, the certifying
 * bodies and the general services. Pure data, seeded by BranchProducts.
 *
 * Each row keeps the list's title, code, price, "Mesi scadenza", description
 * and supplier (IQI on the certifying-body lines). Corrected from the list
 * (user decisions 2026-10-21): the third year of "IQI_14", "22+22005" and
 * "PAS 24000" carried the second year's code and now ends in "_3"; the six
 * "Ampliamento scopo" and "Transizione" 45001 services, which repeated the
 * "45001" code, and the one row without a code take the generated "PRD-" one;
 * the third year of "27017+27018" was titled "2°Anno"; the two "mantenimento
 * della Certificazione Bio" rows take their year in the title. The list's
 * "Cartella", "Voce di costo" and "Voce di ricavo" have no product field and
 * are not carried over.
 */
final class IsoProductCatalogue
{
    /** The certifying body of the "Ente" lines, matched on its registry name. */
    public const string IQI = 'IQI';

    /** The list's "Nessuna" VAT rate, on the Marcatura CE, GACP and 11381 lines. */
    public const array NO_VAT_RATE = ['name' => 'Nessuna', 'rate' => 0.0];

    /**
     * Category name => product name => its spec.
     *
     * @var array<string, array<string, array{code?: string, description?: string, price?: float, supplier: string|null, expiration_months: int, vat_rate?: array{name: string, rate: float}}>>
     */
    public const array PRODUCTS = [
        'ISO - 1090' => [
            'Consulenza ISO EN 1090 _ 1° Anno' => ['code' => '1090', 'supplier' => null, 'expiration_months' => 0],
            'Consulenza ISO EN 1090 _ 2° Anno' => ['code' => '1090_2', 'supplier' => null, 'expiration_months' => 12],
            'Consulenza ISO EN 1090 _ 3° Anno' => ['code' => '1090_3', 'supplier' => null, 'expiration_months' => 24],
        ],
        'ISO - 9001' => [
            'Consulenza ISO 9001_1° Anno' => ['code' => '9001', 'supplier' => null, 'expiration_months' => 0],
            'Consulenza ISO 9001_2° Anno' => ['code' => '9001_2', 'supplier' => null, 'expiration_months' => 12],
            'Consulenza ISO 9001__3° Anno' => ['code' => '9001_3', 'supplier' => null, 'expiration_months' => 24],
            'Ampliamento Scopo ISO 9001_1°Anno' => ['code' => 'SCOPO 9001', 'supplier' => null, 'expiration_months' => 0],
            'Ampliamento Scopo ISO 9001_2°Anno' => ['code' => 'SCOPO 9001_2', 'supplier' => null, 'expiration_months' => 12],
            'Ampliamento Scopo ISO 9001_3°Anno' => ['code' => 'SCOPO 9001_3', 'supplier' => null, 'expiration_months' => 24],
            'Stesura con supporto alla compilazione di un Piano della Qualità' => ['code' => 'PIANO QUALITA\'', 'supplier' => null, 'expiration_months' => 0],
            'Attività di supporto per la chiusura della Non Conformità correlata allo standard: UNI EN ISO 9001:2015' => ['code' => 'N.C. 9001', 'supplier' => null, 'expiration_months' => 0],
            'Supporto ai fini della presentazione dell’istanza al Consiglio Superiore dei Lavori Pubblici' => ['code' => 'ISTANZA ISO9001', 'supplier' => null, 'expiration_months' => 0],
            'Audit interno preliminare per la valutazione del grado di conformità aziendale ai requisiti della norma ISO 9001' => ['code' => 'VERIFICA REQUISITI ISO 9001', 'supplier' => null, 'expiration_months' => 0],
        ],
        'ISO - 9100' => [
            'Consulenza ISO 9100_ 1 Anno' => ['code' => '9100', 'supplier' => null, 'expiration_months' => 0],
            'Consulenza ISO 9100_ 2 Anno' => ['code' => '9100_2', 'supplier' => null, 'expiration_months' => 24],
            'Consulenza ISO 9100_ 3 Anno' => ['code' => '9100_3', 'supplier' => null, 'expiration_months' => 36],
            'Supporto alla verifica ispettiva UNI ISO 9100 sui rischi e la gestione della qualità' => ['code' => 'VERIFICA 9100', 'supplier' => null, 'expiration_months' => 0],
        ],
        'ISO - 10854' => [
            'Consulenza ISO 10854 _ 1° Anno' => ['code' => '10854', 'supplier' => null, 'expiration_months' => 0],
            'Consulenza ISO 1085 _ 2° Anno' => ['code' => '10854_2', 'supplier' => null, 'expiration_months' => 12],
            'Consulenza ISO 1085_ 3° Anno' => ['code' => '10854_3', 'supplier' => null, 'expiration_months' => 24],
        ],
        'ISO - 11010' => [
            'Consulenza ISO 11010_ 1° Anno' => ['code' => '11010', 'supplier' => null, 'expiration_months' => 0],
            'Consulenza ISO 11010_ 2° Anno' => ['code' => '11010_2', 'supplier' => null, 'expiration_months' => 12],
            'Consulenza ISO 11010_ 3° Anno' => ['code' => '11010_3', 'supplier' => null, 'expiration_months' => 24],
        ],
        'ISO - 11034' => [
            'Consulenza ISO 11034_ 1° Anno' => ['code' => '11034', 'supplier' => null, 'expiration_months' => 0],
            'Consulenza ISO 11034_2° Anno' => ['code' => '11034_2', 'supplier' => null, 'expiration_months' => 12],
            'Consulenza ISO 11034_3° Anno' => ['code' => '11034_3', 'supplier' => null, 'expiration_months' => 24],
        ],
        'ISO - 11381' => [
            'Consulenza 11381_ 1° Anno' => ['code' => '11381', 'supplier' => null, 'expiration_months' => 12, 'vat_rate' => self::NO_VAT_RATE],
            'Consulenza 11381_ 2° Anno' => ['code' => '11381_2', 'supplier' => null, 'expiration_months' => 12, 'vat_rate' => self::NO_VAT_RATE],
            'Consulenza 11381_ 3° Anno' => ['code' => '11381_3', 'supplier' => null, 'expiration_months' => 24, 'vat_rate' => self::NO_VAT_RATE],
        ],
        'ISO - 13485' => [
            'Consulenza ISO 13485 _ 1° Anno' => ['code' => '13485', 'supplier' => null, 'expiration_months' => 0],
            'ConsulenzaISO 13485 _ 2° Anno' => ['code' => '13485_2', 'supplier' => null, 'expiration_months' => 12],
            'Consulenza ISO 13485 _ 3° Anno' => ['code' => '13485_3', 'supplier' => null, 'expiration_months' => 24],
        ],
        'ISO - 13549' => [
            'Consulenza ISO 13549_ 1° Anno' => ['code' => '13549', 'supplier' => null, 'expiration_months' => 0],
            'Consulenza ISO 13549_ 2° Anno' => ['code' => '13549_2', 'supplier' => null, 'expiration_months' => 12],
            'Consulenza ISO 13549_ 3° Anno' => ['code' => '13549_3', 'supplier' => null, 'expiration_months' => 24],
        ],
        'ISO - 14001' => [
            'Consulenza ISO 14001 _ 1° Anno' => ['code' => '14001', 'supplier' => null, 'expiration_months' => 0],
            'Consulenza ISO 14001 _ 2° Anno' => ['code' => '14001_2', 'supplier' => null, 'expiration_months' => 12],
            'Consulenza ISO 14001_ 3° Anno' => ['code' => '14001_3', 'supplier' => null, 'expiration_months' => 24],
            'Ampliamento di scopo di certificazione ISO 9001_ 1° Anno' => ['code' => 'SCOPO 14001', 'supplier' => null, 'expiration_months' => 0],
            'Ampliamento di scopo di certificazione ISO 9001_ 2° Anno' => ['code' => 'SCOPO 14001_2', 'supplier' => null, 'expiration_months' => 12],
            'Ampliamento di scopo di certificazione ISO 9001_ 3° Anno' => ['code' => 'SCOPO 14001_3', 'supplier' => null, 'expiration_months' => 24],
            'Corso Responsabile Sistema di Gestione Ambientale' => ['code' => 'CORSO 14001', 'price' => 300.0, 'supplier' => null, 'expiration_months' => 0],
        ],
        'ISO - 14021' => [
            'Consulenza ISO 14021:2016 _ 1° Anno' => ['code' => '14021', 'supplier' => null, 'expiration_months' => 0],
            'Consulenza ISO 14021:2016 _ 2° Anno' => ['code' => '14021_2', 'supplier' => null, 'expiration_months' => 12],
            'Consulenza ISO 14021:2016 _ 3° Anno' => ['code' => '14021_3', 'supplier' => null, 'expiration_months' => 24],
        ],
        'ISO - 14024' => [
            'Consulenza ISO 14024_1° Anno' => ['code' => '14024', 'supplier' => null, 'expiration_months' => 0],
            'Consulenza ISO 14024_ 2° Anno' => ['code' => '14024_2', 'supplier' => null, 'expiration_months' => 12],
            'Consulenza ISO 14024_ 3° Anno' => ['code' => '14024_3', 'supplier' => null, 'expiration_months' => 24],
        ],
        'ISO - 14064' => [
            'Consulenza ISO 14064 _1 Anno' => ['code' => '14064', 'supplier' => null, 'expiration_months' => 0],
            'Consulenza ISO 14064 _2 Anno' => ['code' => '14064_2', 'supplier' => null, 'expiration_months' => 12],
            'Consulenza ISO 14064 _3 Anno' => ['code' => '14064_3', 'supplier' => null, 'expiration_months' => 24],
        ],
        'ISO - 14067' => [
            'Consulenza ISO 14067_ 1° Anno' => ['code' => '14067', 'supplier' => null, 'expiration_months' => 0],
            'Consulenza ISO 14067_ 2° Anno' => ['code' => '14067_2', 'supplier' => null, 'expiration_months' => 12],
            'Consulenza ISO 14067_ 3° Anno' => ['code' => '14067_3', 'supplier' => null, 'expiration_months' => 24],
        ],
        'ISO - 16636' => [
            'Attività di consulenza ISO 16636_ 1° Anno' => ['code' => '16636', 'supplier' => null, 'expiration_months' => 0],
            'Attività di consulenza ISO 16636_ 2° Anno' => ['code' => '16636_2', 'supplier' => null, 'expiration_months' => 12],
            'Attività di consulenza ISO 16636_ 3° Anno' => ['code' => '16636_3', 'supplier' => null, 'expiration_months' => 24],
        ],
        'ISO - 17020' => [
            'Consulenza per l’accreditamento UNI EN 17020 del laboratorio divisione metrologica_1°Anno' => ['code' => '17020', 'supplier' => null, 'expiration_months' => 0],
            'Consulenza per l’accreditamento UNI EN 17020 del laboratorio divisione metrologica_2°Anno' => ['code' => '17020_2', 'supplier' => null, 'expiration_months' => 24],
            'Consulenza per l’accreditamento UNI EN 17020 del laboratorio divisione metrologica_3°Anno' => ['code' => '17020_3', 'supplier' => null, 'expiration_months' => 24],
        ],
        'ISO - 17025' => [
            'Consulenza ISO/IEC 17025_ 1 Anno' => ['code' => '17025', 'supplier' => null, 'expiration_months' => 0],
            'Consulenza ISO/IEC 17025_ 2 Anno' => ['code' => '17025_2', 'supplier' => null, 'expiration_months' => 12],
            'Consulenza ISO/IEC 17025_ 3 Anno' => ['code' => '17025_3', 'supplier' => null, 'expiration_months' => 24],
        ],
        'ISO - 20121' => [
            'Predisposizione Sistema di Gestione Standard conforme alla norma: UNI EN ISO 20121 N.A._I ANNO' => ['code' => '20121', 'supplier' => null, 'expiration_months' => 0],
            'Predisposizione Sistema di Gestione Standard conforme alla norma: UNI EN ISO 20121 N.A._II ANNO' => ['code' => '20121_2', 'supplier' => null, 'expiration_months' => 12],
            'Aggiornamento Sistema di Gestione Standard conforme alla norma: UNI EN ISO 20121 N.A._III ANNO' => ['code' => '20121_3', 'supplier' => null, 'expiration_months' => 12],
        ],
        'ISO - 20400' => [
            'Consulenza ISO 20400_1°anno' => ['code' => '20400_1', 'supplier' => null, 'expiration_months' => 0],
            'Consulenza ISO 20400_2°anno' => ['code' => '20400_2', 'supplier' => null, 'expiration_months' => 12],
            'Consulenza ISO 20400_3°anno' => ['code' => '20400_3', 'supplier' => null, 'expiration_months' => 24],
        ],
        'ISO - 21502' => [
            'Consulenza ISO 21502 _ 1° Anno' => ['code' => '21502', 'supplier' => null, 'expiration_months' => 0],
            'Consulenza ISO 21502 _ 2° Anno' => ['code' => '21502_2', 'supplier' => null, 'expiration_months' => 12],
            'Consulenza ISO 21502 _ 3° Anno' => ['code' => '21502_3', 'supplier' => null, 'expiration_months' => 24],
        ],
        'ISO - 22000' => [
            'Consulenza ISO 22000_ 1° Anno' => ['code' => '22000', 'supplier' => null, 'expiration_months' => 0],
            'Consulenza ISO 22000_ 2° Anno' => ['code' => '22000_2', 'supplier' => null, 'expiration_months' => 12],
            'Consulenza ISO 22000_3° Anno' => ['code' => '22000_3', 'supplier' => null, 'expiration_months' => 24],
        ],
        'ISO - 22005' => [
            'Consulenza ISO 22005 _1° Anno' => ['code' => '22005', 'supplier' => null, 'expiration_months' => 0],
            'Consulenza ISO 22005 _2° Anno' => ['code' => '22005_2', 'supplier' => null, 'expiration_months' => 12],
            'Consulenza ISO 22005 _3° Anno' => ['code' => '22005_3', 'supplier' => null, 'expiration_months' => 24],
        ],
        'ISO - 24000' => [
            'Consulenza PAS 24000_ 1°Anno' => ['code' => 'PAS 24000', 'supplier' => null, 'expiration_months' => 0],
            'Consulenza PAS 24000_ 2°Anno' => ['code' => 'PAS 24000_2', 'supplier' => null, 'expiration_months' => 12],
            'Consulenza PAS 24000_ 3°Anno' => ['code' => 'PAS 24000_3', 'supplier' => null, 'expiration_months' => 24],
        ],
        'ISO - 27001' => [
            'ISO_vulnerability assesment' => ['code' => 'VULNERABILITY', 'supplier' => null, 'expiration_months' => 0],
            'Consulenza ISO/IEC 27001_1° Anno' => ['code' => '27001', 'supplier' => null, 'expiration_months' => 0],
            'Consulenza ISO/IEC 27001_2° Anno' => ['code' => '27001_2', 'supplier' => null, 'expiration_months' => 12],
            'Consulenza ISO/IEC 27001_3° Anno' => ['code' => '27001_3', 'supplier' => null, 'expiration_months' => 24],
            'Ente_ Transizione ISO/IEC 27001:2022' => ['code' => 'TRANSIZIONE 27001', 'supplier' => null, 'expiration_months' => 0],
            'Transizione ISO 27001:2013 in ISO/IEC 27001:2022_1 anno' => ['code' => '27001:2022', 'supplier' => null, 'expiration_months' => 0],
            'Transizione ISO 27001:2013 in ISO/IEC 27001:2022_2 anno' => ['code' => '27001:2022_2', 'supplier' => null, 'expiration_months' => 12],
            'Transizione ISO 27001:2013 in ISO/IEC 27001:2022_3 anno' => ['code' => '27001:2022_3', 'supplier' => null, 'expiration_months' => 24],
        ],
        'ISO - 29001' => [
            'Consulenza ISO_29001_ 1° Anno' => ['code' => '29001', 'supplier' => null, 'expiration_months' => 0],
            'Consulenza ISO_29001_ 2° Anno' => ['code' => '29001_2', 'supplier' => null, 'expiration_months' => 12],
            'Consulenza ISO_29001_ 3° Anno' => ['code' => '29001_3', 'supplier' => null, 'expiration_months' => 24],
        ],
        'ISO - 30415' => [
            'Consulenza ISO 30415_ 1° Anno' => ['code' => '30415', 'supplier' => null, 'expiration_months' => 0],
            'Consulenza ISO 30415_ 2° Anno' => ['code' => '30415_2', 'supplier' => null, 'expiration_months' => 12],
            'Consulenza ISO 30415_ 3° Anno' => ['code' => '30415_3', 'supplier' => null, 'expiration_months' => 24],
            'Supporto alla redazione della politica aziendale nel rispetto della normativa: UNI EN ISO 30415' => ['code' => 'POLITICA 30415', 'supplier' => null, 'expiration_months' => 0],
        ],
        'ISO - 37001' => [
            'Consulenza ISO 37001_ 1° Anno' => ['code' => '37001', 'supplier' => null, 'expiration_months' => 0],
            'Consulenza ISO 37001_ 2° Anno' => ['code' => '37001_2', 'supplier' => null, 'expiration_months' => 12],
            'Consulenza ISO 37001_3° Anno' => ['code' => '37001_3', 'supplier' => null, 'expiration_months' => 24],
            'Adeguamento Normativo: UNI ISO 37001:2025_I anno' => ['code' => '37001:2025', 'supplier' => null, 'expiration_months' => 0],
            'Adeguamento Normativo: UNI ISO 37001:2025_II anno' => ['code' => '37001:2025_2', 'supplier' => null, 'expiration_months' => 12],
            'Adeguamento Normativo: UNI ISO 37001:2025_III anno' => ['code' => '37001:2025_3', 'supplier' => null, 'expiration_months' => 24],
            'INCARICO FCPC- ai soli fini della verifica ispettiva ISO 37001' => ['code' => 'FCPC', 'supplier' => null, 'expiration_months' => 0],
            'Nomina RPC obbligatorio per l\'adozione del sistema ISO 37001:2025' => ['code' => 'RPC', 'supplier' => null, 'expiration_months' => 0],
        ],
        'ISO - 39001' => [
            'Consulenza ISO 39001_ 1° Anno' => ['code' => '39', 'supplier' => null, 'expiration_months' => 0],
            'Consulenza ISO 39001_ 2° Anno' => ['code' => '39_2', 'supplier' => null, 'expiration_months' => 12],
            'Consulenza ISO 39001_ 3° Anno' => ['code' => '39_3', 'supplier' => null, 'expiration_months' => 24],
        ],
        'ISO - 45001' => [
            'Consulenza 45001_ 1° Anno' => ['code' => '45001', 'supplier' => null, 'expiration_months' => 0],
            'Consulenza 45001 _ 2° Anno' => ['code' => '45001_2', 'supplier' => null, 'expiration_months' => 12],
            'Consulenza 45001 _ 3° Anno' => ['code' => '45001_3', 'supplier' => null, 'expiration_months' => 24],
            'Supporto Compilazione Modello OT23_1° anno' => ['code' => 'OT23', 'price' => 700.0, 'supplier' => null, 'expiration_months' => 0],
            'Supporto Compilazione Modello OT23_2° anno' => ['code' => 'OT23_2', 'price' => 300.0, 'supplier' => null, 'expiration_months' => 12],
            'Supporto Compilazione Modello OT23_3° anno' => ['code' => 'OT23_3', 'price' => 300.0, 'supplier' => null, 'expiration_months' => 24],
            'Supporto Variazione di Scopo 45001_1 anno' => ['code' => 'SCOPO 45001', 'supplier' => null, 'expiration_months' => 0],
            'Supporto Variazione di Scopo 45001_2 anno' => ['code' => 'SCOPO 45001_2', 'supplier' => null, 'expiration_months' => 12],
            'Supporto Variazione di Scopo 45001_3 anno' => ['code' => 'SCOPO 45001_3', 'supplier' => null, 'expiration_months' => 24],
            'Supporto Ampliamento Scopo ISO 45001_1°Anno' => ['supplier' => null, 'expiration_months' => 0],
            'Supporto Ampliamento Scopo ISO 45001_2°Anno' => ['supplier' => null, 'expiration_months' => 12],
            'Supporto Ampliamento Scopo ISO 45001_3°Anno' => ['supplier' => null, 'expiration_months' => 24],
            'ISO_Transizione alla 45001_1° anno' => ['supplier' => null, 'expiration_months' => 0],
            'ISO_Transizione alla 45001_2° anno' => ['supplier' => null, 'expiration_months' => 12],
            'ISO_Transizione alla 45001_3° anno' => ['supplier' => null, 'expiration_months' => 24],
        ],
        'ISO - 50001' => [
            'Consulenza EN ISO 50001_ 1° Anno' => ['code' => '50001', 'supplier' => null, 'expiration_months' => 0],
            'Consulenza EN ISO 50001_ 2° Anno' => ['code' => '50001_2', 'supplier' => null, 'expiration_months' => 12],
            'Consulenza EN ISO 50001_ 3° Anno' => ['code' => '50001_3', 'supplier' => null, 'expiration_months' => 24],
            'Implementazione Relazione Energetica' => ['code' => 'RELAZIONE ENERGETICA 50001', 'supplier' => null, 'expiration_months' => 0],
            'Diagnosi Energetica' => ['code' => 'DIAGNOSI', 'supplier' => null, 'expiration_months' => 0],
        ],
    ];
}
