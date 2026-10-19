<?php

namespace Database\Seeders\QualificaCatalog;

/**
 * Job roles of the commercial areas: Commerciale, Marketing, Unit Travel.
 * Format: see JobRoleCatalogue.
 */
final class CommercialJobRoles
{
    /** @var array<string, array{description: string, activities: array<int|string, string>}> */
    public const array ROLES = [
        'Responsabile Commerciale' => [
            'description' => 'Responsabile della definizione e implementazione delle politiche commerciali. Coordina e motiva il team, trasmette le competenze di vendita e gestisce la rete interna ed esterna.',
            'activities' => [
                'Gestione dei rapporti con agenti, partner e fornitori',
                'Coordinamento delle aperture sedi e sviluppo delle partnership territoriali',
                'Definizione delle strategie commerciali e di vendita',
                'Analisi di mercato e dei competitor',
                'Coordinamento e gestione del team commerciale',
                'Gestione del budget marketing e lancio di nuovi servizi e settori',
                'Supervisione delle provvigioni e verifica dei risultati commerciali',
                'Monitoraggio di KPI e performance commerciali',
                'Supervisione delle trattative commerciali e sviluppo dei clienti',
                'Pianificazione di campagne commerciali, eventi e attività promozionali',
            ],
        ],
        'Supervisor Commerciale Sedi Territoriali' => [
            'description' => 'Supporto e supervisione per gli aspetti commerciali alle risorse presenti nelle sedi e ai segnalatori; formazione delle nuove risorse; gestione delle convenzioni aziende.',
            'activities' => [
                'Supporto e supervisione commerciale ai referenti delle sedi territoriali',
                'Gestione dei gruppi WhatsApp di sedi e segnalatori' => 'Supporto a sedi e segnalatori su utenti GOL, CAF e convenzioni aziende.',
                'Informativa alle sedi su aggiornamenti normativi e comunicazioni importanti',
                'Raccolta degli aggiornamenti giornalieri e cura delle FAQ regionali' => 'Con invio settimanale nei gruppi WhatsApp.',
                'Redazione e aggiornamento delle procedure di settore e dei messaggi pre-impostati' => 'Procedure su convenzioni aziende, utenti GOL/SFL/ADI e convenzioni CAF, pubblicate in Area Centrale e trasmesse alle sedi di riferimento.',
                'Trasmissione delle segnalazioni ai reparti e raccolta dei report delle sedi' => 'Segnalazioni relative ai CPI e ad altre problematiche; report settimanali e mensili delle singole sedi.',
                'Formazione commerciale delle nuove risorse delle sedi' => 'Formazione teorica su GOL/SFL/ADI, convenzioni aziende e rapporti CAF, in presenza o da remoto.',
                'Onboarding operativo delle nuove risorse delle sedi' => 'Creazione e condivisione dei fogli Smartsheet per sedi e segnalatori, impostazione di mail, firma e Smartsheet sul PC aziendale.',
                'Gestione del database delle aziende convenzionate' => 'Ricerca, caricamento e contatti telefonici, in Campania e nelle altre regioni.',
            ],
        ],
        'Consulente Commerciale Senior' => [
            'description' => 'Gestione autonoma di clienti e trattative complesse, acquisizione di nuovi clienti e affiancamento ai consulenti junior.',
            'activities' => [
                'Predisposizione e negoziazione delle offerte economiche' => 'Garantendo il raggiungimento degli obiettivi commerciali assegnati.',
                "Analisi periodica dell'andamento commerciale e reporting" => 'Reporting al Coordinatore Commerciale, con proposta di azioni correttive e migliorative.',
                'Gestione e sviluppo del portafoglio clienti assegnato' => 'Fidelizzazione, cross selling e upselling.',
                'Consulenza specialistica sui servizi aziendali alle aziende clienti',
                'Affiancamento e supporto ai consulenti commerciali junior' => 'Favorendone la crescita professionale e il trasferimento delle competenze sui servizi aziendali.',
                'Gestione delle trattative commerciali complesse' => 'Dalla fase di analisi del fabbisogno fino alla chiusura della vendita.',
                'Monitoraggio degli incassi, solleciti e recupero crediti',
                'Gestione delle relazioni con segnalatori e partner commerciali' => 'Supporto e aggiornamento sulle opportunità commerciali, anche con i referenti territoriali, per incrementare le opportunità di business.',
            ],
        ],
        'Consulente Commerciale Junior' => [
            'description' => 'Gestione dei contatti assegnati lungo tutto il processo di vendita, dal primo contatto al post-vendita.',
            'activities' => [
                'Gestione delle relazioni con segnalatori e partner commerciali',
                'Gestione dei contatti commerciali' => 'Contatti provenienti da campagne marketing, richieste spontanee e attività promozionali aziendali.',
                'Monitoraggio degli incassi, solleciti e recupero crediti',
                'Verifica della soddisfazione del cliente e follow-up post-vendita',
                'Presentazione e consulenza sui servizi assegnati' => 'Analisi preliminare delle esigenze del cliente e proposta delle soluzioni più idonee.',
                "Gestione del processo di vendita fino all'offerta commerciale" => "Dalla presa di contatto alla predisposizione e trasmissione dell'offerta commerciale.",
                'Organizzazione e gestione degli appuntamenti commerciali' => 'Telefonici, in presenza o da remoto.',
                'Partecipazione alla formazione commerciale e tecnica sui servizi aziendali',
                'Informazioni sui corsi a discenti e utenti interessati',
                'Promozione dei corsi',
            ],
        ],
        'Consulente Commerciale' => [
            'description' => 'Gestione dei contatti assegnati lungo tutto il processo di vendita, dal primo contatto al post-vendita.',
            'activities' => [
                'Gestione delle relazioni con segnalatori e partner commerciali',
                'Gestione dei contatti commerciali',
                'Monitoraggio degli incassi, solleciti e recupero crediti',
                'Verifica della soddisfazione del cliente e follow-up post-vendita',
                'Presentazione e consulenza sui servizi assegnati',
                "Gestione del processo di vendita fino all'offerta commerciale",
                'Organizzazione e gestione degli appuntamenti commerciali',
                'Partecipazione alla formazione commerciale e tecnica sui servizi aziendali',
            ],
        ],
        'Supervisor Back Office Commerciale' => [
            'description' => 'Supporto quotidiano alle attività delle assistenti back-office; supervisione della qualità delle offerte e delle procedure commerciali.',
            'activities' => [
                "Coordinamento e supervisione dell'assistenza commerciale",
                'Supporto nella gestione di fondi, bandi e finanziamenti agevolati',
                'Aggiornamento e miglioramento delle procedure operative commerciali',
                'Controllo qualità delle offerte economiche e delle pratiche commerciali',
                'Monitoraggio delle attività e delle performance del back-office commerciale',
                'Gestione delle trattative commerciali assegnate',
                'Verifica della corretta evasione delle richieste e delle procedure interne',
                'Coordinamento dei rapporti con fornitori, partner e reparti aziendali',
            ],
        ],
        'Back Office Commerciale' => [
            'description' => 'Gestione amministrativa dei documenti del settore commerciale, supporto ai commerciali e data entry; assistenza su MePa, ANAC, Infogare, avvalimenti, offerte tecniche e amministrative.',
            'activities' => [
                'Supporto operativo ai commerciali interni ed esterni',
                'Gestione di front office, centralino e corrispondenza',
                'Gestione della documentazione commerciale, dei contratti e archiviazione',
                'Assegnazione e monitoraggio delle pratiche tramite il CRM aziendale',
                'Predisposizione di offerte commerciali e preventivi per i servizi aziendali',
                'Supporto amministrativo ai settori tecnici e alla contabilità',
                'Gestione delle pratiche relative a gare, avvalimenti e scadenziari',
                'Supporto operativo ai progetti e alle attività di sviluppo aziendale',
            ],
        ],
        'Responsabile Marketing' => [
            'description' => 'Gestione della comunicazione e delle strategie di marketing.',
            'activities' => [
                'Supervisione della produzione di materiale grafico e promozionale',
                'Gestione e monitoraggio dei canali social aziendali',
                'Gestione dei rapporti con fornitori e tipografie per i materiali di stampa',
                'Gestione delle recensioni online e delle schede Google Business Profile',
                'Coordinamento delle campagne pubblicitarie digitali',
                'Coordinamento e supervisione delle attività marketing',
                'Coordinamento delle attività per eventi, fiere e allestimento sedi',
                'Pianificazione delle strategie di comunicazione e promozione',
            ],
        ],
        'Graphic Designer' => [
            'description' => 'Ideazione e produzione dei materiali grafici aziendali: stampati e materiali istituzionali, allestimenti e segnaletica delle sedi, contenuti visivi per social, web e campagne pubblicitarie.',
            'activities' => [
                'Creazione di attestati, manuali, cartelline e materiali istituzionali',
                'Realizzazione di roll-up, vetrofanie, targhe e cartellonistica',
                'Produzione di materiali grafici per partner e clienti',
                'Progettazione della segnaletica interna ed esterna delle sedi',
                'Realizzazione di contenuti grafici per social media, siti web e campagne pubblicitarie',
                'Produzione e post-produzione di immagini, video e contenuti motion',
                'Creazione di brochure, flyer, volantini e cataloghi',
                'Realizzazione di banner pubblicitari e materiale promozionale',
                'Supporto grafico alle attività di comunicazione aziendale',
            ],
        ],
        'Social Media Manager Junior' => [
            'description' => 'Collaborazione e supporto alle attività del reparto marketing: produzione grafica e redazione contenuti.',
            'activities' => [
                'Gestione operativa dei canali social aziendali',
                'Redazione del calendario editoriale',
                'Creazione di contenuti testuali per social e newsletter',
                'Gestione di messaggi, commenti e interazioni con gli utenti',
                'Aggiornamento dei contatti e del database marketing',
                'Invio delle richieste di recensione e supporto alle attività di engagement',
                'Supporto alla pubblicazione di annunci e campagne digitali',
                'Programmazione e pubblicazione di post e stories',
            ],
        ],
        'Web Master / SEO Specialist' => [
            'description' => 'Sviluppo, gestione e ottimizzazione dei siti web aziendali.',
            'activities' => [
                'Monitoraggio della funzionalità e della sicurezza dei siti web',
                'Analisi di keyword e backlink e attività di link building',
                'Analisi dei dati tramite Google Analytics e Google Search Console',
                'Sviluppo e gestione dei siti web aziendali',
                'Gestione di hosting, domini e webmail aziendali',
                'Aggiornamento dei contenuti e gestione dei CMS',
                'Ottimizzazione SEO e posizionamento sui motori di ricerca',
                'Progettazione e gestione di landing page',
            ],
        ],
        'Direttore Tecnico – Responsabile Unit Travel' => [
            'description' => "Coordina e supervisiona l'intera Unit Travel, assicurandone la gestione tecnica, organizzativa e operativa nei settori Educational Travel e Corporate & Business Travel.",
            'activities' => [
                'Direzione tecnica, coordinamento e supervisione della Unit Travel',
                'Pianificazione, sviluppo e gestione dei servizi e dei progetti travel',
                'Coordinamento delle attività di Educational Travel' => "Progettazione dei programmi per scuole, enti formativi, studenti, docenti e partner: viaggi d'istruzione, PCTO, Erasmus+ e progetti di mobilità nazionale e internazionale.",
                'Coordinamento delle attività di Corporate & Business Travel' => 'Per aziende e organizzazioni: trasferte aziendali, fiere, congressi, meeting, convention, incentive, team building, viaggi premio ed esperienze corporate.',
                "Supervisione dell'organizzazione logistica e degli itinerari dei servizi travel" => 'Voli, treni, transfer, hotel, hospitality, autonoleggi e servizi accessori; itinerari e soluzioni personalizzate in funzione di destinazione, budget, numero di partecipanti ed esigenze del cliente.',
                'Gestione dei rapporti con clienti e fornitori travel e negoziazione delle tariffe' => 'Clienti, scuole, aziende ed enti; tour operator, agenzie, vettori, strutture ricettive e altri fornitori; ricerca e negoziazione di tariffe, convenzioni e condizioni commerciali.',
                'Supervisione di documentazione, sicurezza e assistenza ai viaggiatori' => 'Documentazione di viaggio, requisiti di ingresso, assicurazioni e sicurezza dei viaggiatori; assistenza operativa prima, durante e dopo il viaggio, comprese modifiche, cancellazioni ed emergenze.',
                'Definizione e applicazione delle Travel Policy per la clientela corporate',
                'Monitoraggio dei costi e della sostenibilità economica dei servizi travel' => 'Con individuazione di soluzioni di ottimizzazione.',
                'Coordinamento delle risorse della Unit Travel e sviluppo di procedure organizzative' => "Sviluppo di procedure e strumenti per l'efficienza organizzativa.",
            ],
        ],
        'Travel Assistant' => [
            'description' => 'Supporta il Responsabile della Unit nella gestione operativa dei servizi Educational Travel e Corporate & Business Travel.',
            'activities' => [
                "Supporto all'organizzazione e alla gestione operativa di viaggi, trasferte e progetti di mobilità",
                'Ricerca e prenotazione di voli, treni, hotel, autonoleggi, transfer e altri servizi di viaggio' => 'Individuando le soluzioni più adeguate per costi, tempi, destinazione ed esigenze del cliente.',
                'Preparazione e gestione degli itinerari e della documentazione di viaggio' => 'Con verifica dei documenti necessari ove previsto.',
                'Supporto operativo ai programmi di Educational Travel' => "Viaggi d'istruzione, PCTO, Erasmus+ e mobilità educativa; pianificazione di attività formative, visite culturali ed escursioni collegate.",
                'Supporto operativo al Corporate & Business Travel' => 'Trasferte aziendali, fiere, congressi, meeting, convention, incentive, team building ed esperienze corporate.',
                'Gestione operativa dei rapporti con i fornitori travel' => 'Agenzie, compagnie aeree, strutture ricettive, vettori, tour operator e altri fornitori.',
                'Gestione di modifiche, cancellazioni, rimborsi, riprotezioni e variazioni dei servizi prenotati',
                'Assistenza ai viaggiatori e ai gruppi prima, durante e dopo il viaggio',
                'Raccolta della documentazione dei servizi travel e reportistica' => 'Raccolta e organizzazione della documentazione, aggiornamento dei dati su viaggi e trasferte, predisposizione di report e riepiloghi.',
                'Supporto amministrativo su fatturazione, rendicontazione e controllo dei costi travel' => 'Ove previsto, con supporto al Responsabile della Unit nelle attività organizzative.',
            ],
        ],
    ];
}
