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
            ['Scadenza', 'Oggi, Scadute o Questa settimana, sulla data fine (o sulla data inizio se manca).'],
            [
              'Assegnazione',
              'Scegli **uno o più** valori insieme (preimpostato: Assegnati a te): assegnati a te, richiesti da te, assegnati da te (sei richiedente ma non assegnatario), creati da te (li hai creati ma non sei né il richiedente, né un assegnatario, né un osservatore) oppure osservati da te. **Tutti** mostra ogni task in cui hai un qualsiasi ruolo (richiedente, assegnatario, osservatore o creatore), anche se hai il permesso Visualizza tutti. Se hai il permesso Visualizza tutti o Visualizza sede trovi anche **Tutti i visibili**, che mostra ogni task che puoi vedere, compresi quelli dei colleghi.',
            ],
          ],
        },
        {
          type: 'tip',
          text: 'All\'apertura la tabella mostra solo i task **assegnati a te e aperti**, ordinati dall\'ultimo aggiornamento più recente. Scegli altri valori nei filtri Assegnazione e Stato per allargare la vista. Se arrivi da un riquadro della **Dashboard**, la tabella si apre già filtrata solo per quella visita, senza cambiare i filtri salvati.',
        },
        {
          type: 'paragraph',
          text: 'Il pulsante **Statistiche** apre i riquadri Scaduti, In scadenza oggi, Stimato ed Effettivo, con i grafici per stato, per priorità e dei nuovi task per mese. Contano solo i task principali (non i sotto-task) tra quelli che puoi vedere, e non dipendono dai filtri della tabella.',
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
          text: 'Dal dettaglio premi **Nuovo sotto-task** per creare un\'attività figlia, con date comprese in quelle del padre.',
        },
        {
          type: 'paragraph',
          text: 'Con **Ricorrenza attiva** QNet crea da solo le occorrenze future. Scegli la frequenza (Giornaliera, Settimanale, Mensile), l\'intervallo in **Ripeti ogni** e la fine: A una data, Dopo un numero di occorrenze o Mai.',
        },
        {
          type: 'note',
          text: 'Scegliendo un **Modello di Task** alla creazione di una commessa (azione **Programma** sul contratto), i task del modello vengono creati e assegnati ai responsabili: li trovi nella sezione Task della commessa. I modelli si configurano in **Task › Modelli di Task**.',
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
