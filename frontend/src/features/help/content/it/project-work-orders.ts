import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'project-work-orders',
  title: 'Commesse di Progetto',
  summary: 'Le commesse di tipo Progetto, con la chat del team su ogni commessa.',
  sections: [
    {
      id: 'overview',
      title: "Cos'è questa pagina",
      blocks: [
        {
          type: 'paragraph',
          text: 'In **Lavorazioni › Commesse di Progetto** trovi le commesse di tipo **Progetto**. Queste commesse **non compaiono più in Commesse**, che mostra solo le commesse di lavorazione. La tabella funziona come quella delle Commesse: stesse colonne, filtri, modifica rapida ed esportazione; aprendo una riga vedi il dettaglio della commessa, descritto nella guida **Commesse**.',
        },
        {
          type: 'note',
          text: "Nelle schede **Commesse** di un Contratto o di un'Anagrafica continui a vedere le commesse di tutti i tipi, Progetto compreso.",
        },
      ],
    },
    {
      id: 'unread-column',
      title: 'La colonna Non letti',
      blocks: [
        {
          type: 'paragraph',
          text: 'La colonna **Non letti** mostra, per ogni commessa, quanti messaggi della chat non hai ancora letto. Resta vuota se non ci sono messaggi nuovi o se la chat di quella commessa non è visibile per te.',
        },
      ],
    },
    {
      id: 'chat-access',
      title: 'La chat: chi la vede e cosa si scrive',
      blocks: [
        {
          type: 'paragraph',
          text: 'Ogni commessa di progetto ha una tab **Chat** nel dettaglio, per scambiare messaggi con il team. La vedono e ci scrivono i **Responsabili** e i **Partecipanti** della commessa e gli **amministratori**. Chi vede la commessa per altri motivi (per esempio perché ha il permesso di vedere tutte le commesse) **non** vede la chat.',
        },
        {
          type: 'list',
          items: [
            'I messaggi sono **solo testo**, fino a **2.000 caratteri**.',
            'I messaggi nuovi compaiono **in tempo reale**, senza ricaricare la pagina.',
            'Se i messaggi sono tanti, **Carica messaggi precedenti** mostra quelli più vecchi.',
          ],
        },
        {
          type: 'steps',
          items: [
            'Apri la commessa e scegli la tab **Chat**.',
            'Scrivi nel campo **Scrivi un messaggio**.',
            'Premi **Invia**.',
          ],
        },
        {
          type: 'note',
          text: 'La chat è separata dalle **Note** della commessa, che restano come prima.',
        },
      ],
    },
    {
      id: 'edit-delete',
      title: 'Modificare e cancellare i messaggi',
      blocks: [
        {
          type: 'paragraph',
          text: "Puoi usare **Modifica** ed **Elimina** sui tuoi messaggi entro **15 minuti** dall'invio. Quando lo modifichi, accanto al messaggio compare **modificato**. Dopo i 15 minuti il messaggio non si cambia più.",
        },
        {
          type: 'paragraph',
          text: 'Prima di cancellare ti viene chiesta una conferma: scegli **Elimina** per procedere o **Annulla** per tenere il messaggio. Un messaggio cancellato non sparisce: resta nella conversazione come **Messaggio eliminato**, senza il testo, così il filo del discorso non si perde.',
        },
        {
          type: 'paragraph',
          text: 'Gli **amministratori** possono modificare o cancellare **qualsiasi** messaggio, anche di altri e senza il limite dei 15 minuti.',
        },
      ],
    },
    {
      id: 'closed-work-orders',
      title: 'Commessa completata o chiusa',
      blocks: [
        {
          type: 'paragraph',
          text: "Quando la commessa è **Completata** o **Chiusa**, la chat diventa in **sola lettura**: rivedi i messaggi ma non puoi inviarne, modificarli o cancellarli, e vedi l'avviso **Chat in sola lettura: non puoi inviare messaggi.** Vale anche per gli amministratori.",
        },
      ],
    },
    {
      id: 'chat-badge',
      title: 'Il numero sulla tab Chat',
      blocks: [
        {
          type: 'paragraph',
          text: "Sulla tab **Chat** compare un numero con i messaggi non letti, che si aggiorna anche mentre guardi un'altra tab della commessa. Si **azzera** quando apri la tab Chat con la pagina in primo piano: i messaggi risultano letti e la colonna **Non letti** si aggiorna di conseguenza. I tuoi messaggi non contano come non letti.",
        },
      ],
    },
    {
      id: 'no-notifications',
      title: 'Nessuna notifica',
      blocks: [
        {
          type: 'note',
          text: 'I messaggi della chat **non generano notifiche** (nessuna campanella) **né email**. Per sapere se ci sono novità guarda la colonna **Non letti** e il numero sulla tab **Chat**.',
        },
      ],
    },
  ],
}

export default guide
