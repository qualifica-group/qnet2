import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'request-statistics',
  title: 'Statistiche Gestione Richieste',
  summary:
    'Statistiche Gestione Richieste mostra l\'andamento del lavoro sulle richieste in un periodo scelto, con indicatori e confronto sul periodo precedente, mappa per categoria, classifica operatori ed esportazione CSV/Excel.',
  sections: [
    {
      id: 'statistics-access',
      title: 'Chi può usare le statistiche',
      blocks: [
        {
          type: 'paragraph',
          text: 'Le statistiche mostrano l\'andamento del lavoro sulle richieste in un periodo scelto, a schermo o in un file CSV/Excel. Pagina e file usano gli stessi filtri e gli stessi calcoli, quindi i numeri coincidono.',
        },
        {
          type: 'paragraph',
          text: 'I numeri delle statistiche a schermo si aggiornano entro pochi secondi dalle modifiche degli altri utenti; il file CSV è invece sempre calcolato al momento in cui lo generi.',
        },
        {
          type: 'paragraph',
          text: "**GA2** è il Gestore account di livello 2, cioè l'operatore che lavora la richiesta. Nelle statistiche ogni valore per operatore si riferisce al GA2 attuale della richiesta, non a chi ha materialmente eseguito l'operazione.",
        },
        {
          type: 'list',
          items: [
            "Serve il permesso **Statistiche Gestione Richieste › Visualizza**, che si assegna in **Amministrazione › Ruoli**: senza, la voce di menu non compare e la pagina mostra l'accesso negato.",
            "Ognuno vede solo le richieste che vede già nell'elenco: con **Visualizza tutti** tutte; altrimenti quelle di cui è GA2, più quelle delle proprie sedi con **Visualizza per sede**.",
            'I filtri possono solo restringere questa visibilità, mai allargarla.',
          ],
        },
      ],
    },
    {
      id: 'statistics-panel',
      title: 'Leggere la pagina delle statistiche',
      blocks: [
        {
          type: 'paragraph',
          text: 'Apri **Opportunità e Commesse › Statistiche Gestione Richieste**: la pagina mostra subito le statistiche, non serve aprirle.',
        },
        {
          type: 'note',
          text: "Il browser ricorda gli ultimi filtri applicati e l'ultima scheda aperta.",
        },
        {
          type: 'table',
          headers: ['Parte', 'Contenuto'],
          rows: [
            [
              'Periodo',
              'In alto a sinistra: **Oggi**, **Ieri**, **Ultimi 7 giorni** (oggi compreso), **Questo mese** (dal giorno 1 a oggi), **Mese scorso** e **Tutto** (nessun limite di date) si applicano con un clic e cambiano solo le date. **Personalizzato** apre il pannello Filtri per scegliere date qualsiasi ed è evidenziato quando le date applicate non corrispondono a nessuna scelta rapida.',
            ],
            ['Genera report e Filtri', 'In alto a destra: il file CSV/Excel e il pannello con tutti gli altri filtri.'],
            [
              'Filtri applicati',
              'Un riquadro per Periodo, Categorie, Sedi, Operatori, Righe: colorato se restringe i dati, neutro se vale "tutto".',
            ],
            ['Schede', "**Panoramica**, poi le categorie selezionate che stanno nella riga; le altre sono nel menu **Altre (N)**, con la ricerca. La categoria aperta resta sempre visibile nella riga."],
          ],
        },
        {
          type: 'table',
          headers: ['Scheda', 'Contenuto'],
          rows: [
            [
              'Panoramica › Totale complessivo',
              "Una casella per colonna, su tutte le categorie selezionate insieme: una richiesta presente in più categorie conta una volta sola. Sotto il numero una barra colorata mostra quanto pesa ogni categoria (passa il mouse per nome, valore e percentuale); la ripartizione è calcolata sulla somma delle categorie, che può superare il totale complessivo.",
            ],
            [
              'Panoramica › Mappa categorie × indicatori',
              "Una riga per categoria, una colonna per indicatore: più il colore è intenso, più il valore è alto rispetto alle altre categorie per quell'indicatore. \"—\" indica una colonna non configurata per la categoria. Clicca una categoria per aprirne la scheda.",
            ],
            [
              'Scheda categoria',
              'Una casella per ogni colonna configurata per la categoria (zeri compresi), il grafico **Profilo indicatori** con i totali delle colonne e la **Classifica operatori**. Con Righe = Solo totale la classifica non compare; con Solo operatori non compare il Profilo indicatori.',
            ],
            [
              'Classifica operatori',
              "Una riga per operatore GA2 e una colonna per indicatore, con una barra proporzionale al valore più alto della colonna. Si apre ordinata sulla prima colonna, dal valore più alto; clicca un'intestazione per ordinare, un secondo clic inverte l'ordine. Ogni indicatore ha un suo colore (pallino nell'intestazione, barra e valori diversi da zero); gli zeri restano grigi. I primi tre hanno il podio, se il valore è maggiore di zero; Non assegnato resta sempre in fondo, senza posizione.",
            ],
          ],
        },
        {
          type: 'paragraph',
          text: '**Variazione rispetto al periodo precedente.** Se Dal e Al sono entrambi compilati, ogni casella mostra la variazione percentuale rispetto al periodo precedente della stessa durata (per Ultimi 7 giorni, i 7 giorni prima), con tutti gli altri filtri uguali. Verde = miglioramento, rosso = peggioramento; **Nuovo** = nel periodo precedente il valore era 0. Passa il mouse sulla variazione per leggere il valore del periodo precedente.',
        },
        {
          type: 'note',
          text: 'Le colonne "non gestiti" (Richiami e Nuovi contatti non gestiti) hanno l\'icona ambra: sono numeri da far scendere, quindi per loro un aumento è rosso e un calo è verde.',
        },
        {
          type: 'table',
          headers: ['Pagina', 'File CSV/Excel'],
          rows: [
            ['Si aggiorna subito', 'Viene preparato e poi scaricato'],
            ['Ha il Totale complessivo', 'Non ha una riga di totale generale'],
            ['Valori per operatore nella Classifica operatori', 'Una riga per ogni operatore'],
            [
              'Solo le colonne configurate di ogni categoria',
              'Tutte le colonne usate da almeno una categoria selezionata',
            ],
            ['Variazione sul periodo precedente', 'Solo i valori del periodo scelto'],
          ],
        },
      ],
    },
    {
      id: 'statistics-filters',
      title: 'I filtri delle statistiche',
      blocks: [
        {
          type: 'paragraph',
          text: 'Le scelte rapide del periodo cambiano solo le date. Per tutto il resto clicca **Filtri** (o **Personalizzato**) per aprire il pannello **Filtri report e statistiche**: modifica i valori e premi **Applica** (o **Annulla**). **Azzera filtri** riporta i valori iniziali (oggi, tutte le categorie, sedi e operatori, modalità Tutto): la modifica vale solo dopo **Applica**. Gli stessi filtri valgono per grafici e file.',
        },
        {
          type: 'table',
          headers: ['Filtro', 'Cosa fa', 'Valore predefinito'],
          rows: [
            ['Dal / Al', 'Periodo considerato, giorni inclusi per intero. Si può lasciare vuoto uno dei due campi o entrambi: solo Al = tutto fino a quella data; solo Dal = tutto da quella data in poi; entrambi vuoti = nessun limite di date.', 'Oggi (Dal e Al)'],
            ['Categorie', 'Quali categorie includere.', 'Tutte'],
            ['Sedi', 'Limita agli operatori di quelle sedi.', 'Tutte'],
            ['Operatori', 'Limita a certi operatori GA2.', 'Tutti'],
            ['Righe da includere', 'Solo totale, Solo operatori o Tutto.', 'Tutto'],
          ],
        },
        {
          type: 'note',
          text: 'Se sono compilati entrambi, Al non può precedere Dal. La data usata cambia da colonna a colonna (vedi la tabella delle colonne). N. Richiami non gestiti, N. Nuovi contatti non gestiti e N. Potenziali associati ignorano il periodo e guardano alla situazione di oggi; le loro versioni "(nel periodo selezionato)" lo usano.',
        },
        {
          type: 'list',
          items: [
            'Primo clic su una categoria con sottocategorie: selezioni solo la categoria.',
            'Secondo clic: aggiungi anche tutte le sottocategorie.',
            'Terzo clic: togli categoria e sottocategorie.',
          ],
        },
        {
          type: 'note',
          text: "Anche selezionando solo la categoria superiore, la sua riga conta già le richieste delle sottocategorie. Serve almeno una categoria; **Seleziona tutto** seleziona o toglie l'intero elenco.",
        },
        {
          type: 'paragraph',
          text: 'Sedi e Operatori compaiono solo con Solo operatori o Tutto. La sede di una richiesta, qui, è la sede del suo operatore GA2, non la sede operativa della richiesta; filtrando per sede le richieste senza operatore restano escluse.',
        },
        {
          type: 'warning',
          text: 'Con Solo totale i filtri Sedi e Operatori non si applicano: il totale comprende tutti. Le scelte restano memorizzate per quando cambi modalità.',
        },
      ],
    },
    {
      id: 'statistics-report-structure',
      title: 'Struttura del report',
      blocks: [
        {
          type: 'paragraph',
          text: 'Quali categorie compaiono si decide in **Prodotti › Categorie Prodotto**: **Visibile nei report** mette la categoria nel report (le sottocategorie ereditano e possono forzarlo a no, escludendo anche le proprie); **Colonne report** sceglie le colonne calcolate. Una categoria esclusa non è contata nemmeno nei totali della categoria superiore.',
        },
        {
          type: 'paragraph',
          text: 'Una richiesta appartiene a ogni categoria di almeno uno dei suoi prodotti, sottocategorie incluse: una richiesta con prodotti di due categorie conta in entrambe.',
        },
        {
          type: 'list',
          items: [
            "Le righe del file: le categorie compaiono in ordine alfabetico, ognuna seguita dalle sue sottocategorie selezionate (anch'esse alfabetiche).",
            'Per ogni categoria: riga TOTALE, poi operatori in ordine alfabetico, infine Non assegnato.',
          ],
        },
        {
          type: 'note',
          text: 'Un operatore compare solo se ha almeno un valore diverso da zero; Non assegnato solo se ci sono richieste senza operatore che contribuiscono ai conteggi.',
        },
        {
          type: 'paragraph',
          text: 'Le colonne del file: prima le fisse Categoria e GA2 (TOTALE, nome operatore o Non assegnato), poi le colonne statistiche usate da almeno una categoria selezionata. Se una colonna non è configurata per la categoria della riga la cella resta vuota; uno 0 indica una colonna configurata ma senza risultati.',
        },
      ],
    },
    {
      id: 'statistics-columns',
      title: 'Le colonne statistiche',
      blocks: [
        {
          type: 'table',
          headers: ['Colonna', 'Cosa conta', 'Periodo e note'],
          rows: [
            [
              'N. Telefonate Effettuate',
              'Le note collegate alla richiesta e scritte dal suo GA2: ogni nota vale una telefonata.',
              'Data di creazione della nota. Escluse le richieste ancora Aperte, le note cancellate, generali o scritte da altri; per Non assegnato vale sempre 0.',
            ],
            [
              'N. Richiami non gestiti',
              'Le richieste con data di richiamo di oggi o già passata, non ancora chiuse.',
              'Ignora il periodo: guarda sempre alla data di oggi.',
            ],
            [
              'N. Richiami non gestiti (nel periodo selezionato)',
              'Le richieste non ancora chiuse con data di richiamo compresa nel periodo.',
              'Data di richiamo; conta anche i richiami futuri se cadono nel periodo.',
            ],
            [
              'N. Nuovi contatti non gestiti',
              'Le richieste ancora nello stato Aperto.',
              'Ignora il periodo: conta anche le richieste create prima.',
            ],
            [
              'N. Nuovi contatti non gestiti (nel periodo selezionato)',
              'Le richieste create nel periodo e ancora nello stato Aperto.',
              'Data di creazione della richiesta.',
            ],
            [
              'N. Potenziali associati',
              'Le richieste che oggi si trovano in uno stato del gruppo In attesa o Validato.',
              'Ignora il periodo: guarda lo stato attuale.',
            ],
            [
              'N. Potenziali associati (nel periodo selezionato)',
              'Le richieste passate nel periodo a uno stato del gruppo In attesa o Validato.',
              'Data del cambio di stato; ogni richiesta conta una volta.',
            ],
            [
              'Associati',
              'Le richieste che oggi si trovano in uno stato del gruppo Chiuso con esito positivo.',
              'Data di creazione della richiesta. Guarda lo stato attuale: una richiesta chiusa positiva e poi spostata in un altro stato non conta.',
            ],
            ['Trattative Concluse', 'Stesso calcolo di Associati.', 'Cambia solo il nome, secondo le categorie che lo usano.'],
            ['Invio Presa in carico', 'Stesso calcolo di Associati.', 'Cambia solo il nome, secondo le categorie che lo usano.'],
            [
              'Aziende inserite',
              'Le anagrafiche di tipo azienda create nel periodo e collegate come cliente a una richiesta.',
              "Data di creazione dell'anagrafica; nel TOTALE ogni azienda conta una volta.",
            ],
            ['Aule in gestione', 'Non ancora calcolata.', 'Vale sempre 0.'],
            ['Aule in partenza', 'Non ancora calcolata.', 'Vale sempre 0.'],
            [
              'Presa Appuntamenti',
              'Le richieste passate direttamente da uno stato "OK App. Fissato" allo stato "Assegnato".',
              'Data del cambio di stato; conta anche se poi la richiesta cambia stato. Vale 0 nelle categorie il cui workflow non ha questi due stati.',
            ],
          ],
        },
        {
          type: 'note',
          text: 'In Aziende inserite la somma delle righe degli operatori può superare il TOTALE, se la stessa azienda è collegata a richieste di operatori diversi. Nelle altre colonne il TOTALE è uguale alla somma delle righe.',
        },
        {
          type: 'tip',
          text: 'I cambi di stato contano sia se fatti da Gestione Richieste sia dal modulo Offerte.',
        },
      ],
    },
    {
      id: 'statistics-export',
      title: 'Generare il file',
      blocks: [
        {
          type: 'steps',
          items: [
            'Controlla i filtri in **Filtri applicati**.',
            'Clicca **Genera report** e scegli **CSV** oppure **Excel (XLSX)**.',
            'Attendi il messaggio "Generazione in corso…": resta sulla pagina.',
            'Al termine compare "Report generato: il download è partito automaticamente."',
          ],
        },
        {
          type: 'paragraph',
          text: 'Non arriva una notifica separata. Il file si chiama, per esempio, request-management-report-2026-09-14_2026-09-18.xlsx (date Dal e Al). Con un solo estremo diventa ...-from-DATA o ...-to-DATA; senza date, solo request-management-report.xlsx.',
        },
        {
          type: 'warning',
          text: 'Se la preparazione non riesce compare "La generazione del report non è riuscita. Riprova."',
        },
      ],
    },
    {
      id: 'statistics-faq',
      title: 'Domande frequenti sulle statistiche',
      blocks: [
        {
          type: 'list',
          items: [
            '**Perché vedo 0?** La colonna è tra quelle non ancora calcolate; nel periodo non è successo nulla; le note sono di un utente diverso dal GA2; la richiesta è ancora in Aperto; sede e operatore scelti non hanno nulla in comune.',
            '**Perché una cella è vuota e non 0?** La colonna non è configurata per quella categoria: controlla Colonne report in Categorie Prodotto.',
            '**Perché un operatore non compare?** Non ha valori diversi da zero nel periodo, hai scelto Solo totale, è escluso dai filtri Operatori o Sedi, oppure non vedi le sue richieste con i tuoi permessi.',
            '**Perché manca "Non assegnato"?** Non ci sono richieste senza operatore che contribuiscono ai conteggi, oppure hai filtrato per sede.',
            '**Perché i totali del padre non includono una sottocategoria?** La sottocategoria ha Visibile nei report a no, direttamente o per eredità.',
            "**Perché i numeri di un operatore sono cambiati dopo un trasferimento?** I conteggi guardano l'operatore attuale: le note del vecchio operatore non contano più come telefonate.",
            '**Perché alcune colonne non cambiano con il periodo?** N. Richiami non gestiti, N. Nuovi contatti non gestiti e N. Potenziali associati guardano sempre alla situazione di oggi. Per il dato del periodo usa la loro versione "(nel periodo selezionato)", da attivare in Colonne report della categoria.',
            '**Perché Associati vale 0 anche se ho richieste chiuse con esito positivo?** Associati, Trattative Concluse e Invio Presa in carico contano le richieste create nel periodo scelto: con il periodo di oggi restano fuori quelle create nei giorni precedenti. Allarga il periodo Dal/Al.',
            '**Perché il Totale complessivo è più basso della somma delle categorie?** Una richiesta presente in più categorie selezionate conta una volta sola nel totale complessivo.',
            '**Perché non vedo la variazione percentuale?** Serve un periodo con entrambe le date Dal e Al (Tutto o un solo estremo non hanno un periodo precedente); se il valore è 0 sia ora sia nel periodo precedente la variazione non compare.',
          ],
        },
      ],
    },
  ],
}

export default guide
