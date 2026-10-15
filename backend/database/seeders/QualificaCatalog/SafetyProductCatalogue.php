<?php

namespace Database\Seeders\QualificaCatalog;

/**
 * The products of the "Sicurezza" branch, transcribed from the "SICUREZZA 81_09"
 * list (user directive 2026-10-15): one row per product, filed on the
 * subcategory of its "Categoria" column, with its price, its "Mesi scadenza"
 * (null where the list leaves it blank) and the supplier the list's notes name
 * (GONDOLA SRLS for the F-gas products, ND CONTROL for the welding ones).
 * Pure data, seeded by BranchProducts with the 22% VAT rate and the
 * "Consulenza" typology (SafetyCatalogue::TYPOLOGIES).
 *
 * Names are the list's titles verbatim; the one title repeated in a category
 * ("Formazione dei datori di lavoro ... - durata 16 ore") takes its product code
 * in brackets, so the two survive as distinct products (user decision 2026-10-15).
 */
final class SafetyProductCatalogue
{
    /**
     * Category name => product name => its price, supplier and "Mesi scadenza".
     *
     * @var array<string, array<string, array{price: float, supplier: string|null, expiration_months: int|null}>>
     */
    public const array PRODUCTS = [
        SafetyCatalogue::TRAINING_COURSES => [
            'Art. 37 _Corso di Formazione Generale e Specifica dei lavoratori' => ['price' => 50.0, 'supplier' => null, 'expiration_months' => 0],
            'Corso di Formazione per Addetto al Primo Soccorso Gruppo A' => ['price' => 130.0, 'supplier' => null, 'expiration_months' => 0],
            'Corso di Formazione per Preposti in Azienda' => ['price' => 150.0, 'supplier' => null, 'expiration_months' => 0],
            'Corso di Formazione per Addetto Montaggio, Uso e Smontaggio Ponteggi - PIMUS' => ['price' => 200.0, 'supplier' => null, 'expiration_months' => 0],
            'Corso di Formazione per conducente GRU' => ['price' => 180.0, 'supplier' => null, 'expiration_months' => 0],
            'Corso di Formazione per Addetto Conduzione Autocarro con Cassone Ribaltabile' => ['price' => 180.0, 'supplier' => null, 'expiration_months' => 0],
            'Corso di Formazione per Addetti Conduzione Carrelli Industriali Semoventi con conducente a bordo - MULETTO' => ['price' => 180.0, 'supplier' => null, 'expiration_months' => 0],
            'Corso di Formazione Addetti Conduzione Trattrici Agricole o Forestali a Ruote' => ['price' => 180.0, 'supplier' => null, 'expiration_months' => 0],
            'Corso di Formazione Addetti Conduzione Trattrici Agricole o Forestali a Cingoli' => ['price' => 180.0, 'supplier' => null, 'expiration_months' => 0],
            'Corso di Formazione Utilizzo Trabattelli' => ['price' => 180.0, 'supplier' => null, 'expiration_months' => 0],
            'Corso di Aggiornamento per RSPP/ASPP - Modulo B' => ['price' => 150.0, 'supplier' => null, 'expiration_months' => 0],
            'Corso di Formazione per Responsabile Servizio Prevenzione e Protezione Datore di Lavoro - RSPP/DL' => ['price' => 150.0, 'supplier' => null, 'expiration_months' => 0],
            'Corso di Formazione per RSPP/ASPP - Modulo A' => ['price' => 190.0, 'supplier' => null, 'expiration_months' => 0],
            'Corso di Formazione per RSPP/ASPP - Modulo B' => ['price' => 230.0, 'supplier' => null, 'expiration_months' => 0],
            'Corso di Formazione per RSPP - Modulo C' => ['price' => 250.0, 'supplier' => null, 'expiration_months' => 0],
            'Corso di Formazione Addetti Conduzione Piattaforme Elevabili con stabilizzatori e senza stabilizzatori - PLE' => ['price' => 180.0, 'supplier' => null, 'expiration_months' => 0],
            'Corso di Formazione Addetti Conduzione Escavatori Idraulici e Caricatori Frontali' => ['price' => 180.0, 'supplier' => null, 'expiration_months' => 0],
            'Corso di Aggiornamento Rappresentante Lavoratori Sicurezza inferiore a 50 dipendenti - RLS' => ['price' => 90.0, 'supplier' => null, 'expiration_months' => 0],
            'Corso di Aggiornamento Addetto Montaggio, Uso e Smontaggio Ponteggi - PIMUS' => ['price' => 150.0, 'supplier' => null, 'expiration_months' => 0],
            'Corso di Formazione PES - PAV - PEI' => ['price' => 200.0, 'supplier' => null, 'expiration_months' => 0],
            'Corso di Formazione Patentino Fgas (Compreso di codice PR)' => ['price' => 1200.0, 'supplier' => SafetyCatalogue::GONDOLA, 'expiration_months' => 0],
            'Corso di Formazione per Addetto Utilizzo Carroponte' => ['price' => 180.0, 'supplier' => null, 'expiration_months' => 0],
            'Corso di Formazione per primo ingresso in cantiere' => ['price' => 150.0, 'supplier' => null, 'expiration_months' => 0],
            'Corso di aggiornamento della Formazione per Addetto al Primo Soccorso Gruppo A' => ['price' => 90.0, 'supplier' => null, 'expiration_months' => 0],
            'AGGIORNAMENTO RSPP/DL' => ['price' => 150.0, 'supplier' => null, 'expiration_months' => 0],
            'Corso di Formazione per Caposquadra Cappotto Termico' => ['price' => 350.0, 'supplier' => null, 'expiration_months' => 0],
            'Corso di Formazione per Addetto Cappotto Termico' => ['price' => 300.0, 'supplier' => null, 'expiration_months' => 0],
            'Corso di Formazione per Preposti in Cantiere' => ['price' => 150.0, 'supplier' => null, 'expiration_months' => 0],
            'Corso di formazione Addetto Utilizzo di Macchine Movimento Terra - MMT' => ['price' => 180.0, 'supplier' => null, 'expiration_months' => 0],
            'Corso di Formazione Utilizzo DPI III Categoria - ORTOPROTETTORI' => ['price' => 180.0, 'supplier' => null, 'expiration_months' => 0],
            'Corso di Formazione per l\'utilizzo dei DPI di III Categoria e Lavori in Quota' => ['price' => 200.0, 'supplier' => null, 'expiration_months' => 0],
            'Corso per Datore di Lavoro/ Dirigente ai sensi Decreto Legge n. 146/2021 – art. 37 comma 7 del D.Lgs 81/08 – 16 ore' => ['price' => 120.0, 'supplier' => null, 'expiration_months' => 0],
            'PIMUS_ Redazione del Piano di Montaggio, Uso e Smontaggio dei Ponteggi' => ['price' => 300.0, 'supplier' => null, 'expiration_months' => 0],
            'Patentino Saldatori' => ['price' => 450.0, 'supplier' => SafetyCatalogue::ND_CONTROL, 'expiration_months' => 0],
            'Corso Alimentarista per personale adibito alla somministrazione' => ['price' => 55.0, 'supplier' => null, 'expiration_months' => 0],
            'Corso di Formazione per Addetti Utilizzo Decespugliatore' => ['price' => 180.0, 'supplier' => null, 'expiration_months' => 0],
            'Corso di Formazione per Addetto Utilizzo Motosega' => ['price' => 180.0, 'supplier' => null, 'expiration_months' => 0],
            'Corso di formazione per preposto alle attività di pianificazione controllo e apposizione della segnaletica stradale destinata alle attività lavorative che si svolgono in presenza di traffico' => ['price' => 180.0, 'supplier' => null, 'expiration_months' => 0],
            'Corso di Formazione Pest Management' => ['price' => 250.0, 'supplier' => null, 'expiration_months' => 0],
            'Iscrizione Portale F-gas e codice IR' => ['price' => 85.0, 'supplier' => SafetyCatalogue::GONDOLA, 'expiration_months' => 0],
            'Corso di Aggiornamento Formazione per Preposti in Azienda' => ['price' => 100.0, 'supplier' => null, 'expiration_months' => 0],
            'RSPP_ Incarico per Responsabile Servizio Prevenzione e Protezione Esterno' => ['price' => 0.0, 'supplier' => null, 'expiration_months' => 0],
            'Corso di Formazione Addetti Conduzione Escavatori Idraulici e Terne' => ['price' => 180.0, 'supplier' => null, 'expiration_months' => 0],
            'Corso di Formazione per Conducente Gru a Torre' => ['price' => 180.0, 'supplier' => null, 'expiration_months' => 0],
            'Corso di Aggiornamento Addetti alla Conduzione Macchine Movimento Terra - MMT' => ['price' => 150.0, 'supplier' => null, 'expiration_months' => 0],
            'CONTRATTO IN IN MATERIA DI SALUTE E SICUREZZA NEI LUOGHI DI LAVORO_MENSILE' => ['price' => 0.0, 'supplier' => null, 'expiration_months' => 0],
            'Corso di Formazione per Addetto all’Antincendio - Livello 1' => ['price' => 100.0, 'supplier' => null, 'expiration_months' => 0],
            'Corso di Aggiornamento per Addetto all’Antincendio - Livello 1' => ['price' => 75.0, 'supplier' => null, 'expiration_months' => 0],
            'Corso di Formazione per Addetto all’Antincendio - Livello 2' => ['price' => 130.0, 'supplier' => null, 'expiration_months' => 0],
            'Corso di Aggiornamento per Addetto all’Antincendio - Livello 2' => ['price' => 75.0, 'supplier' => null, 'expiration_months' => 0],
            'Corso di Formazione per Addetto al Primo Soccorso Gruppi B-C' => ['price' => 100.0, 'supplier' => null, 'expiration_months' => 0],
            'Corso di aggiornamento della Formazione per Addetto al Primo Soccorso Gruppi B-C' => ['price' => 90.0, 'supplier' => null, 'expiration_months' => 0],
            'Corso di Aggiornamento dei lavoratori' => ['price' => 45.0, 'supplier' => null, 'expiration_months' => 0],
            'Corso Alimentarista per personale non adibito alla somministrazione' => ['price' => 45.0, 'supplier' => null, 'expiration_months' => 0],
            'Corso Alimentarista per Responsabile Industria Alimentare - RIA' => ['price' => 75.0, 'supplier' => null, 'expiration_months' => 0],
            'Corso di Aggiornamento Rappresentante Lavoratori Sicurezza superiore a 50 dipendenti - RLS' => ['price' => 110.0, 'supplier' => null, 'expiration_months' => 0],
            'Corso di Formazione Generale e Specifica dei lavoratori Rischio Alto' => ['price' => 50.0, 'supplier' => null, 'expiration_months' => 0],
            'Corso di Aggiornamento Formazione per Preposti in Cantiere' => ['price' => 100.0, 'supplier' => null, 'expiration_months' => 0],
            'Procedure macchine da cantiere' => ['price' => 200.0, 'supplier' => null, 'expiration_months' => 0],
            'CONTRATTO IN IN MATERIA DI SALUTE E SICUREZZA NEI LUOGHI DI LAVORO_ANNUALE' => ['price' => 0.0, 'supplier' => null, 'expiration_months' => 0],
            'Corso di aggiornamento formazione per conducente GRU su Autocarro' => ['price' => 150.0, 'supplier' => null, 'expiration_months' => 0],
            'CORSO DI FORMAZIONE GRU SU AUTOCARRO (12 ORE)' => ['price' => 180.0, 'supplier' => null, 'expiration_months' => 0],
            'Formazione dei datori di lavoro ai sensi dell\'Accordo Stato Regioni del 17/04/2025 - durata 16 ore (RSPP/DAT.)' => ['price' => 120.0, 'supplier' => null, 'expiration_months' => 0],
            'Formazione dei datori di lavoro - settore edile - ai sensi dell\'Accordo Stato Regioni del 17/04/2025 - durata 16 ore + 6 specifica' => ['price' => 180.0, 'supplier' => null, 'expiration_months' => 0],
            'Corso di formazione teorico pratico per addetti all\'utilizzo di trattori agricoli forestali 8 ore (3 ore di teoria + 5 ore di pratica)' => ['price' => 180.0, 'supplier' => null, 'expiration_months' => 0],
            'Corso di aggiornamento per Responsabile Servizio Prevenzione e Protezione Datore di Lavoro - RSPP/DL' => ['price' => 150.0, 'supplier' => null, 'expiration_months' => 0],
            'CORSO DI FORMAZIONE SPECIFICO H2S E SUI DISPOSITIVI DI FUGA SEMI MASCHERA EMERGENZA DISPOSITIVO FUGA SCAPPA - SCAPPA' => ['price' => 100.0, 'supplier' => null, 'expiration_months' => 0],
            'CORSO FORMAZIONE RISCHIO SPECIFICO ESPOSIZIONE ALLA SO2' => ['price' => 100.0, 'supplier' => null, 'expiration_months' => 0],
            'Documento di Valutazione del rischio da radiazioni ottiche naturali (RON)' => ['price' => 250.0, 'supplier' => null, 'expiration_months' => 0],
            'Valutazione Rischio Sismico Standard' => ['price' => 250.0, 'supplier' => null, 'expiration_months' => 0],
            'Formazione dei datori di lavoro ai sensi dell\'Accordo Stato Regioni del 17/04/2025 - durata 16 ore (DL)' => ['price' => 150.0, 'supplier' => null, 'expiration_months' => 0],
            'Adeguamento documentale smart working – sicurezza sul lavoro' => ['price' => 200.0, 'supplier' => null, 'expiration_months' => 0],
            'Corso di aggiornamento Formazione Utilizzo Trabattelli' => ['price' => 100.0, 'supplier' => null, 'expiration_months' => 0],
            'Aggiornamento corso di formazione per l\'utilizzo dei DPI di III Categoria e Lavori in Quota' => ['price' => 150.0, 'supplier' => null, 'expiration_months' => 0],
            'AGG. Corso di Formazione PES - PAV - PEI' => ['price' => 200.0, 'supplier' => null, 'expiration_months' => 0],
            'CORSO DI FORMAZIONE GRU MOBILI' => ['price' => 200.0, 'supplier' => null, 'expiration_months' => 0],
            'CORSO DI AGGIORNAMENTO FORMAZIONE GRU MOBILI' => ['price' => 150.0, 'supplier' => null, 'expiration_months' => 0],
            'Corso per Tecnico dei controlli non distruttivi – Metodo Ultrasuoni (UT)' => ['price' => 0.0, 'supplier' => null, 'expiration_months' => 0],
            'Corso per Datore di Lavoro/ Dirigente ai sensi Decreto Legge n. 146/2021 – art. 37 comma 7 del D.Lgs 81/08 – 16 ore+ Modulo Cantiere 6 H' => ['price' => 200.0, 'supplier' => null, 'expiration_months' => 0],
            'Visita Medica -Carbossiemoglobina - COHb' => ['price' => 15.0, 'supplier' => null, 'expiration_months' => 0],
            'CORSO DI FORMAZIONE PER ADDETTI ALL’USO DI APPARECCHI DI SOLLEVAMENTO (8 ore)' => ['price' => 120.0, 'supplier' => null, 'expiration_months' => 0],
            'Piano di autocontrollo per la gestione del rischio legionella' => ['price' => 250.0, 'supplier' => null, 'expiration_months' => 0],
            'Aula di formazione generale e specifica' => ['price' => 600.0, 'supplier' => null, 'expiration_months' => null],
            'Aula di formazione antincendio' => ['price' => 800.0, 'supplier' => null, 'expiration_months' => null],
            'Aula di formazione Primo Soccorso' => ['price' => 800.0, 'supplier' => null, 'expiration_months' => null],
            'Aula di formazione per preposto' => ['price' => 700.0, 'supplier' => null, 'expiration_months' => null],
        ],
        SafetyCatalogue::DOCUMENTS => [
            'DVR_ Consulenza per predisposizione Documento di Valutazione dei Rischi' => ['price' => 300.0, 'supplier' => null, 'expiration_months' => 0],
            'STRESS_ Consulenza per predisposizione Documento di Valutazione Rischio Stress Lavoro Correlato' => ['price' => 200.0, 'supplier' => null, 'expiration_months' => 0],
            'ERGO_ Consulenza per predisposizione Documento di Valutazione Rischio Ergonomia' => ['price' => 200.0, 'supplier' => null, 'expiration_months' => 0],
            'VDT_ Consulenza per predisposizione Documento di Valutazione Rischio Videoterminale' => ['price' => 200.0, 'supplier' => null, 'expiration_months' => 0],
            'MICROCLIMA_Consulenza per predisposizione Documento di Valutazione Rischio Microclima' => ['price' => 200.0, 'supplier' => null, 'expiration_months' => 0],
            'POSTURE_ Consulenza per predisposizione Documento di Valutazione Rischio Posture Statiche' => ['price' => 200.0, 'supplier' => null, 'expiration_months' => 0],
            'SPECIFICI_ Consulenza per predisposizione dei Documenti di Valutazione dei Rischi Specifici' => ['price' => 300.0, 'supplier' => null, 'expiration_months' => 0],
            'CHIMICO_ Consulenza per predisposizione Documento di Valutazione Rischio Chimico' => ['price' => 200.0, 'supplier' => null, 'expiration_months' => 0],
            'BIOLOGICO_Consulenza per predisposizione Documento di Valutazione Rischio Biologico' => ['price' => 200.0, 'supplier' => null, 'expiration_months' => 0],
            'RUMORE_ Consulenza per predisposizione Documento di Valutazione Rischio Rumore' => ['price' => 200.0, 'supplier' => null, 'expiration_months' => 0],
            'CORPO INTERO_ Consulenza per predisposizione Documento di Valutazione Rischio Vibrazione corpo intero' => ['price' => 200.0, 'supplier' => null, 'expiration_months' => 0],
            'MANO BRACCIO_ Consulenza per predisposizione Documento di Valutazione Rischio Vibrazione mano braccio' => ['price' => 200.0, 'supplier' => null, 'expiration_months' => 0],
            'MOVIMENTAZIONE_Consulenza per predisposizione Documento di Valutazione Rischio Movimentazione manuale dei carichi' => ['price' => 200.0, 'supplier' => null, 'expiration_months' => 0],
            'PEE_ Consulenza Elaborazione Piano di Emergenza ed Evacuazione' => ['price' => 200.0, 'supplier' => null, 'expiration_months' => 0],
            'POS_ Redazione Piano Operativo di Sicurezza' => ['price' => 100.0, 'supplier' => null, 'expiration_months' => 0],
            'SIC _ Consulenza per predisposizione Documento di Valutazione dei Rischi (DVR) - D.Lgs. 81/08 - Prima Edizione' => ['price' => 300.0, 'supplier' => null, 'expiration_months' => 0],
            'DVR AGG_ Aggiornamento e revisione DVR e relativi allegati specifici' => ['price' => 180.0, 'supplier' => null, 'expiration_months' => 0],
            'SIC_PEE' => ['price' => 200.0, 'supplier' => null, 'expiration_months' => 0],
            'Qualifica dei processi di saldatura WPQR | Welding Procedure Qualification Record' => ['price' => 0.0, 'supplier' => SafetyCatalogue::ND_CONTROL, 'expiration_months' => 0],
            'Revisione Redazione Piano Operativo di Sicurezza - POS' => ['price' => 50.0, 'supplier' => null, 'expiration_months' => 0],
            'Qualifica dei processi di saldatura WPQR /WPS' => ['price' => 0.0, 'supplier' => SafetyCatalogue::ND_CONTROL, 'expiration_months' => 0],
            'Prove iniziali di tipo ITT' => ['price' => 650.0, 'supplier' => SafetyCatalogue::ND_CONTROL, 'expiration_months' => 0],
            'Modulo consegna DPI' => ['price' => 0.0, 'supplier' => null, 'expiration_months' => 0],
            'DUVRI_ Documento Valutazione Rischi da Interferenza' => ['price' => 200.0, 'supplier' => null, 'expiration_months' => 0],
            'HACCP_ Manuale di Autocontrollo e sistema HACCP con relative Schede di Gestione e Controllo' => ['price' => 450.0, 'supplier' => null, 'expiration_months' => 0],
            'PLANIMETRIA_ Consulenza per Redazione Planimetria Anticendio (il cliente deve fornire il DWG o PDF dei locali)' => ['price' => 150.0, 'supplier' => null, 'expiration_months' => 0],
            'Aggiornamento del DVR (Documento di Valutazione dei Rischi), inclusivo di: procedure specifiche, misure di prevenzione e protezione, analisi e valutazione dei DPI' => ['price' => 1000.0, 'supplier' => null, 'expiration_months' => 0],
            'Planimetria antincendio e relativo Piano di Emergenza (occorre la planimetria dei locali in pdf con layout macchinari)' => ['price' => 500.0, 'supplier' => null, 'expiration_months' => 0],
            'Corso di Aggiornamento Addetti Conduzione Piattaforme Elevabili con stabilizzatori e senza stabilizzatori - PLE' => ['price' => 150.0, 'supplier' => null, 'expiration_months' => 0],
        ],
        SafetyCatalogue::MEDICAL_VISITS => [
            'NO ESAMI_ Consulenza Sorveglianza Sanitaria - Visita Medica' => ['price' => 45.0, 'supplier' => null, 'expiration_months' => 0],
            'Nomina Medico Competente - MC' => ['price' => 100.0, 'supplier' => null, 'expiration_months' => 0],
            'ESAMI_ Consulenza Sorveglianza Sanitaria - Visita Medica con Esami Ematici' => ['price' => 65.0, 'supplier' => null, 'expiration_months' => 0],
            'ALCOOL_ Consulenza Sorveglianza Sanitaria - Visita Medica con Esami Ematici ed Alcool Test' => ['price' => 75.0, 'supplier' => null, 'expiration_months' => 0],
            'DRUG_ Consulenza Sorveglianza Sanitaria - Visita Medica con Esami Ematici, Alcool Test e Drug Test' => ['price' => 85.0, 'supplier' => null, 'expiration_months' => 0],
            'Intregrazione esami ematici- visite mediche' => ['price' => 0.0, 'supplier' => null, 'expiration_months' => 20],
        ],
        SafetyCatalogue::SITE_INSPECTIONS => [
            'SOPRALLUOGO_ Consulenza in materia di sicurezza nei luoghi di lavoro e sopralluogo' => ['price' => 500.0, 'supplier' => null, 'expiration_months' => 0],
            'n.12 Campioni su matrici alimentari (1 al mese)' => ['price' => 0.0, 'supplier' => null, 'expiration_months' => 0],
            'N°1 Analisi Acqua potabile' => ['price' => 0.0, 'supplier' => null, 'expiration_months' => 0],
            'Tamponi su superfici alimentari' => ['price' => 45.0, 'supplier' => null, 'expiration_months' => 0],
            'Campioni su matrici alimentari' => ['price' => 50.0, 'supplier' => null, 'expiration_months' => 0],
        ],
        SafetyCatalogue::TRAVEL => [
            'Spese di trasferta Sorveglianza Sanitaria' => ['price' => 150.0, 'supplier' => null, 'expiration_months' => 0],
        ],
    ];
}
