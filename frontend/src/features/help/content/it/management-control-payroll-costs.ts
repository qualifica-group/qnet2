import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'management-control-payroll-costs',
  title: 'Costi del personale',
  summary: 'Il file mensile dei costi del personale del consulente del lavoro, caricato in anteprima e poi confermato, e il costo reale del lavoro per persona.',
  sections: [
    {
      id: 'overview',
      title: 'Cos’è',
      blocks: [
        {
          type: 'paragraph',
          text: 'La pagina **Costi del personale** si trova in **Contabilità › Controllo di gestione › Costi del personale**. Ogni mese carichi il **file costi** del consulente del lavoro: il costo reale di ogni persona viene distribuito sulle commesse e sulle funzioni in base alle ore registrate nel segnatempo, al posto della stima da contratto. La tabella mostra, per persona, società e mese, il costo, le ore e la parte di costo **non ribaltata** sulle commesse. È di sola consultazione: le righe non si modificano né si cancellano a mano.',
        },
      ],
    },
    {
      id: 'import',
      title: 'Importare il file del mese',
      blocks: [
        {
          type: 'steps',
          items: [
            'Premi **Importa** (il pulsante compare solo con il permesso di importazione).',
            'Scegli il file (**csv, txt o xlsx**): viene letto subito e vedi l’**anteprima** senza che venga scritto nulla.',
            'Controlla **mese**, numero di righe, persone e società, e **costo totale**. Se il mese era già caricato, l’avviso indica quante righe verranno **sostituite**.',
            'Premi **Conferma importazione**: il file viene applicato e la tabella si aggiorna.',
          ],
        },
        {
          type: 'paragraph',
          text: 'Un file contiene **un solo mese**. Importare di nuovo un mese già caricato lo **sostituisce per intero**: le righe di quel mese sono cancellate e riscritte, gli altri mesi non vengono toccati. Le colonne obbligatorie sono **AZIENDA**, **Codice fiscale dipendente**, **Mese da**, **Anno da**, **Costo retribuzione**, **Costo contributivo**, **Costo inail**, **Ore ordinarie** e **TOTALE COSTO MENSILE**; **Data assunzione** e **Data cessazione** sono facoltative.',
        },
      ],
    },
    {
      id: 'errors',
      title: 'Errori nel file',
      blocks: [
        {
          type: 'paragraph',
          text: 'L’importazione è **tutto o niente**: se anche una sola riga ha un errore, non viene scritto nulla e **Conferma importazione** resta disattivato. Ogni errore indica **riga** e **colonna** del file, per esempio codice fiscale non trovato o presente su più utenti, società non associata, importo non numerico, totale diverso dalla somma di retribuzione, contributi e INAIL, stessa persona ripetuta nella stessa società, mese diverso dalle altre righe. Correggi il file e caricalo di nuovo.',
        },
        {
          type: 'tip',
          text: 'La persona si riconosce dal **codice fiscale** della scheda utente: compilalo prima dell’importazione per tutte le persone del file.',
        },
      ],
    },
    {
      id: 'columns',
      title: 'Le colonne della tabella',
      blocks: [
        {
          type: 'table',
          headers: ['Colonna', 'Significato'],
          rows: [
            ['**Mese**, **Società**, **Persona**, **Codice fiscale**', 'Chi e a quale mese si riferisce la riga; la stessa persona può comparire su più società.'],
            ['**Costo retribuzione**, **contributivo**, **INAIL**, **totale**', 'Gli importi del file; il totale è la somma dei tre. Il costo contributivo può essere negativo (conguagli).'],
            ['**Ore ordinarie**, **assunzione**, **cessazione**', 'I dati del file.'],
            ['**Ore segnatempo**', 'Tutte le ore registrate dalla persona nel mese, con e senza commessa.'],
            ['**Ore su commessa**', 'Le ore del mese registrate su una commessa.'],
            ['**Quota su commesse**', 'Costo totale della persona nel mese, in proporzione alle ore su commessa sulle ore del segnatempo.'],
            ['**Quota non ribaltata**', 'La parte restante del costo, che non finisce su nessuna commessa.'],
          ],
        },
        {
          type: 'note',
          text: 'Le colonne calcolate (ore segnatempo, ore su commessa, quota su commesse, quota non ribaltata) non si possono ordinare né filtrare.',
        },
      ],
    },
    {
      id: 'labor-cost',
      title: 'Effetto sul costo del lavoro',
      blocks: [
        {
          type: 'paragraph',
          text: 'Nei mesi con un costo importato, il **costo orario** di una persona è il suo costo del mese (somma di tutte le società) diviso per le **ore registrate nel segnatempo** in quel mese. Il costo di chi non ha ore registrate non va su nessuna commessa: confluisce nelle sue **funzioni aziendali** o, senza appartenenze, nella **struttura**. Nei mesi senza costo importato resta la **stima dalla condizione contrattuale**. Il costo di tredicesima e quattordicesima sta nel mese del file in cui il consulente lo riporta.',
        },
      ],
    },
    {
      id: 'permissions',
      title: 'Permessi',
      blocks: [
        {
          type: 'paragraph',
          text: 'La voce di menu e la tabella richiedono il permesso **Costi del personale › Visualizza**; il pulsante **Importa** richiede anche **Importa**. I permessi si assegnano dal form Ruoli.',
        },
      ],
    },
  ],
}

export default guide
