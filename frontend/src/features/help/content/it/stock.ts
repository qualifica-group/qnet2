import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'stock',
  title: 'Dotazioni',
  summary: 'Dove si trovano i beni fisici a uso interno (notebook, telefoni, arredi) e a chi sono dati in dotazione, con lo storico di ogni movimento.',
  sections: [
    {
      id: 'overview',
      title: 'A cosa serve',
      blocks: [
        {
          type: 'paragraph',
          text: 'La pagina **Dotazioni** tiene la giacenza dei beni fisici a uso interno: per ogni prodotto la quantità presente in ogni sede operativa, libera oppure assegnata a una persona. Ogni combinazione di prodotto, sede e persona è una riga con la propria quantità. Ha due schede, **Giacenze** e **Movimenti**.',
        },
        {
          type: 'warning',
          text: 'Il modulo è visibile solo a chi ha il permesso **Visualizza elenco** sulle Dotazioni; chi non lo ha non vede la voce di menu, né le sezioni Giacenze e Dotazioni nelle schede prodotto e utente.',
        },
      ],
    },
    {
      id: 'tracked-products',
      title: 'Quali prodotti si possono muovere',
      blocks: [
        {
          type: 'paragraph',
          text: 'Solo i prodotti **tracciati**: di tipo **Bene fisico** e con la casella **Uso interno** spuntata (nel modulo Prodotti). **Uso interno** si può scegliere solo su un Bene fisico, altrimenti il modulo mostra un errore sul campo.',
        },
        {
          type: 'note',
          text: 'Finché un prodotto ha giacenza maggiore di zero non puoi togliere Uso interno né cambiarne il tipo. Un Bene fisico continua a essere usabile nelle offerte solo se è anche Vendibile o Utilizzabile come costo.',
        },
      ],
    },
    {
      id: 'balances',
      title: 'Scheda Giacenze',
      blocks: [
        {
          type: 'paragraph',
          text: 'Le righe con quantità maggiore di zero, con prodotto, sede, persona (vuota se la merce è libera), stato **Libera** o **Assegnata**, quantità, unità di misura e data dell’ultimo movimento. Con il permesso **Gestire** le azioni di riga sono **Assegna** (solo righe libere), **Restituisci** (solo righe assegnate), **Trasferisci**, **Scarica** e **Rettifica**. **Storico** è sempre disponibile e mostra i movimenti di quella riga, dal più recente.',
        },
      ],
    },
    {
      id: 'movements',
      title: 'Tipi di movimento',
      blocks: [
        {
          type: 'table',
          headers: ['Movimento', 'Cosa fa'],
          rows: [
            ['Carico', 'Aggiunge merce libera in una sede.'],
            ['Scarico', 'Toglie merce da una sede (per esempio perché dismessa). La nota è obbligatoria.'],
            ['Trasferimento', 'Sposta merce libera, o la dotazione di una persona, in un’altra sede attiva.'],
            ['Assegnazione', 'Dà in dotazione a una persona attiva merce libera di una sede: la dotazione resta legata a quella sede.'],
            ['Restituzione', 'Riporta la dotazione di una persona tra la merce libera della stessa sede.'],
            ['Rettifica', 'Allinea la giacenza a una quantità contata. La nota è obbligatoria; zero cancella la riga.'],
          ],
        },
      ],
    },
    {
      id: 'record-movement',
      title: 'Registrare un movimento',
      blocks: [
        {
          type: 'steps',
          items: [
            'Premi **Carico** o **Trasferimento** in alto a destra, oppure un’azione su una riga (il modulo si apre già compilato).',
            'Nel modulo i campi cambiano col tipo: per esempio **Destinatario** per l’assegnazione, **Sede di destinazione** per il trasferimento, **Quantità contata** per la rettifica.',
            'Indica la quantità e, se serve, la data (non può essere futura) e la nota, poi premi **Registra movimento**.',
          ],
        },
        {
          type: 'note',
          text: 'Non puoi scaricare, trasferire o assegnare più della quantità disponibile nella riga di partenza: il modulo mostra la quantità disponibile. I movimenti non si modificano né si cancellano: per correggere un errore registra un movimento opposto o una rettifica.',
        },
      ],
    },
    {
      id: 'product-and-user',
      title: 'Nelle schede prodotto e utente',
      blocks: [
        {
          type: 'paragraph',
          text: 'La scheda di un prodotto tracciato mostra la sezione **Giacenze** (sede, persona o “In sede”, quantità) e la griglia Prodotti la colonna **Giacenza**. La scheda di un utente mostra la sezione **Dotazioni** con ciò che ha in carico; con il permesso **Gestire** ogni riga ha **Restituisci**, che registra, dopo conferma, la restituzione dell’intera quantità.',
        },
      ],
    },
    {
      id: 'permissions',
      title: 'Chi vede e chi scrive',
      blocks: [
        {
          type: 'table',
          headers: ['Permesso', 'Cosa permette'],
          rows: [
            ['Visualizza elenco', 'Apre la pagina, le giacenze, i movimenti e lo storico; mostra le sezioni nelle schede prodotto e utente.'],
            ['Gestire', 'Registra i movimenti (include la lettura).'],
          ],
        },
      ],
    },
  ],
}

export default guide
