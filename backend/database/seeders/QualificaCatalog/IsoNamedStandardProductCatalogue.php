<?php

namespace Database\Seeders\QualificaCatalog;

/**
 * The "ISO" products of the named standards and schemes (CAM, Ecovadis, EMAS,
 * SA8000, UNI PDR...), split out of IsoProductCatalogue for size: same list,
 * same rules.
 */
final class IsoNamedStandardProductCatalogue
{
    /**
     * Category name => product name => its spec.
     *
     * @var array<string, array<string, array{code?: string, description?: string, price?: float, supplier: string|null, expiration_months: int, vat_rate?: array{name: string, rate: float}}>>
     */
    public const array PRODUCTS = [
        'ISO - CAM' => [
            'Consulenza CAM per 4 Macchine Industriali per la pulizia_ 1° Anno' => ['code' => 'CAM', 'supplier' => null, 'expiration_months' => 0],
            'Consulenza CAM per 4 Macchine Industriali per la pulizia_ 2° Anno' => ['code' => 'CAM_2', 'supplier' => null, 'expiration_months' => 12],
            'Consulenza CAM per 4 Macchine Industriali per la pulizia_ 3° Anno' => ['code' => 'CAM_3', 'supplier' => null, 'expiration_months' => 24],
        ],
        'ISO - Certificazione Bio' => [
            'Sistema di gestione per l\'ottenimento della Certificazione Bio' => ['code' => 'Certificazione Bio', 'supplier' => null, 'expiration_months' => 0],
            'Sistema di gestione per il mantenimento della Certificazione Bio_2° Anno' => ['code' => 'Certificazione Bio_2', 'supplier' => null, 'expiration_months' => 0],
            'Sistema di gestione per il mantenimento della Certificazione Bio_3° Anno' => ['code' => 'Certificazione Bio_3', 'supplier' => null, 'expiration_months' => 0],
        ],
        'ISO - CodexA' => [
            'Consulenza CODEX ALIMENTARIUS N.A. _ 1° anno' => ['code' => 'CODEX', 'supplier' => null, 'expiration_months' => 0],
            'Consulenza CODEX ALIMENTARIUS N.A. _ 2° anno' => ['code' => 'CODEX_2', 'supplier' => null, 'expiration_months' => 12],
            'Consulenza CODEX ALIMENTARIUS N.A. _ 3° anno' => ['code' => 'CODEX_3', 'supplier' => null, 'expiration_months' => 24],
        ],
        'ISO - Consulenza Direzionale' => [
            'Consulenza Direzionale' => ['code' => 'C.DIREZIONALE', 'supplier' => null, 'expiration_months' => 0],
            'RECAP_Progetti Tecnici di Consulenza_I° Anno' => ['code' => 'C.DIREZIONALE_1', 'description' => "Consulenza relativa ai seguenti servizi:\n\nISO_ Sistema GDPR e Nomina DPO;\n\nModello Organizzativo ai sensi del d.lgs 231\n\nNomina Organismo di Vigilanza\n\nConsulenza Direzionale", 'supplier' => null, 'expiration_months' => 0],
            'RECAP_Progetti Tecnici di Consulenza_II° Anno' => ['code' => 'C.DIREZIONALE_2', 'supplier' => null, 'expiration_months' => 12],
            'RECAP_Progetti Tecnici di Consulenza_III° Anno' => ['code' => 'C.DIREZIONALE_3', 'supplier' => null, 'expiration_months' => 24],
        ],
        'ISO - CSR' => [
            'Consulenza Corporate Social Responsibility N.A._1° Anno' => ['code' => 'CSR', 'supplier' => null, 'expiration_months' => 0],
            'Consulenza Corporate Social Responsibility N.A._2° Anno' => ['code' => 'CSR_2', 'supplier' => null, 'expiration_months' => 12],
            'Consulenza Corporate Social Responsibility N.A._3° Anno' => ['code' => 'CSR_3', 'supplier' => null, 'expiration_months' => 24],
        ],
        'ISO - D.lgs 231/01' => [
            'Consulenza Modello Organizzativo 231/01_1° Anno' => ['code' => 'D.lgs 231/01', 'supplier' => null, 'expiration_months' => 0],
            'Consulenza Modello Organizzativo 231/01_2° Anno' => ['code' => 'D.lgs 231/01_2', 'supplier' => null, 'expiration_months' => 12],
            'Consulenza Modello Organizzativo 231/01_3° Anno' => ['code' => 'D.lgs 231/01_3', 'supplier' => null, 'expiration_months' => 24],
            'Corso di formazione sui i principi generali del D.lgs. 231/01, con particolare attenzione al reato di corruzione' => ['code' => 'CORSO 231', 'supplier' => null, 'expiration_months' => 0],
        ],
        'ISO - Ecolabel' => [
            'Attività di consulenza per l\'ottenimento marchio ECOLABEL_ 1° Anno' => ['code' => 'Ecolabel', 'supplier' => null, 'expiration_months' => 0],
            'Attività di consulenza per l\'ottenimento marchio ECOLABEL_ 2° Anno' => ['code' => 'Ecolabel_2', 'supplier' => null, 'expiration_months' => 12],
            'Attività di consulenza per l\'ottenimento marchio ECOLABEL_ 3° Anno' => ['code' => 'Ecolabel_3', 'supplier' => null, 'expiration_months' => 24],
            'Integrazione Ecolabel' => ['code' => 'INTEGRAZIONE Ecolabel', 'supplier' => null, 'expiration_months' => 0],
        ],
        'ISO - Ecovadis' => [
            'Supporto all\'aggiornamento questionario Ecovadis' => ['code' => 'AGG.ECOVADIS', 'supplier' => null, 'expiration_months' => 0],
            'Supporto alla redazione della Dichiarazione Ambientale richiesta dal questionario Ecovadis;' => ['code' => 'QUESTIONARIO ECOVADIS', 'supplier' => null, 'expiration_months' => 0],
            'Badge EcoVadis_Committed' => ['code' => 'B.COMMITTED', 'price' => 400.0, 'supplier' => null, 'expiration_months' => 0],
            'Badge EcoVadis_Fast Mover' => ['code' => 'B.FAST', 'price' => 300.0, 'supplier' => null, 'expiration_months' => 0],
            'Supporto alla redazione delle procedure richieste dal questionario Ecovadis' => ['code' => 'PROCEDURA ECOVADIS', 'supplier' => null, 'expiration_months' => 0],
        ],
        'ISO - EMAS' => [
            'Attività di consulenza EMAS_ 1° Anno' => ['code' => 'Emas', 'supplier' => null, 'expiration_months' => 0],
            'Attività di consulenza EMAS_ 2° Anno' => ['code' => 'Emas_2', 'supplier' => null, 'expiration_months' => 12],
            'Attività di consulenza EMAS_ 3° Anno' => ['code' => 'Emas_3', 'supplier' => null, 'expiration_months' => 24],
            'Supporto per la registrazione Emas' => ['code' => 'S.EMAS', 'supplier' => null, 'expiration_months' => 0],
            'Consulenza per assistenza alla compilazione e procedure di trasmissione Mud' => ['code' => 'MUD', 'price' => 600.0, 'supplier' => null, 'expiration_months' => 0],
            'Attività di supporto verifica ispettiva Arpac' => ['code' => 'ARPAC', 'supplier' => null, 'expiration_months' => 0],
            'Supporto per l’aggiornamento della registrazione sul sito dell\'ISPRA' => ['code' => 'ISPRA', 'supplier' => null, 'expiration_months' => 0],
        ],
        'ISO - Esco' => [
            'Consulenza ESCO_1° Anno' => ['code' => 'Esco', 'supplier' => null, 'expiration_months' => 0],
            'Consulenza ESCO_2° Anno' => ['code' => 'Esco_2', 'supplier' => null, 'expiration_months' => 12],
            'Consulenza ESCO_3° Anno' => ['code' => 'Esco_3', 'supplier' => null, 'expiration_months' => 24],
        ],
        'ISO - ESG' => [
            'Servizio di Pre-Assestement ESG' => ['code' => 'ASSESMENT ESG', 'supplier' => null, 'expiration_months' => 0],
        ],
        'ISO - FCC' => [
            'Consulenza FCC_I Anno' => ['code' => 'FCC', 'supplier' => null, 'expiration_months' => 0],
            'Consulenza FCC_II Anno' => ['code' => 'FCC_2', 'supplier' => null, 'expiration_months' => 12],
            'Consulenza FCC_III Anno' => ['code' => 'FCC_3', 'supplier' => null, 'expiration_months' => 24],
        ],
        'ISO - Fgas' => [
            'Consulenza sistema F-Gas _ 1° Anno' => ['code' => 'Fgas', 'supplier' => null, 'expiration_months' => 0],
            'Consulenza sistema F-Gas _ 2° Anno' => ['code' => 'Fgas_2', 'supplier' => null, 'expiration_months' => 12],
            'Consulenza sistema F-Gas _ 3° Anno' => ['code' => 'Fgas_3', 'supplier' => null, 'expiration_months' => 24],
            'Iscrizione della ditta al registro Nazionale FGAS' => ['code' => 'REGISTRO F-GAS', 'description' => 'compresivi di DIRITTI CAMERALI', 'supplier' => null, 'expiration_months' => 0],
            'Noleggio strumentazione' => ['code' => 'NOLEGGIO F-GAS', 'supplier' => null, 'expiration_months' => 0],
        ],
        'ISO - GACP' => [
            'Consulenza G.A.C.P._ 1° Anno' => ['code' => 'GACP', 'supplier' => null, 'expiration_months' => 0, 'vat_rate' => IsoProductCatalogue::NO_VAT_RATE],
            'Consulenza G.A.C.P._ 2° Anno' => ['code' => 'GACP_2', 'supplier' => null, 'expiration_months' => 12, 'vat_rate' => IsoProductCatalogue::NO_VAT_RATE],
            'Consulenza G.A.C.P._ 3° Anno' => ['code' => 'GACP_3', 'supplier' => null, 'expiration_months' => 24, 'vat_rate' => IsoProductCatalogue::NO_VAT_RATE],
        ],
        'ISO - GAP ANALYSIS' => [
            'Processo di GAP ANALYSIS' => ['code' => 'GAP ANALYSIS', 'supplier' => null, 'expiration_months' => 0],
        ],
        'ISO - GMP' => [
            'Consulenza GMP _ 1° Anno' => ['code' => 'GMP', 'supplier' => null, 'expiration_months' => 12],
            'Registrazione portale Ministero salute' => ['code' => 'GMP PORTALE', 'supplier' => null, 'expiration_months' => 12],
            'Consulenza GMP _ 2° Anno' => ['code' => 'GMP_2', 'supplier' => null, 'expiration_months' => 24],
            'Consulenza GMP _ 3° Anno' => ['code' => 'GMP_3', 'supplier' => null, 'expiration_months' => 36],
        ],
        'ISO - ISCC' => [
            'Consulenza finalizzata alla Certificazione ISCC: International Sustainability and Carbon Certification' => ['code' => 'ISSC', 'supplier' => null, 'expiration_months' => 0],
        ],
        'ISO - Marcatura Ce' => [
            'Consulenza Marcatura CE _ 1° Anno' => ['code' => 'Marcatura Ce', 'supplier' => null, 'expiration_months' => 0, 'vat_rate' => IsoProductCatalogue::NO_VAT_RATE],
            'Consulenza Marcatura CE_2 ° Anno' => ['code' => 'Marcatura Ce_2', 'supplier' => null, 'expiration_months' => 24, 'vat_rate' => IsoProductCatalogue::NO_VAT_RATE],
            'Consulenza Marcatura CE_3° Anno' => ['code' => 'Marcatura Ce_3', 'supplier' => null, 'expiration_months' => 24, 'vat_rate' => IsoProductCatalogue::NO_VAT_RATE],
            'ANALISI DI LABORATORIO' => ['code' => 'ANALISI', 'supplier' => null, 'expiration_months' => 0],
        ],
        'ISO - NAWI' => [
            'Consulenza finalizzata alla dichiarazione di Conformità UE bilance NAWI_1°Anno' => ['code' => 'NAWI', 'supplier' => null, 'expiration_months' => 0],
            'Consulenza finalizzata alla dichiarazione di Conformità UE bilance NAWI_2°Anno' => ['code' => 'NAWI_2', 'supplier' => null, 'expiration_months' => 24],
            'Consulenza finalizzata alla dichiarazione di Conformità UE bilance NAWI_3°Anno' => ['code' => 'NAWI_3', 'supplier' => null, 'expiration_months' => 24],
        ],
        'ISO - ODV' => [
            'Nomina dell\'organismo di vigilanza ai sensi del d.lgs.231_ 1° Anno' => ['code' => 'ODV', 'supplier' => null, 'expiration_months' => 0],
            'Nomina dell\'organismo di vigilanza ai sensi del d.lgs.231_ 2° Anno' => ['code' => 'ODV_2', 'supplier' => null, 'expiration_months' => 12],
            'Nomina dell\'organismo di vigilanza ai sensi del d.lgs.231_ 3° Anno' => ['code' => 'ODV_3', 'supplier' => null, 'expiration_months' => 24],
        ],
        'ISO - QASER' => [
            'Consulenza questionario di autovalutazione QASER_1° Anno' => ['code' => 'QASER', 'supplier' => null, 'expiration_months' => 0],
            'Consulenza questionario di autovalutazione QASER_2° Anno' => ['code' => 'QASER_2', 'supplier' => null, 'expiration_months' => 12],
            'Consulenza questionario di autovalutazione QASER_3° Anno' => ['code' => 'QASER_3', 'supplier' => null, 'expiration_months' => 24],
        ],
        'ISO - RATING DI LEGALITA\'' => [
            'Supporto e assistenza per l\'ottenimento e l\'attribuzione Rating di Legalità: analisi dei requisiti e attività di supporto tecnico nella predisposizione della documentazione.' => ['code' => 'RATING', 'supplier' => null, 'expiration_months' => 0],
        ],
        'ISO - RSGI' => [
            'Assunzione incarico RSGI 9001-14001-45001 - I ANNO' => ['code' => 'RSGI', 'supplier' => null, 'expiration_months' => 0],
            'Assunzione incarico RSGI 9001-14001-45001 - II ANNO' => ['code' => 'RSGI_2', 'supplier' => null, 'expiration_months' => 12],
            'Assunzione incarico RSGI 9001-14001-45001 - III ANNO' => ['code' => 'RSGI_3', 'supplier' => null, 'expiration_months' => 24],
        ],
        'ISO - SA8000' => [
            'Consulenza SA 8000 _I EMISSIONE' => ['code' => 'SA8000', 'supplier' => null, 'expiration_months' => 0],
            'Compilazione Social Finger Print' => ['code' => 'SFP', 'price' => 200.0, 'supplier' => null, 'expiration_months' => 0],
            'Consulenza SA 8000_SORVEGLIANZA 6 MESI' => ['code' => 'SA8000_6', 'supplier' => null, 'expiration_months' => 6],
            'Consulenza SA 8000_SORVEGLIANZA 12 MESI' => ['code' => 'SA8000_12', 'supplier' => null, 'expiration_months' => 12],
            'Consulenza SA 8000_SORVEGLIANZA 18 MESI' => ['code' => 'SA8000_18', 'supplier' => null, 'expiration_months' => 18],
            'Consulenza SA 8000_SORVEGLIANZA 24 MESI' => ['code' => 'SA8000_24', 'supplier' => null, 'expiration_months' => 24],
            'Consulenza SA 8000_SORVEGLIANZA 30 MESI' => ['code' => 'SA8000_30', 'supplier' => null, 'expiration_months' => 30],
            'Consulenza SA 8000_SORVEGLIANZA 33- 35 MESI' => ['code' => 'SA8000_33-35', 'supplier' => null, 'expiration_months' => 35],
            'Studio di fattibilità finalizzato alla verifica del possesso dei requisiti relativi allo standard SA 8000' => ['code' => 'STUDIO SA8000', 'supplier' => null, 'expiration_months' => 0],
            'Traduzione dei modelli SA 8000' => ['code' => 'TRADUZIONE SA8000', 'price' => 300.0, 'supplier' => null, 'expiration_months' => 0],
        ],
        'ISO - UNI PDR 125' => [
            'Consulenza UNI PDR 125 _ 1° Anno' => ['code' => 'UNI PDR 125', 'supplier' => null, 'expiration_months' => 0],
            'Consulenza UNI PDR 125 _ 2° Anno' => ['code' => 'UNI PDR 125_2', 'supplier' => null, 'expiration_months' => 12],
            'Consulenza UNI PDR 125 _ 3° Anno' => ['code' => 'UNI PDR 125_3', 'supplier' => null, 'expiration_months' => 24],
            'Studio di fattibilità finalizzato alla verifica del possesso dei requisiti relativi allo standard UNI PDR 125' => ['code' => 'STUDIO UNI PDR 125', 'supplier' => null, 'expiration_months' => 0],
            'Voucher UNI PDR 125' => ['code' => 'VOUCHER UNI PDR 125', 'price' => 150.0, 'supplier' => null, 'expiration_months' => 0],
        ],
        'ISO - UNI PDR 74' => [
            'Consulenza UNI PDR 74_1° Anno' => ['code' => 'UNI PDR 74', 'supplier' => null, 'expiration_months' => 0],
            'Consulenza UNI PDR 74_2° Anno' => ['code' => 'UNI PDR 74_2', 'supplier' => null, 'expiration_months' => 12],
            'Consulenza UNI PDR 74_3° Anno' => ['code' => 'UNI PDR 74_3', 'supplier' => null, 'expiration_months' => 24],
        ],
    ];
}
