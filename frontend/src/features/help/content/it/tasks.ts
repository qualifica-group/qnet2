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
          text: 'Il **Completamento** in percentuale dipende dallo stato scelto e non si inserisce a mano.',
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
            ['Assegnazione', 'I task assegnati a te, richiesti da te, assegnati da te (sei richiedente ma non assegnatario), creati da te (con un altro richiedente) oppure osservati da te.'],
          ],
        },
        {
          type: 'tip',
          text: 'All\'apertura la tabella mostra solo i task **aperti**: scegli **Tutti** nel filtro Stato per vedere anche quelli chiusi. Se arrivi da un riquadro della **Dashboard**, la tabella si apre già filtrata solo per quella visita, senza cambiare i filtri salvati.',
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
            ['Task', 'Titolo (obbligatorio), Descrizione, Task padre.'],
            ['Classificazione', 'Stato (obbligatorio), Tipologia, Categoria, Priorità, Importanza.'],
            ['Anagrafica e referente', 'Anagrafica, Referente (tra quelli dell\'anagrafica).'],
            ['Persone', 'Richiedente (obbligatorio), Assegnatari (almeno uno), Osservatori.'],
            ['Pianificazione', 'Data inizio, Data fine (obbligatoria), orari, Tempo stimato (minuti).'],
            ['Record collegati', 'Opportunità o Commessa; con una Commessa, la Fase in cui mettere il task (solo fasi aperte, non per i sottotask).'],
            ['Chiusura', 'Feedback obbligatorio, Validazione.'],
            ['Ricorrenza', 'Frequenza e fine della ripetizione.'],
          ],
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
          text: 'Ogni stato appartiene a una fase: Aperto, In attesa, Da validare, Chiuso con esito positivo, Chiuso con esito negativo. Un task in validazione o chiuso non accetta modifiche ai dati principali.',
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
            ['Riapri', 'Riporta il task nello stato "In corso".'],
            ['Approva', 'Chiude definitivamente un task in validazione.'],
            ['Rifiuta', 'Riporta un task in validazione a "In corso".'],
            ['Blocca / Sblocca', 'Sospende o riattiva il task.'],
            ['Richiedi aggiornamento', 'Invia mail e notifica ad assegnatari e osservatori scelti.'],
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
            'ti viene assegnato un task, come assegnatario o osservatore;',
            'un task va in validazione, viene approvato o rifiutato;',
            'un task viene chiuso, riaperto, bloccato o sbloccato;',
            'qualcuno ti chiede un aggiornamento.',
          ],
        },
        {
          type: 'note',
          text: "Chi esegue l'azione non riceve la notifica.",
        },
      ],
    },
  ],
}

export default guide
