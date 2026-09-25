import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'tasks',
  title: 'Task',
  summary: 'I Task sono le attività da svolgere, con chi le chiede, chi le esegue e una scadenza.',
  sections: [
    {
      id: 'overview',
      title: 'Chi vede un task',
      blocks: [
        {
          type: 'paragraph',
          text: 'Si trovano in **Task › Task**. Quali task vedi dipende dai permessi del tuo ruolo:',
        },
        {
          type: 'table',
          headers: ['Permesso', 'Cosa vedi'],
          rows: [
            ['Nessuno (predefinito)', 'Solo i tuoi task: quelli che hai creato o richiesto, o di cui sei assegnatario o osservatore.'],
            ['**Visualizza per sede**', 'I tuoi task e quelli in cui almeno un assegnatario lavora in una delle tue sedi (fisica o remota).'],
            ['**Visualizza tutti**', 'Tutti i task.'],
          ],
        },
        {
          type: 'note',
          text: 'Vedere un task della tua sede non ti permette di modificarlo: per farlo devi averci un ruolo (creatore, richiedente, assegnatario) o il permesso **Gestisci tutto**.',
        },
        {
          type: 'tip',
          text: 'Il **Completamento** in percentuale non si inserisce a mano: dipende dallo stato scelto oppure, se il task ha dei sotto-task, dalla media (arrotondata) delle loro percentuali — che resta al massimo al 99% finché il task è aperto, per lasciare al 100% solo un task davvero completato. La barra e il numero sono colorati come nella commessa: rosso fino al 33%, ambra fino al 66%, blu oltre, verde al 100%.',
        },
        {
          type: 'note',
          text: 'Se apri un task a cui non hai accesso, vedi un messaggio di "Accesso non consentito" con i contatti (richiedente e creatore) a cui puoi scrivere: la pagina non ha un pulsante Riprova, perché non si tratta di un errore temporaneo.',
        },
      ],
    },
    {
      id: 'filters-and-statistics',
      title: 'Filtri e statistiche',
      blocks: [
        {
          type: 'paragraph',
          text: 'Con i **filtri avanzati** della tabella restringi l\'elenco con gli stessi criteri della scheda Task della commessa: Stato, Scadenza, Assegnazione, Stato task, Tipologia, Priorità, Importanza, Richiedente, Assegnatari e Osservatori.',
        },
        {
          type: 'table',
          headers: ['Filtro', 'Cosa mostra'],
          rows: [
            ['Stato', 'Aperti (preimpostato), Completati, Bloccati, In validazione o Tutti.'],
            ['Scadenza', 'Oggi, Scadute, Questa settimana o Questo mese, sulla data fine (o sulla data inizio se manca).'],
            [
              'Assegnazione',
              'Scegli **uno o più** valori insieme (preimpostato: Assegnati a te): assegnati a te, richiesti da te, assegnati da te (sei richiedente ma non assegnatario), creati da te (li hai creati ma non sei né il richiedente, né un assegnatario, né un osservatore) oppure osservati da te. **Tutti** mostra ogni task in cui hai un qualsiasi ruolo (richiedente, assegnatario, osservatore o creatore), anche se hai il permesso Visualizza tutti. Se hai il permesso Visualizza tutti o Visualizza sede trovi anche **Tutti i visibili**, che mostra ogni task che puoi vedere, compresi quelli dei colleghi.',
            ],
            ['Anagrafica', 'Uno o più record di Anagrafica a cui è collegato il task.'],
            ['Commessa', 'Una o più Commesse a cui è collegato il task.'],
          ],
        },
        {
          type: 'tip',
          text: 'All\'apertura la tabella mostra solo i task **assegnati a te e aperti**, ordinati dall\'ultimo aggiornamento più recente. Scegli altri valori nei filtri Assegnazione e Stato per allargare la vista. Se arrivi da un riquadro della **Dashboard**, la tabella si apre già filtrata solo per quella visita, senza cambiare i filtri salvati.',
        },
        {
          type: 'note',
          text: 'La ricerca rapida trova anche per ID esatto: digitando solo numeri, oltre al titolo trova anche il task con quell\'identificativo.',
        },
        {
          type: 'paragraph',
          text: 'Il pulsante **Statistiche** apre i riquadri Scaduti, In scadenza oggi, Stimato ed Effettivo, con i grafici per stato, per priorità e dei nuovi task per mese. Contano solo i task principali (non i sotto-task) tra quelli che puoi vedere, e non dipendono dai filtri della tabella.',
        },
      ],
    },
    {
      id: 'views-and-kanban',
      title: 'Viste: Analitica, Sintetica, Kanban',
      blocks: [
        {
          type: 'paragraph',
          text: 'In alto alla tabella scegli come vedere i task: **Analitica** (l\'elenco piatto, la vista di sempre), **Sintetica** (un albero: i task senza padre in cima, espandendo un task ne compaiono i sotto-task) o **Kanban** (colonne trascinabili). Sintetica e Kanban usano gli stessi filtri e la stessa ricerca dell\'Analitica.',
        },
        {
          type: 'note',
          text: 'In Sintetica un sotto-task che rispetta i filtri ma il cui padre no non compare: per vederlo passa all\'Analitica.',
        },
        {
          type: 'paragraph',
          text: 'Il Kanban ha due modalità:',
        },
        {
          type: 'table',
          headers: ['Modalità', 'Colonne'],
          rows: [
            ['Per stato', 'Una colonna per ogni stato attivo del catalogo, nell\'ordine configurato.'],
            [
              'Per scadenza',
              'Scaduti, Oggi, Domani, Questa settimana, Questo mese (solo il mese corrente, anche i task senza scadenza), Più avanti (oltre la fine del mese corrente) e Completati.',
            ],
          ],
        },
        {
          type: 'steps',
          items: [
            'Trascina una card su un\'altra colonna per spostarla.',
            'Per stato: tra due stati aperti lo stato cambia subito; verso uno stato di chiusura si apre la finestra **Completa**, come nell\'elenco — annullandola la card torna al suo posto; da uno stato chiuso a uno aperto il task viene riaperto.',
            'Per scadenza: trascinare su Oggi, Domani, Questa settimana, Questo mese o Più avanti imposta la data fine di conseguenza (rispettivamente oggi, domani, la domenica di questa settimana, l\'ultimo giorno del mese, il primo giorno del mese successivo).',
          ],
        },
        {
          type: 'note',
          text: 'La colonna **Scaduti** non accetta trascinamenti in ingresso; la colonna **Completati** non si può trascinare né in ingresso né in uscita — per riaprire un task completato usa l\'azione Riapri.',
        },
        {
          type: 'warning',
          text: 'Il Kanban carica al massimo 500 task tra quelli che rispettano i filtri: se sono di più, un avviso invita a restringere i filtri (l\'elenco Analitica/Sintetica non ha questo limite).',
        },
        {
          type: 'paragraph',
          text: 'Il pulsante **+** in fondo a ogni colonna apre il modulo di creazione con lo stato o la scadenza già precompilati con quella colonna. La modalità di vista scelta si ricorda per te tra un accesso e l\'altro.',
        },
      ],
    },
    {
      id: 'creating-a-task',
      title: 'Creare un task',
      blocks: [
        {
          type: 'steps',
          items: [
            'Premi **Nuovo task**.',
            'Compila i campi delle sezioni del modulo (vedi tabella).',
            'Se vuoi, aggiungi file in **Allegati**: vengono caricati appena il task è salvato.',
            'Premi **Salva**.',
          ],
        },
        {
          type: 'table',
          headers: ['Sezione', 'Campi principali'],
          rows: [
            ['Task', 'Titolo (obbligatorio), Descrizione, Evidenze, Task padre.'],
            [
              'Classificazione',
              'Stato (facoltativo in creazione: se non lo scegli, parte da quello predefinito), Tipologia, Priorità e Importanza (tutte e tre obbligatorie, precompilate con la voce predefinita del catalogo), Categoria (ad albero, indentata, puoi scegliere anche una categoria padre).',
            ],
            ['Anagrafica e referente', 'Anagrafica, Referente (tra quelli dell\'anagrafica).'],
            [
              'Persone',
              'Richiedente (obbligatorio), Assegnatari (almeno uno), Osservatori, Task privato, Non inviare notifica di apertura.',
            ],
            ['Pianificazione', 'Data inizio, Data fine (obbligatoria, precompilata a oggi), orari, Tempo stimato (minuti).'],
            [
              'Record collegati',
              'Opportunità, Commessa o Lead (si escludono a vicenda tra Opportunità e Commessa; scegliere una Commessa imposta l\'anagrafica); con una Commessa, la Fase in cui mettere il task (solo fasi aperte, non per i sottotask).',
            ],
            ['Chiusura', 'Feedback obbligatorio, Validazione, Crea già completato (solo in creazione).'],
            ['Ricorrenza', 'Frequenza e fine della ripetizione.'],
            [
              'Sotto-task',
              'Righe facoltative con titolo (obbligatorio), data fine e assegnatari: creano subito dei task figli insieme al padre, fino a 50 per volta.',
            ],
          ],
        },
        {
          type: 'note',
          text: 'Lo **Stato** iniziale scelto a mano deve essere uno stato di lavorazione: gli stati di chiusura, quelli "da validare" e quelli raggiungibili solo da un\'azione (es. Completa) non sono selezionabili in creazione.',
        },
        {
          type: 'note',
          text: 'Un **Task privato** è visibile solo al creatore, al richiedente, agli assegnatari e agli osservatori: chi ha il permesso Visualizza tutti o Visualizza per sede non lo vede (il super-amministratore resta l\'unica eccezione).',
        },
        {
          type: 'note',
          text: 'Attivando **Crea già completato**, il task nasce già chiuso con esito positivo: viene registrato subito un segnatempo con i minuti stimati (anche 0), senza passare dalla validazione. Non è compatibile con Feedback obbligatorio o Validazione senza il relativo feedback.',
        },
        {
          type: 'tip',
          text: 'Per impostazione predefinita gli assegnatari e gli osservatori ricevono la notifica di assegnazione alla creazione: attiva **Non inviare notifica di apertura** per crearlo senza avvisarli. In modifica la stessa idea si chiama **Non notificare i nuovi assegnati** e riguarda solo chi aggiungi con quel salvataggio.',
        },
        {
          type: 'note',
          text: 'Cambiare l\'**Anagrafica** azzera Referente, Opportunità e Lead, e mantiene la Commessa solo se appartiene alla stessa anagrafica. Opportunità, Commessa e Lead mostrano solo i record dell\'anagrafica scelta, una volta che ne hai scelta una.',
        },
      ],
    },
    {
      id: 'people-and-roles',
      title: 'Persone e ruoli',
      blocks: [
        {
          type: 'table',
          headers: ['Ruolo', 'Chi è', 'Cosa può modificare'],
          rows: [
            ['Creato da', 'Chi ha creato il task.', 'Tutto.'],
            ['Richiedente', "Chi ha chiesto l'attività.", 'Tutto.'],
            [
              'Assegnatari',
              'Chi deve svolgerla.',
              'Solo descrizione, stato, orari, data di completamento e feedback.',
            ],
            ['Osservatori', 'Chi segue senza lavorarci.', '—'],
          ],
        },
        {
          type: 'note',
          text: 'Ogni stato appartiene a una fase: Aperto, In attesa, Da validare, Chiuso con esito positivo, Chiuso con esito negativo. Un task in validazione o chiuso non accetta modifiche ai dati principali, a eccezione di un super-amministratore, che può comunque modificarne i campi (ma non eliminarlo né completarlo).',
        },
        {
          type: 'note',
          text: 'Puoi eliminare un task se puoi modificarlo (creatore, richiedente, assegnatario o chi ha il permesso Gestisci tutto), purché non sia completato, in validazione o bloccato. Eliminando un task si eliminano a cascata anche i suoi sotto-task: se anche uno solo di questi non fosse eliminabile, l\'intera eliminazione viene annullata.',
        },
        {
          type: 'note',
          text: 'Chi può modificare il task gestisce anche tutte le voci del suo **Segnatempo**, comprese quelle inserite da un altro assegnatario.',
        },
      ],
    },
    {
      id: 'task-actions',
      title: 'Azioni sul task e completamento',
      blocks: [
        {
          type: 'paragraph',
          text: 'Nel dettaglio compaiono solo le azioni consentite in quel momento:',
        },
        {
          type: 'table',
          headers: ['Azione', 'Cosa fa'],
          rows: [
            ['Completa', 'Chiude il task o lo invia in validazione.'],
            ['Riapri', 'Riporta il task nello stato "In corso" e toglie un eventuale blocco.'],
            ['Approva', 'Chiude definitivamente un task in validazione.'],
            ['Rifiuta', 'Riporta un task in validazione allo stato "Assegnato", azzera il feedback di chiusura e toglie un eventuale blocco.'],
            ['Blocca / Sblocca', 'Sospende o riattiva il task. Puoi bloccare solo un task aperto (non completato né in validazione); completarlo, riaprirlo o rifiutarne la validazione lo sblocca automaticamente.'],
            ['Richiedi aggiornamento', 'Invia mail e notifica a un gruppo di destinatari, con un messaggio obbligatorio (vedi sotto).'],
          ],
        },
        {
          type: 'steps',
          items: [
            'Premi **Completa**.',
            'Se richiesto, scrivi il **Feedback di chiusura**.',
            'Se il task richiede validazione, scegli lo **Stato di validazione**.',
            'Nella sezione **Segnatempo** registra il tempo dedicato.',
            'Premi **Completa**.',
          ],
        },
        {
          type: 'warning',
          text: 'Con **Validazione** attiva, il completamento di un assegnatario non chiude il task: passa in validazione e il richiedente deve approvarlo o rifiutarlo. Non puoi completare un task con sotto-task aperti, né agire su un task bloccato.',
        },
        {
          type: 'note',
          text: 'Completando un task dal dettaglio (o dall\'elenco) il segnatempo viene registrato per **tutti gli assegnatari**, uno identico per ciascuno (per te soltanto se il task non ne ha). Completando un singolo sotto-task dal pannello Sotto-task, invece, il segnatempo si registra solo per te: non è una scelta disponibile, dipende da dove completi il task.',
        },
        {
          type: 'paragraph',
          text: 'La **Richiesta di aggiornamento** è riservata al richiedente, al creatore o a chi gestisce il task (non al semplice osservatore), e solo su un task non completato, non in validazione e non bloccato. Scegli uno dei tre gruppi di destinatari:',
        },
        {
          type: 'table',
          headers: ['Destinatari', 'Chi riceve la richiesta'],
          rows: [
            ['Assegnatari', 'Tutti gli assegnatari; gli osservatori ricevono comunque una copia, contrassegnata **In copia**.'],
            ['Osservatori', 'Tutti gli osservatori del task.'],
            ['Assegnatari e osservatori', 'Entrambi i gruppi, come destinatari diretti.'],
          ],
        },
        {
          type: 'note',
          text: 'Il messaggio è **obbligatorio** (da 3 a 2000 caratteri): spiega cosa vuoi sapere, così i destinatari lo leggono direttamente nella notifica.',
        },
      ],
    },
    {
      id: 'subtasks-and-recurrence',
      title: 'Sotto-task, ricorrenza e modelli',
      blocks: [
        {
          type: 'paragraph',
          text: 'Dal dettaglio premi **Nuovo sotto-task** per creare un\'attività figlia, con date comprese in quelle del padre. In alternativa, mentre crei il task puoi aggiungere subito una o più righe nella sezione **Sotto-task** del modulo: bastano un titolo e, se vuoi, una data fine e degli assegnatari, il resto viene ereditato dal padre.',
        },
        {
          type: 'paragraph',
          text: 'Nel pannello **Sotto-task** del dettaglio trascini le righe (con l\'apposita maniglia) per riordinarle, e da ogni riga puoi completare, riaprire o eliminare il singolo sotto-task, quando i tuoi permessi lo consentono.',
        },
        {
          type: 'paragraph',
          text: 'Con **Ricorrenza attiva** QNet crea da solo le occorrenze future. Scegli la frequenza — Giornaliera, Settimanale, Mensile, Annuale o Personalizzata (ogni N giorni) — l\'intervallo in **Ripeti ogni** e la fine: A una data, Dopo un numero di occorrenze o Mai.',
        },
        {
          type: 'paragraph',
          text: 'Per una ricorrenza Mensile o Annuale scegli se il giorno è **fisso** (es. il 31 del mese) oppure **ordinale** (es. il 2° martedì): per l\'Annuale scegli anche il mese. Con **Solo giorni lavorativi** attivo, le date generate cadono sempre dal lunedì al venerdì (non tiene conto delle festività).',
        },
        {
          type: 'note',
          text: 'Scegliendo un **Modello di Task** alla creazione di una commessa (azione **Programma** sul contratto), i task del modello vengono creati e assegnati ai responsabili: li trovi nella sezione Task della commessa. I modelli si configurano in **Task › Modelli di Task**.',
        },
      ],
    },
    {
      id: 'list-editing-and-bulk',
      title: 'Modifica rapida ed azioni sull\'elenco',
      blocks: [
        {
          type: 'paragraph',
          text: 'Dal selettore colonne puoi attivare cinque colonne nascoste di default: **Minuti effettivi** (somma del segnatempo di tutti), **Aggiornato il**, **Ricorrente**, **Task padre** e **Fase**.',
        },
        {
          type: 'paragraph',
          text: 'Un clic su una cella modificabile (Titolo, Stato, Tipologia, Priorità, Importanza, Data inizio, Scadenza, Richiedente, Assegnatari, Osservatori, Tempo stimato, Commessa, Fase) la modifica **direttamente nell\'elenco**, senza aprire il modulo: la cella si disabilita da sola su un task completato, in validazione o bloccato (il super-amministratore fa eccezione). La **Fase** si può scegliere solo se la riga ha già una Commessa.',
        },
        {
          type: 'note',
          text: 'Cambiando lo **Stato** verso uno di chiusura si apre la finestra **Completa**, la stessa del dettaglio: annullandola, la cella torna al valore di prima. Cambiando uno stato chiuso a uno aperto, il task viene riaperto subito, come con l\'azione Riapri.',
        },
        {
          type: 'paragraph',
          text: 'Sulla riga trovi le stesse azioni del dettaglio (Completa, Riapri, Approva, Rifiuta, Blocca, Sblocca, Richiedi aggiornamento), più **Duplica** (apre il modulo di creazione precompilato con gli stessi dati — tranne allegati, sotto-task, stato, data di completamento e segnatempo, e senza il task padre) e **Note** (apre il pannello note del task, con il numero di note nel badge sull\'icona).',
        },
        {
          type: 'paragraph',
          text: 'Selezionando una o più righe compare la barra **Azioni**: Assegna (sostituisce gli assegnatari), Completa, Riapri, Blocca, Sblocca, Priorità, Data inizio, Data fine, Elimina.',
        },
        {
          type: 'warning',
          text: 'Un\'azione massiva è **tutto o niente**: se anche un solo task selezionato non è ammesso (es. bloccato, o richiede validazione per Completa), l\'azione si ferma con un messaggio che elenca quali task e perché, e nessuno dei task selezionati viene modificato.',
        },
        {
          type: 'paragraph',
          text: 'Se hai il permesso di creare task, in fondo alla tabella trovi una riga compatta per crearne uno **rapidamente**: titolo, tipologia, priorità, importanza, stato, scadenza, richiedente, assegnatari e osservatori. Tipologia, priorità e importanza partono già valorizzate con la voce predefinita del catalogo; scadenza, richiedente e assegnatario di partenza sono, rispettivamente, oggi e tu stesso.',
        },
        {
          type: 'note',
          text: 'Il piè di pagina della tabella mostra il **totale dei minuti stimati** dell\'insieme filtrato (non solo della pagina visibile).',
        },
      ],
    },
    {
      id: 'collaboration-and-notifications',
      title: 'Collaborazione e notifiche',
      blocks: [
        {
          type: 'paragraph',
          text: 'Nel dettaglio del task trovi **Note**, **Documenti**, **Cronologia attività** e **Segnatempo**. Ricevi una notifica (campanella ed email) quando:',
        },
        {
          type: 'list',
          items: [
            'ti viene assegnato un task come nuovo assegnatario (a meno che tu ne sia anche il creatore) o come nuovo osservatore (sempre, anche se sei il creatore);',
            'un task va in validazione, oppure la sua validazione viene approvata o rifiutata;',
            'un task viene chiuso: la notifica arriva al richiedente e agli osservatori, e agli assegnatari solo se sono più di uno; se il task era in validazione, gli assegnatari ricevono comunque una notifica separata di approvazione;',
            'un task viene riaperto, bloccato o sbloccato;',
            'qualcuno ti chiede un aggiornamento: la ricevono i destinatari scelti, con una copia (**In copia**) agli osservatori quando la richiesta va agli assegnatari.',
          ],
        },
        {
          type: 'note',
          text: "Chi esegue l'azione non la riceve, così come il creatore in quanto tale (a meno che sia anche richiedente, assegnatario o osservatore) e gli utenti disattivati.",
        },
      ],
    },
  ],
}

export default guide
