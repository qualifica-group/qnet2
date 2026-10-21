<?php

namespace Database\Seeders\QualificaCatalog;

/**
 * The "ISO" products of the combined standards ("9001+14001"...), of the
 * certifying bodies ("Ente 9001"..., supplier IQI) and of the general and
 * multi-standard services, split out of IsoProductCatalogue for size: same
 * list, same rules.
 */
final class IsoMultiStandardProductCatalogue
{
    /**
     * Category name => product name => its spec.
     *
     * @var array<string, array<string, array{code?: string, description?: string, price?: float, supplier: string|null, expiration_months: int, vat_rate?: array{name: string, rate: float}}>>
     */
    public const array PRODUCTS = [
        'ISO - 3834-2+1090' => [
            'Consulenza ISO 3834-2 + EN 1090_ 1° Anno' => ['code' => '3834-2+1090', 'supplier' => null, 'expiration_months' => 0],
            'Consulenza ISO 3834-2 + EN 1090_ 2° Anno' => ['code' => '3834-2+1090_2', 'supplier' => null, 'expiration_months' => 12],
            'Consulenza ISO 3834-2 + EN 1090_ 3° Anno' => ['code' => '3834-2+1090_3', 'supplier' => null, 'expiration_months' => 24],
        ],
        'ISO - 9001+11034' => [
            'Consulenza ISO 9001 + ISO 11034 _ 1° anno' => ['code' => '9+11034', 'supplier' => null, 'expiration_months' => 0],
            'Consulenza ISO 9001 + ISO 11034 _ 2° anno' => ['code' => '9+11034_2', 'supplier' => null, 'expiration_months' => 12],
            'Consulenza ISO 9001+ ISO 11034 _ 3° anno' => ['code' => '9+11034_3', 'supplier' => null, 'expiration_months' => 24],
        ],
        'ISO - 9001+14001' => [
            'Consulenza ISO 9001 + ISO 14001 _ 1° Anno' => ['code' => '9+14', 'supplier' => null, 'expiration_months' => 0],
            'Consulenza ISO 9001 + ISO 14001 _ 2° Anno' => ['code' => '9+14_2', 'supplier' => null, 'expiration_months' => 12],
            'Consulenza ISO 9001 + ISO 14001 _ 3° Anno' => ['code' => '9+14_3', 'supplier' => null, 'expiration_months' => 24],
        ],
        'ISO - 9001+14001+37001' => [
            'Consulenza ISO 9001 + ISO 14001 + ISO 37001_ 1° Anno' => ['code' => '9+14+37', 'supplier' => null, 'expiration_months' => 0],
            'Consulenza ISO 9001 + ISO 14001 + ISO 37001_ 2° Anno' => ['code' => '9+14+37_2', 'supplier' => null, 'expiration_months' => 12],
            'Consulenza ISO 9001 + ISO 14001 + ISO 37001_ 3° Anno' => ['code' => '9+14+37_3', 'supplier' => null, 'expiration_months' => 24],
        ],
        'ISO - 9001+14001+45001' => [
            'Consulenza ISO 9001 + ISO 14001 + ISO 45001_1° Anno' => ['code' => '9+14+45', 'supplier' => null, 'expiration_months' => 0],
            'Consulenza ISO 9001 + ISO14001 + ISO 45001_2° Anno' => ['code' => '9+14+45_2', 'supplier' => null, 'expiration_months' => 12],
            'Consulenza ISO 9001 + ISO14001 + ISO 45001_3° Anno' => ['code' => '9+14+45_3', 'supplier' => null, 'expiration_months' => 24],
        ],
        'ISO - 9001+14001+45001+37001' => [
            'Consulenza ISO 9001 + 14001 + 45001 + 37001_ 1° Anno' => ['code' => '9+14+45+37', 'supplier' => null, 'expiration_months' => 0],
            'Consulenza ISO 9001 + 14001 + 45001 + 37001_ 2° Anno' => ['code' => '9+14+45+37_2', 'supplier' => null, 'expiration_months' => 12],
            'Consulenza ISO 9001 + 14001 + 45001 + 37001_ 3° Anno' => ['code' => '9+14+45+37_3', 'supplier' => null, 'expiration_months' => 24],
        ],
        'ISO - 9001+14001+45001+50001' => [
            'Consulenza ISO 9001 + UNI EN 14001 + UNI ISO 45001+ ISO 50001_ 1°' => ['code' => '9+14+45+50001', 'supplier' => null, 'expiration_months' => 0],
            'Consulenza ISO 9001 + UNI EN 14001 + UNI ISO 45001+ ISO 50001_ 2°' => ['code' => '9+14+45+50001_2', 'supplier' => null, 'expiration_months' => 12],
            'Consulenza ISO 9001 + UNI EN 14001 + UNI ISO 45001+ ISO 50001_ 3°' => ['code' => '9+14+45+50001_3', 'supplier' => null, 'expiration_months' => 24],
        ],
        'ISO - 9001+27001' => [
            'Consulenza ISO 9001+27001__I anno' => ['code' => '9+27', 'supplier' => null, 'expiration_months' => 0],
            'Consulenza ISO 9001+27001_II anno' => ['code' => '9+27_2', 'supplier' => null, 'expiration_months' => 12],
            'Consulenza ISO 9001+27001_III anno' => ['code' => '9+27_3', 'supplier' => null, 'expiration_months' => 24],
        ],
        'ISO - 9001+27001+27017' => [
            'ConsulenzaISO 9001+27001+27017_I anno' => ['code' => '9+27+27017', 'supplier' => null, 'expiration_months' => 0],
            'Consulenza ISO 9001+27001+27017_II anno' => ['code' => '9+27+27017_2', 'supplier' => null, 'expiration_months' => 12],
            'Consulenza ISO 9001+27001+27017_III anno' => ['code' => '9+27+27017_3', 'supplier' => null, 'expiration_months' => 0],
        ],
        'ISO - 9001+45001' => [
            'Consulenza ISO 9001+ ISO 45001_ 1° Anno' => ['code' => '9+45', 'supplier' => null, 'expiration_months' => 0],
            'Consulenza ISO 9001+ ISO 45001_ 2° Anno' => ['code' => '9+45_2', 'supplier' => null, 'expiration_months' => 12],
            'Consulenza ISO 9001+ ISO 45001_ 3° Anno' => ['code' => '9+45_3', 'supplier' => null, 'expiration_months' => 24],
        ],
        'ISO - 9001+45001+22000' => [
            'ConsulenzaISO 9001 + ISO 45001 +ISO 20000_ 1° Anno' => ['code' => '9+45+22', 'supplier' => null, 'expiration_months' => 0],
            'ConsulenzaISO 9001 + ISO 45001 +ISO 20000_ 2° Anno' => ['code' => '9+45+22_2', 'supplier' => null, 'expiration_months' => 12],
            'ConsulenzaISO 9001 + ISO 45001 +ISO 20000_ 3° Anno' => ['code' => '9+45+22_3', 'supplier' => null, 'expiration_months' => 24],
        ],
        'ISO - 9001+45001+27001' => [
            'ISO 9001 + ISO 45001 + ISO 27001 _ 1° Anno' => ['code' => '9+45+27', 'supplier' => null, 'expiration_months' => 0],
            'ISO 9001 + ISO 45001 + ISO 27001 _ 2° Anno' => ['code' => '9+45+27_2', 'supplier' => null, 'expiration_months' => 12],
            'ISO 9001 + ISO 45001 + ISO 27001 _ 3° Anno' => ['code' => '9+45+27_3', 'supplier' => null, 'expiration_months' => 24],
        ],
        'ISO - 9001+45001+37001' => [
            'Consulenza ISO 9001 + ISO 45001 + ISO 37001_ 1° Anno' => ['code' => '9+45+37', 'supplier' => null, 'expiration_months' => 0],
            'Consulenza ISO 9001 + ISO 45001 + ISO 37001_ 2° Anno' => ['code' => '9+45+37_2', 'supplier' => null, 'expiration_months' => 12],
            'Consulenza ISO 9001 + ISO 45001 + ISO 37001_ 3° Anno' => ['code' => '9+45+37_3', 'supplier' => null, 'expiration_months' => 24],
        ],
        'ISO - 9001+9001RT21' => [
            'Consulenza ISO 9001:2015 + ISO 9001 RT21_ 1° Anno' => ['code' => '9+RT21', 'supplier' => null, 'expiration_months' => 0],
            'Consulenza ISO 9001:2015 + ISO 9001 RT21_ 2° Anno' => ['code' => '9+RT21_2', 'supplier' => null, 'expiration_months' => 12],
            'Consulenza ISO 9001:2015 + ISO 9001 RT21_ 3° Anno' => ['code' => '9+RT21_3', 'supplier' => null, 'expiration_months' => 24],
        ],
        'ISO - 9120+9100' => [
            'Consulenza AS/EN 9120 + AS/EN 9100_ 1° Anno' => ['code' => '9120+9100', 'supplier' => null, 'expiration_months' => 0],
            'Consulenza AS/EN 9120 + AS/EN 9100_ 2° Anno' => ['code' => '9120+9100_2', 'supplier' => null, 'expiration_months' => 12],
            'Consulenza AS/EN 9120 + AS/EN 9100_ 3° Anno' => ['code' => '9120+9100_3', 'supplier' => null, 'expiration_months' => 24],
        ],
        'ISO - 14001+45001' => [
            'Consulenza ISO 14001 + ISO 45001_ 1°anno' => ['code' => '14+45', 'supplier' => null, 'expiration_months' => 0],
            'Consulenza ISO 14001 + ISO 45001_ 2°anno' => ['code' => '14+45_2', 'supplier' => null, 'expiration_months' => 24],
            'Consulenza ISO 14001 + ISO 45001_ 3°anno' => ['code' => '14+4532', 'supplier' => null, 'expiration_months' => 24],
            'Ampliamento Scopo ISO 14001 + ISO 45001_1°Anno' => ['code' => 'SCOPO 14+45', 'supplier' => null, 'expiration_months' => 0],
            'Ampliamento Scopo ISO 14001 + ISO 45001_2°Anno' => ['code' => 'SCOPO 14+45_2', 'supplier' => null, 'expiration_months' => 12],
            'Ampliamento Scopo ISO 14001 + ISO 45001_3°Anno' => ['code' => 'SCOPO 14+45_3', 'supplier' => null, 'expiration_months' => 24],
        ],
        'ISO - 14001+45001+22000' => [
            'Consulenza ISO 14001 + UNI ISO 45001+ ISO 22000_1° Anno' => ['code' => '14+45+22', 'supplier' => null, 'expiration_months' => 0],
            'Consulenza ISO 14001 + UNI ISO 45001+ ISO 22000_2° Anno' => ['code' => '14+45+22_2', 'supplier' => null, 'expiration_months' => 12],
            'Consulenza ISO 14001 + UNI ISO 45001+ ISO 22000_3° Anno' => ['code' => '14+45+22_3', 'supplier' => null, 'expiration_months' => 24],
        ],
        'ISO - 14001+50001' => [
            'Consulenza ISO 14001+ ISO 50001_ 1°Anno' => ['code' => '14+50001', 'supplier' => null, 'expiration_months' => 0],
            'Consulenza ISO 14001+ ISO 50001_ 2°Anno' => ['code' => '14+50001_2', 'supplier' => null, 'expiration_months' => 12],
            'Consulenza ISO 14001+ ISO 50001_ 3°Anno' => ['code' => '14+50001_3', 'supplier' => null, 'expiration_months' => 24],
        ],
        'ISO - 22000+22005' => [
            'Consulenza ISO 22000 +ISO 22005_1 Anno' => ['code' => '22+22005', 'supplier' => null, 'expiration_months' => 0],
            'Consulenza ISO 22000 +ISO 22005_2 Anno' => ['code' => '22+22005_2', 'supplier' => null, 'expiration_months' => 12],
            'Consulenza ISO 22000 +ISO 22005_3 Anno' => ['code' => '22+22005_3', 'supplier' => null, 'expiration_months' => 24],
        ],
        'ISO - 27017+27018' => [
            'Consulenza ISO/IEC 27017 + ISO/IEC 27018_ 1°Anno' => ['code' => '27017+27018', 'supplier' => null, 'expiration_months' => 0],
            'Consulenza ISO/IEC 27017 + ISO/IEC 27018_ 2°Anno' => ['code' => '27017+27018_2', 'supplier' => null, 'expiration_months' => 12],
            'Consulenza ISO/IEC 27017 + ISO/IEC 27018_ 3°Anno' => ['code' => '27017+27018_3', 'supplier' => null, 'expiration_months' => 24],
        ],
        'ISO - 37001+27001+39001+UNI PDR 125' => [
            'Consulenza ISO 37001, ISO/IEC 27001,UNI ISO 39001, UNI PDR 125 _ 1°anno' => ['code' => '37+27+39+UNI PDR125', 'supplier' => null, 'expiration_months' => 0],
            'Consulenza ISO 37001, ISO/IEC 27001,UNI ISO 39001, UNI PDR 125 _ 2°anno' => ['code' => '37+27+39+UNI PDR125_2', 'supplier' => null, 'expiration_months' => 12],
            'Consulenza ISO 37001, ISO/IEC 27001,UNI ISO 39001, UNI PDR 125 _ 3°anno' => ['code' => '37+27+39+UNI PDR125_3', 'supplier' => null, 'expiration_months' => 24],
        ],
        'ISO - 37001+50001' => [
            'Consulenza ISO 37001 + ISO 50001 _ 1° Anno' => ['code' => '37+50001', 'price' => 800.0, 'supplier' => null, 'expiration_months' => 0],
            'Consulenza ISO 37001 + ISO 50001 _ 2° Anno' => ['code' => '37+50001_2', 'price' => 800.0, 'supplier' => null, 'expiration_months' => 12],
            'Consulenza ISO 37001 + ISO 50001 _ 3° Anno' => ['code' => '37+50001_3', 'supplier' => null, 'expiration_months' => 24],
        ],
        'ISO - ISO 18295-1 + ISO 18295-2' => [
            'Consulenza ISO 18295-1 + ISO 18295-2_1° anno' => ['code' => 'ISO 18295-1 + ISO 18295-2', 'supplier' => null, 'expiration_months' => 0],
            'Consulenza ISO 18295-1 + ISO 18295-2 _2° anno' => ['code' => 'ISO 18295-1 + ISO 18295-2_2', 'supplier' => null, 'expiration_months' => 12],
            'Consulenza ISO 18295-1 + ISO 18295-2 _3° anno' => ['code' => 'ISO 18295-1 + ISO 18295-2_3', 'supplier' => null, 'expiration_months' => 24],
        ],
        'ISO - PIF+CPSR' => [
            'Corso di formazione per la redazione e la gestione del PIF (Product Information File) e del CPSR (Cosmetic Product Safety Report)' => ['code' => 'PIF+CPSR', 'supplier' => null, 'expiration_months' => 0],
        ],
        'ISO - Ente 14001' => [
            'ENTE IQI_ISO 14001_1 ANNO' => ['code' => 'IQI_14', 'supplier' => IsoProductCatalogue::IQI, 'expiration_months' => 0],
            'ENTE IQI_ISO 14001_2 ANNO' => ['code' => 'IQI_14_2', 'supplier' => IsoProductCatalogue::IQI, 'expiration_months' => 12],
            'ENTE IQI_ISO 14001_3 ANNO' => ['code' => 'IQI_14_3', 'supplier' => IsoProductCatalogue::IQI, 'expiration_months' => 24],
        ],
        'ISO - Ente 22000' => [
            'ENTE IQI_ISO 22000_1 ANNO' => ['code' => 'IQI_22', 'supplier' => IsoProductCatalogue::IQI, 'expiration_months' => 0],
            'ENTE IQI_ISO 22000_2 ANNO' => ['code' => 'IQI_22_2', 'supplier' => IsoProductCatalogue::IQI, 'expiration_months' => 12],
            'ENTE IQI_ISO 22000_3 ANNO' => ['code' => 'IQI_22_3', 'supplier' => IsoProductCatalogue::IQI, 'expiration_months' => 24],
        ],
        'ISO - Ente 27001' => [
            'ENTE IQI_ISO 27001_1 ANNO' => ['code' => 'IQI_27', 'supplier' => IsoProductCatalogue::IQI, 'expiration_months' => 0],
            'ENTE IQI_ISO 27001_2 ANNO' => ['code' => 'IQI_27_2', 'supplier' => IsoProductCatalogue::IQI, 'expiration_months' => 12],
            'ENTE IQI_ISO 27001_3 ANNO' => ['code' => 'IQI_27_3', 'supplier' => IsoProductCatalogue::IQI, 'expiration_months' => 24],
        ],
        'ISO - Ente 45001' => [
            'ENTE IQI_SO 45001_1 ANNO' => ['code' => 'IQI_45', 'supplier' => IsoProductCatalogue::IQI, 'expiration_months' => 0],
            'ENTE IQI_ISO 45001_2 ANNO' => ['code' => 'IQI_45_2', 'supplier' => IsoProductCatalogue::IQI, 'expiration_months' => 12],
            'ENTE IQI_ISO 45001_3 ANNO' => ['code' => 'IQI_45_3', 'supplier' => IsoProductCatalogue::IQI, 'expiration_months' => 24],
        ],
        'ISO - Ente 9001' => [
            'ENTE IQI _ISO 9001_1 ANNO' => ['code' => 'IQI_9', 'supplier' => IsoProductCatalogue::IQI, 'expiration_months' => 0],
            'ENTE IQI_ISO 9001_2 ANNO' => ['code' => 'IQI_9_2', 'supplier' => IsoProductCatalogue::IQI, 'expiration_months' => 12],
            'ENTE IQI_ISO 9001_3 ANNO' => ['code' => 'IQI_9_3', 'supplier' => IsoProductCatalogue::IQI, 'expiration_months' => 24],
        ],
        'ISO - Ente 9001+14001+45001' => [
            'ENTE IQI_ISO 9001+14001+45001_1 ANNO' => ['code' => 'IQI_9+14+45', 'supplier' => IsoProductCatalogue::IQI, 'expiration_months' => 0],
            'ENTE IQI_ISO 9001+14001+45001_2 ANNO' => ['code' => 'IQI_9+14+45_2', 'supplier' => IsoProductCatalogue::IQI, 'expiration_months' => 12],
            'ENTE IQI_ISO 9001+14001+45001_3 ANNO' => ['code' => 'IQI_9+14+45_3', 'supplier' => IsoProductCatalogue::IQI, 'expiration_months' => 24],
        ],
        'ISO - Ente F-GAS' => [
            'Certificazione Aziendale F-Gas' => ['code' => 'Ente F-GAS', 'supplier' => null, 'expiration_months' => 0],
        ],
        'ISO - Servizi generali' => [
            'Aggiornamento Modulo C2' => ['code' => 'C2', 'supplier' => null, 'expiration_months' => 0],
            'Inserimento Certificato ISO in Camera di Commercio' => ['code' => 'COMMERCIO ISO', 'price' => 450.0, 'supplier' => null, 'expiration_months' => 0],
            'Variazione Sede Legale' => ['code' => 'SEDE LEGALE', 'supplier' => null, 'expiration_months' => 0],
            'Supporto in Verifica' => ['code' => 'SUPPORTO IN VERIFICA', 'supplier' => null, 'expiration_months' => 0],
            'Attività di supporto per la chiusura della Non Conformità correlata allo standard' => ['code' => 'NON CONFORMITA\'', 'supplier' => null, 'expiration_months' => 0],
            'Supporto di certificazione- FOLLOW UP' => ['code' => 'FOLLOW UP', 'supplier' => null, 'expiration_months' => 0],
            'ISO_supporto alla revisione della procedura operativa carica e scarica gas refrigeranti e revisione registro nc per incidenti ambientali' => ['supplier' => null, 'expiration_months' => 0],
        ],
        'ISO - Standard multipli' => [
            'Variazione indirizzo sede legale e ragione sociale azienda' => ['code' => 'VARIAZ. SEDE+RAGIONE SOCIALE', 'supplier' => null, 'expiration_months' => 0],
            'Consulenza ISO 14001:2015 + ISO 45001:20180 + ISO 13485 _ 1°anno' => ['code' => '14+45+13485', 'supplier' => null, 'expiration_months' => 0],
            'Consulenza ISO 14001:2015 + ISO 45001:20180 + ISO 13485 _ 2°anno' => ['code' => '14+45+13485_2', 'supplier' => null, 'expiration_months' => 24],
            'Consulenza ISO 14001:2015 + ISO 45001:20180 + ISO 13485 _ 3°anno' => ['code' => '14+45+13485_3', 'supplier' => null, 'expiration_months' => 24],
        ],
    ];
}
