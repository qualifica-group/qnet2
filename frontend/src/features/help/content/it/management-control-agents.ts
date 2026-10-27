import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'management-control-agents',
  title: 'Agenti',
  summary: 'Per ogni agente, le provvigioni maturate nel periodo, quelle ancora da liquidare, in estratto conto, approvate e non pagate, e il pagato.',
  sections: [
    {
      id: 'overview',
      title: 'Cos’è',
      blocks: [
        {
          type: 'paragraph',
          text: 'La pagina **Agenti** risponde alla domanda “quanto abbiamo maturato con ciascun agente, quanto resta da liquidare e quanto abbiamo già pagato?”. La trovi in **Contabilità › Controllo di gestione › Agenti**. È **solo di consultazione** e mostra importi aggregati; per il dettaglio dei singoli movimenti usa **Provvigioni agenti › Maturato** ed **Estratti**.',
        },
        {
          type: 'paragraph',
          text: 'In alto trovi 6 riquadri: **Maturato**, **Da liquidare**, **In estratto conto** (con il numero di estratti ancora **in attesa del documento** dell’agente), **Approvato non pagato**, **Pagato** e il numero di **Agenti**. Sotto, la tabella per agente e i grafici.',
        },
      ],
    },
    {
      id: 'filters',
      title: 'Filtri e periodo',
      blocks: [
        {
          type: 'paragraph',
          text: 'Di default il periodo va dal **1 gennaio a oggi**; può estendersi al massimo a **24 mesi**. Puoi restringere per **società** e scegliere quanti agenti mostrare (**primi 10, 20 o 50**): gli altri sono sommati in una riga **Altri**. Il periodo vale per **maturato** (data di maturazione) e **pagato** (data di pagamento delle rate); **da liquidare**, **in estratto conto** e **approvato non pagato** sono sempre una fotografia **a oggi**.',
        },
        {
          type: 'note',
          text: 'Gli importi maturati sono imponibili, come nelle provvigioni; estratti e pagamenti sono gli importi registrati (netto da pagare e importo pagato).',
        },
      ],
    },
    {
      id: 'agents',
      title: 'Agenti',
      blocks: [
        {
          type: 'list',
          items: [
            '**Maturato**: somma, con segno, delle provvigioni maturate nel periodo. Gli storni la riducono.',
            '**Da liquidare**: provvigioni maturate non ancora inserite in un estratto conto.',
            '**In estratto conto**: netto da pagare degli estratti emessi o con documento ricevuto. Il conteggio **in attesa documento** sono gli estratti emessi per cui l’agente non ha ancora mandato il documento.',
            '**Approvato non pagato**: netto da pagare degli estratti approvati, meno quanto della loro fattura passiva è già stato pagato.',
            '**Pagato**: quanto è stato pagato nel periodo sulle rate della fattura passiva collegata all’estratto. Gli estratti annullati non contano.',
          ],
        },
        {
          type: 'paragraph',
          text: 'La tabella ordina gli agenti per importo **da liquidare** (a parità, per maturato), e chiude con la riga **Altri** (se ci sono altri agenti) e il **Totale**. Sono considerati agenti i referenti destinatari delle provvigioni, qualunque sia il loro ruolo.',
        },
      ],
    },
    {
      id: 'months',
      title: 'Andamento per mese',
      blocks: [
        {
          type: 'paragraph',
          text: 'Due grafici affiancano **maturato e pagato**: uno per agente, l’altro per mese, anche per i mesi senza movimenti.',
        },
      ],
    },
    {
      id: 'permissions',
      title: 'Permessi',
      blocks: [
        {
          type: 'paragraph',
          text: 'Serve il permesso **Controllo di gestione** (dal form Ruoli), lo stesso delle altre pagine del modulo. Chi lo ha vede i dati di **tutte** le società. Senza il permesso la voce di menu non compare.',
        },
      ],
    },
  ],
}

export default guide
