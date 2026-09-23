import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'notifications',
  title: 'Notifiche',
  summary:
    'L\'elenco di tutte le tue notifiche: filtrale, apri il collegamento e segna letto/non letto una alla volta o in blocco.',
  sections: [
    {
      id: 'overview',
      title: 'Cosa mostra la pagina',
      blocks: [
        {
          type: 'paragraph',
          text: 'La pagina elenca solo le TUE notifiche, più recenti per prime: titolo, messaggio, livello (Info, Successo, Avviso, Errore), data di ricezione, data di lettura e, quando presente, il collegamento alla pagina interessata.',
        },
        {
          type: 'note',
          text: 'La campanella in testata mostra le ultime notifiche e il contatore delle non lette; questa pagina è l\'elenco completo, filtrabile. Si apre dal link **Notifiche** in fondo al menu laterale, appena sopra **Impostazioni**.',
        },
      ],
    },
    {
      id: 'filtering',
      title: 'Filtrare e cercare',
      blocks: [
        {
          type: 'list',
          items: [
            'Stato: **Letta** oppure **Non letta**.',
            'Livello: Info, Successo, Avviso, Errore.',
            'Data di ricezione o di lettura.',
            'Ricerca libera su titolo e messaggio.',
          ],
        },
      ],
    },
    {
      id: 'reading-state',
      title: 'Segnare letto o non letto',
      blocks: [
        {
          type: 'steps',
          items: [
            'Su una riga non letta, il pulsante **Segna come letta** (icona busta aperta) nella colonna Azioni la marca come letta.',
            'Su una riga letta, il pulsante **Segna come non letta** (icona busta chiusa) la riporta a non letta.',
            'Selezionando più righe, **Segna selezionate come lette** le marca tutte come lette in un solo passaggio.',
            'Il pulsante **Segna tutte come lette** in testata marca come lette TUTTE le tue notifiche, anche quelle non visibili nella pagina corrente.',
          ],
        },
        {
          type: 'note',
          text: 'Non è possibile eliminare una notifica: lo stato letto/non letto è l\'unica cosa che si può cambiare.',
        },
      ],
    },
    {
      id: 'opening-the-link',
      title: 'Aprire il collegamento',
      blocks: [
        {
          type: 'paragraph',
          text: 'Quando una notifica ha un collegamento, la colonna Collegamento mostra **Apri**. Cliccandolo, se la notifica era non letta viene segnata come letta; il record interessato (es. un\'azienda, un\'opportunità) si apre in un **pannello laterale** sopra la pagina notifiche, senza lasciarla. Dalla barra in alto del pannello puoi passare alla pagina completa del record. I collegamenti che non puntano a un singolo record (es. l\'esito di un\'importazione) aprono invece la pagina interessata.',
        },
        {
          type: 'note',
          text: 'Non tutte le notifiche hanno un collegamento: in quel caso la colonna resta vuota. Con Ctrl/Cmd+clic su **Apri** il collegamento si apre in una nuova scheda del browser.',
        },
      ],
    },
    {
      id: 'the-bell',
      title: 'Il link nel menu e nella campanella',
      blocks: [
        {
          type: 'list',
          items: [
            'Il link **Notifiche**, in fondo al menu laterale sopra **Impostazioni**, apre questa pagina; mostra un contatore con il numero delle notifiche non lette (oltre 99 mostra **99+**) e sparisce quando non ce ne sono.',
            'Il link **Vedi tutte**, in fondo al pannello della campanella, porta alla stessa pagina e chiude il pannello.',
          ],
        },
      ],
    },
  ],
}

export default guide
