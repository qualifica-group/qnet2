import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'chart-of-accounts',
  title: 'Piano dei conti',
  summary: 'Il piano dei conti gestionale organizza costi e ricavi in tre livelli: mastro, conto e sottoconto.',
  sections: [
    {
      id: 'overview',
      title: 'A cosa serve',
      blocks: [
        {
          type: 'paragraph',
          text: 'Si trova in **Configurazione › Contabilità › Piano dei conti**. È un piano **gestionale**: serve a classificare costi e ricavi per le analisi interne, **non** è una contabilità generale e non c’è prima nota né voci patrimoniali. I sottoconti saranno le voci che si assegnano ai documenti (prodotti e righe delle fatture attive arriveranno in una fase successiva).',
        },
        {
          type: 'note',
          text: 'In questa prima fase si gestisce solo il piano. Il collegamento ai prodotti e alle righe delle fatture attive, il sottoconto predefinito delle provvigioni e le statistiche arrivano nella fase 2 e successive.',
        },
      ],
    },
    {
      id: 'levels',
      title: 'I tre livelli',
      blocks: [
        {
          type: 'table',
          headers: ['Livello', 'Cosa è'],
          rows: [
            ['Mastro', 'Il livello più alto (per esempio 61 Servizi). Ha il **tipo Costo o Ricavo**, che conti e sottoconti ereditano.'],
            ['Conto', 'Sta sotto un mastro (per esempio 61.03 Utenze). Raggruppa i sottoconti.'],
            ['Sottoconto', 'La voce finale. Nei mastri di **Costo** è un **tipo di costo**; nei mastri di **Ricavo** è un sottoconto di ricavo.'],
          ],
        },
        {
          type: 'paragraph',
          text: 'Un conto si può spostare solo sotto un mastro dello stesso tipo; il tipo di un mastro che ha già conti non si può cambiare.',
        },
      ],
    },
    {
      id: 'tree',
      title: 'Leggere l’albero',
      blocks: [
        {
          type: 'list',
          items: [
            'Ogni mastro mostra il badge **Costo** o **Ricavo**; premi la freccia accanto alla voce (o i tasti freccia destra e sinistra) per aprirla e chiuderla.',
            'Le voci **non attive** sono attenuate e hanno il badge **Non attivo**. In questa fase è solo un’informazione: l’effetto sulle scelte nei documenti arriva con la fase 2.',
            '**Espandi tutto** e **Comprimi tutto** aprono o chiudono tutto l’albero.',
            'La **ricerca** per codice o descrizione mostra le voci che corrispondono insieme ai loro mastri e conti, già aperti. Svuotando la ricerca l’albero torna come lo avevi lasciato. Durante la ricerca le frecce di apertura sono bloccate.',
            'Il gruppo **Sottoconti di costo da collocare** compare solo se ci sono tipi di costo senza conto (per esempio arrivati dalla migrazione): da lì si collocano nel piano.',
          ],
        },
      ],
    },
    {
      id: 'managing',
      title: 'Creare, modificare, eliminare',
      blocks: [
        {
          type: 'steps',
          items: [
            'Premi **Nuovo mastro** per creare un mastro: scegli il tipo **Costo** o **Ricavo**, indica codice e nome.',
            'Dal menu della riga (i tre puntini) di un mastro scegli **Nuovo conto**; da quello di un conto scegli **Nuovo sottoconto**: il form si apre con il padre già impostato. Su un conto di ricavo crea un sottoconto di ricavo; su un conto di costo crea un tipo di costo (con anche natura e colore).',
            'Dallo stesso menu puoi **Modificare**, **Eliminare** e aprire lo **Storico attività** della voce. Le voci che non puoi gestire con i tuoi permessi non compaiono nel menu.',
            'Per un tipo di costo senza conto scegli **Colloca nel piano**: indica il conto e il codice.',
            'Se il salvataggio viene rifiutato (per esempio codice già usato) l’errore compare sul campo e il form resta aperto con quanto hai scritto.',
          ],
        },
      ],
    },
    {
      id: 'codes',
      title: 'Codici',
      blocks: [
        {
          type: 'paragraph',
          text: 'Il codice è **obbligatorio** su ogni voce, al massimo 20 caratteri tra lettere, numeri, punti e trattini (per esempio 61.03.001). Deve essere **univoco in tutto il piano**: mastri, conti e sottoconti (di costo e di ricavo) non possono condividere lo stesso codice. L’albero ordina le voci per codice in ordine naturale (61.2 viene prima di 61.10).',
        },
      ],
    },
    {
      id: 'cost-sub-accounts',
      title: 'Sottoconti di costo = tipi di costo',
      blocks: [
        {
          type: 'paragraph',
          text: 'I sottoconti di costo sono i **tipi di costo** già usati in ordini di acquisto, fatture passive e prodotti: nome, natura (Diretto, Indiretto, Generale), colore e stato si modificano anche dalla pagina **Tipi di costo**, che resta invariata. In più hanno il conto e il codice.',
        },
      ],
    },
    {
      id: 'starting-structure',
      title: 'Struttura di partenza',
      blocks: [
        {
          type: 'paragraph',
          text: 'Al rilascio il piano contiene una struttura di partenza (8 mastri di costo e 3 di ricavo, con i relativi conti, ispirata alle voci civilistiche) e i tipi Collaboratori, Fitti, Utenze e Altro già collocati. È solo una proposta: codici e descrizioni si possono sempre modificare e le voci non usate si possono eliminare, per esempio per adottare il piano del commercialista.',
        },
      ],
    },
    {
      id: 'constraints',
      title: 'Vincoli',
      blocks: [
        {
          type: 'warning',
          text: 'L’eliminazione è restrittiva: non puoi eliminare un mastro che ha conti, un conto che ha sottoconti, né un tipo di costo già usato da ordini, fatture o prodotti. Il motivo compare in un messaggio; disattiva la voce oppure elimina prima le voci collegate.',
        },
      ],
    },
  ],
}

export default guide
