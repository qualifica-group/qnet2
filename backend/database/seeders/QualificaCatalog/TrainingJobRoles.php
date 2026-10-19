<?php

namespace Database\Seeders\QualificaCatalog;

/**
 * Job roles of the training, labour and funding areas: Accreditamenti, APL,
 * Formazione, Finanza Agevolata, Fondi Interprofessionali, Istruzione,
 * Progettazione. Format: see JobRoleCatalogue.
 */
final class TrainingJobRoles
{
    /**
     * What every active-labour-policy operator does; the supervisor and the
     * territorial referent carry it too (the sheet: "svolgimento di tutte le
     * attivita' previste per l'Operatore Politiche Attive").
     *
     * @var array<int|string, string>
     */
    private const array ACTIVE_POLICY_OPERATOR_ACTIVITIES = [
        'Accoglienza degli utenti',
        'Orientamento e accompagnamento al lavoro' => 'Valutazione del profilo professionale, individuazione di percorsi formativi e lavorativi, supporto nella scelta e nella ricerca di occupazione.',
        'Gestione delle pratiche sui portali per le politiche attive del lavoro' => 'DID, curriculum vitae, profilazione quantitativa e tracking delle pratiche.',
        'Gestione di progetti e bandi GOL, regionali e nazionali' => 'Verifica dei requisiti e gestione della documentazione.',
        'Organizzazione di percorsi formativi e accompagnamento a corsi, tirocini e stage' => 'Compresa la riqualificazione professionale.',
        'Gestione dei dati e dei report degli utenti' => 'Registrazione e aggiornamento dei dati anagrafici e professionali, elaborazione di report di attività e risultati.',
        'Rendicontazione delle attività di politiche attive',
    ];

    /** @var array<string, array{description: string, activities: array<int|string, string>}> */
    public const array ROLES = [
        'Responsabile Accreditamenti' => [
            'description' => 'Coordinamento e gestione delle attività relative agli accreditamenti e della relativa parte tecnica.',
            'activities' => [
                "Coordinamento generale dell'ufficio accreditamenti",
                'Valutazione architettonica delle unità immobiliari (F2)',
                'Verifica e validazione della documentazione strutturale dei partner',
                'Redazione e presentazione della documentazione tecnica delle sedi dirette',
                "Confronto con i partner sull'avanzamento delle sedi e gestione post-accreditamento",
                'Richiesta della documentazione di sicurezza D.Lgs. 81/08',
                'Sopralluoghi in cantiere',
                'Ricerca di nuove unità immobiliari da accreditare',
                'Gestione delle sedi accreditate e delle risorse da assumere' => "Compreso l'aggiornamento del file anagrafica sedi.",
            ],
        ],
        'Referente Normativo' => [
            'description' => 'Gestione e analisi delle procedure e dei requisiti di accreditamento, monitoraggio e controllo degli aggiornamenti normativi.',
            'activities' => [
                'Analisi delle procedure e dei requisiti di accreditamento',
                'Verifica, validazione e invio in piattaforma della documentazione di accreditamento',
                'Gestione della documentazione di accreditamento',
                'Gestione di integrazioni, aggiornamenti e mantenimenti degli accreditamenti',
                'Partecipazione agli audit regionali',
                "Referente normativo per l'accreditamento e interlocutore con le Regioni",
            ],
        ],
        'Referente Legale' => [
            'description' => 'Redazione, valutazione e controllo degli aspetti contrattuali relativi agli immobili da accreditare.',
            'activities' => [
                'Valutazione e controllo degli aspetti contrattuali in essere',
                'Redazione dei contratti per gli immobili da accreditare' => "Locazione, sublocazione, comodato d'uso e scrittura privata.",
                'Gestione dei rapporti contrattuali con i partner',
                'Supporto ai partner nella valutazione di idoneità dei contratti con il locatore',
            ],
        ],
        'Operativo Ufficio Accreditamenti' => [
            'description' => 'Gestione operativa delle attività relative agli accreditamenti delle sedi.',
            'activities' => [
                "Confronto con i partner sull'avanzamento delle sedi e gestione post-accreditamento",
                'Preparazione della documentazione di accreditamento e di arredamento della sede' => "Documentazione di accreditamento da inoltrare in piattaforma e documentazione per l'arredamento della sede.",
                "Richiesta di attivazione della polizza assicurativa e richiesta ISO per l'accreditamento",
                'Gestione degli acquisti per le sedi dirette e supporto a quelli delle sedi indirette',
                'Richiesta al partner dei dati marketing della sede',
                'Preparazione dei faldoni sede e archiviazione dei file in Drive',
                'Gestione dei contratti degli immobili e richiesta di inserimento in Visura' => "Contratti di locazione, sublocazione, comodato d'uso e scrittura privata.",
                'Gestione delle sedi accreditate e delle risorse da assumere',
                "Richiesta di inserimento dell'opportunità e creazione della commessa",
            ],
        ],
        'Responsabile APL' => [
            'description' => 'Responsabile del settore Agenzia per il Lavoro: gestione delle risorse, andamento delle attività e sviluppo dei servizi APL.',
            'activities' => [
                'Coordinamento e gestione delle risorse del settore, con verifica di presenze e assenze',
                'Monitoraggio delle attività, delle pratiche e dei risultati',
                'Formazione e crescita professionale del team',
                'Promozione dei servizi APL e sviluppo commerciale del settore',
                'Definizione delle strategie e degli obiettivi di settore',
            ],
        ],
        'Referente Territoriale Politiche Attive' => [
            'description' => 'Referente territoriale delle politiche attive per la regione di competenza.',
            'activities' => [
                ...self::ACTIVE_POLICY_OPERATOR_ACTIVITIES,
                'Coordinamento e supervisione degli operatori delle politiche attive',
                'Gestione e assegnazione dei carichi di lavoro',
                'Monitoraggio delle attività, delle pratiche e dei risultati',
                'Presidio dei rapporti con enti e bandi regionali del territorio',
            ],
        ],
        'Supervisor Operatori Politiche Attive' => [
            'description' => "Coordina il team degli operatori delle politiche attive e ne monitora l'attività.",
            'activities' => [
                ...self::ACTIVE_POLICY_OPERATOR_ACTIVITIES,
                'Coordinamento e supervisione degli operatori delle politiche attive',
                'Gestione e assegnazione dei carichi di lavoro',
                'Monitoraggio delle attività, delle pratiche e dei risultati',
            ],
        ],
        'Operatore Politiche Attive' => [
            'description' => "Accompagna e supporta persone in cerca di occupazione o in fase di reinserimento, con gli strumenti e le misure delle politiche attive, per favorire l'inserimento lavorativo.",
            'activities' => [
                ...self::ACTIVE_POLICY_OPERATOR_ACTIVITIES,
                'Supporto alle attività del settore Formazione',
            ],
        ],
        'Addetto Ricerca e Selezione' => [
            'description' => 'Ricerca, selezione e presentazione dei candidati alle aziende utilizzatrici.',
            'activities' => [
                "Analisi delle esigenze dell'azienda cliente e definizione del profilo ideale",
                'Ricerca di profili idonei in database e banche dati di candidati',
                'Preselezione e colloqui con i candidati',
                'Valutazione di competenze, esperienza e adattamento al ruolo dei candidati',
                "Presentazione dei candidati all'azienda utilizzatrice" => "Presentazione dei candidati più idonei all'azienda utilizzatrice, con eventuale proposta di inserimento.",
            ],
        ],
        'Responsabile Formazione' => [
            'description' => 'Responsabile della progettazione, presentazione e coordinamento del servizio formativo.',
            'activities' => [
                "Presidio dell'analisi del fabbisogno formativo secondo il modello interno",
                'Macro-progettazione degli eventi formativi e redazione del piano formativo' => 'Con applicazione del piano formativo e coordinamento delle sue fasi.',
                'Validazione dei progetti formativi elaborati dai Project Leader',
                'Registrazione della documentazione di accreditamento dei progetti formativi' => "Sul sito dell'ente accreditante.",
                'Supervisione e coordinamento del personale della formazione',
                'Promozione di metodologie e strumenti formativi innovativi' => "Per il raggiungimento degli obiettivi formativi, favorendo l'utilizzo della piattaforma FAD.",
                'Monitoraggio e riesame periodico della qualità della formazione' => "Individuazione di modalità e risorse per monitorare, migliorare e riesaminare la qualità della formazione; verifica dell'applicazione dei regolamenti in materia di conflitto di interesse.",
                'Monitoraggio e validazione dei budget dei singoli eventi formativi',
                "Coordinamento di statistiche, reportistica e relazione annuale per l'Ente Accreditante" => 'Elaborazione dei dati sugli interventi formativi effettuati (numero di iniziative e qualità).',
            ],
        ],
        'Coordinatore Segreteria Didattica' => [
            'description' => 'Coordinamento della segreteria didattica e gestione della produttività della sede; interfaccia diretta con i supervisor delle attività formative.',
            'activities' => [
                'Coordinamento della segreteria didattica e gestione della produttività della sede',
                'Verifica della presa in carico di tutti i corsi previsti in obiettivo',
                'Organizzazione delle attività dei supervisor',
                "Monitoraggio dell'andamento della sede",
            ],
        ],
        'Supervisor Attività Formative' => [
            'description' => 'Agisce sulle risorse operative: coordinamento quotidiano dei percorsi e degli eventi formativi assegnati.',
            'activities' => [
                'Assegnazione delle attività quotidiane alle risorse operative' => 'Comprese quelle delle sedi territoriali e partner.',
                'Conferimento degli incarichi ai docenti e coordinamento della loro attività' => 'Sui singoli progetti formativi.',
                'Controllo della documentazione dei percorsi formativi',
                'Coordinamento operativo dei percorsi e degli eventi formativi assegnati',
                "Interfaccia con il Coordinatore Segreteria Didattica sull'avanzamento delle attività",
            ],
        ],
        'Segreteria Didattica' => [
            'description' => "Esegue tutte le attività di segreteria e supporta tutti i responsabili aziendali; gestione amministrativa e documentale dei corsi, dalle iscrizioni all'archiviazione.",
            'activities' => [
                'Organizzazione e coordinamento di corsi, tirocini, progetti di formazione e attività didattiche' => 'Attività didattiche curricolari ed extracurricolari.',
                'Amministrazione delle attività didattiche' => 'Registri, scadenze, protocollo, corrispondenza in entrata e in uscita, posta elettronica e telefonate.',
                'Formalizzazione dei primi contatti e delle iscrizioni',
                'Informazioni a docenti e studenti su corsi, ammissione, piani di studio e orientamento',
                'Formalizzazione dei contratti con i docenti',
                "Predisposizione della documentazione per l'erogazione degli eventi formativi",
                'Supporto a tutti i responsabili aziendali',
                'Archiviazione dei dati cartacei e digitali dei corsi',
                'Supporto alle attività commerciali',
                'Supporto alle attività APL',
            ],
        ],
        'Responsabile Finanza Agevolata' => [
            'description' => "Gestisce e coordina l'intero reparto, garantendo efficienza, rispetto delle normative e qualità del servizio; responsabile del monitoraggio e del raggiungimento degli obiettivi di settore.",
            'activities' => [
                'Selezione delle risorse del reparto' => "Valutazione dei CV e colloqui, in accordo con l'ufficio del personale e la direzione.",
                'Pianificazione strategica e operativa delle attività del reparto',
                'Formazione e affiancamento dei tecnici' => 'Nelle prime attività e nelle lavorazioni complesse.',
                'Supervisione delle lavorazioni' => 'Con controllo di qualità e conformità normativa.',
                'Interfaccia nelle comunicazioni tra i settori tecnici' => 'Anche con clienti e partner.',
                'Reporting e analisi dei KPI di reparto' => 'Tasso di approvazione delle domande, tempi di erogazione, soddisfazione del cliente.',
                'Gestione del processo burocratico e delle relazioni con gli enti finanziatori',
                'Sviluppo di networking e collaborazioni strategiche',
            ],
        ],
        'Consulente Finanza Agevolata' => [
            'description' => 'Consulenza su bandi e finanziamenti agevolati, dalla fattibilità alla rendicontazione.',
            'activities' => [
                'Gestione della rendicontazione post-approvazione',
                'Predisposizione della documentazione tecnica e amministrativa',
                'Consulenza di fattibilità per le aziende clienti',
                'Monitoraggio e analisi di bandi e opportunità di finanziamento',
                'Sviluppo di progetti e proposte progettuali per bandi',
                'Analisi normativa e dei requisiti tecnici' => 'Con aggiornamento dei requisiti.',
            ],
        ],
        'Supporto Operativo Bandi' => [
            'description' => 'Supporto operativo e documentale alle attività progettuali e di rendicontazione.',
            'activities' => [
                'Analisi preliminare di requisiti e documentazione',
                'Supporto alla rendicontazione dei progetti',
                'Supporto operativo alle attività progettuali',
                'Aggiornamento della documentazione di settore',
            ],
        ],
        'Responsabile Fondi Interprofessionali' => [
            'description' => "Responsabile del settore Fondi Interprofessionali: gestisce il rapporto con i fondi, l'accreditamento e il supporto alla rete commerciale.",
            'activities' => [
                'Gestione complessiva delle attività del settore Fondi Interprofessionali',
                'Gestione dei rapporti con gli enti e i fondi interprofessionali',
                "Gestione dell'iter di accreditamento ai fondi interprofessionali" => 'Con valutazione della documentazione.',
                'Formazione della rete commerciale sulla vendita dei servizi legati ai fondi',
            ],
        ],
        'Responsabile Istruzione' => [
            'description' => 'Coordina il settore istruzione e sviluppa progetti e collaborazioni con scuole e partner.',
            'activities' => [
                'Coordinamento delle attività del settore istruzione',
                'Monitoraggio e analisi di bandi e opportunità di finanziamento',
                "Sviluppo di progetti per l'azienda e per partner esterni",
                'Gestione delle relazioni con partner e stakeholder',
                'Sviluppo di networking e collaborazioni strategiche',
                'Consulenza e supporto alle scuole',
            ],
        ],
        'Consulente Istruzione' => [
            'description' => 'Progettazione e consulenza in ambito istruzione.',
            'activities' => [
                'Progettazione in ambito istruzione',
                'Consulenza in ambito istruzione',
            ],
        ],
        'Responsabile Progettazione' => [
            'description' => "Coordina le attività di progettazione e ne monitora l'avanzamento.",
            'activities' => [
                'Coordinamento e supervisione delle attività di progettazione',
                'Supporto nella pianificazione strategica dei progetti',
                'Sviluppo di networking e collaborazioni strategiche',
                "Monitoraggio dell'avanzamento dei progetti",
            ],
        ],
        'Addetto Progettazione' => [
            'description' => 'Elaborazione di proposte progettuali e cataloghi formativi per bandi e clienti.',
            'activities' => [
                'Analisi normativa e dei requisiti tecnici',
                'Sviluppo di progetti e proposte progettuali per bandi',
                'Sviluppo e gestione dei cataloghi formativi',
            ],
        ],
    ];
}
