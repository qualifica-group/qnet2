import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'purchase-order-statuses',
  title: 'Stati ODA',
  summary: 'Nome, colore, icona e ordine degli stati degli ordini di acquisto.',
  sections: [
    {
      id: 'overview',
      title: 'A cosa serve',
      blocks: [
        {
          type: 'paragraph',
          text: 'Si configura in **Acquisti › Stati ODA** e decide come gli stati dell’ODA compaiono nelle **schede per stato** della griglia ODA, nei badge, nel filtro per stato e nella finestra **Cambia stato**. Gli stati sono sette e fissi: **Bozza**, **In validazione**, **Approvato**, **Inviato al fornitore**, **Parzialmente evaso**, **Evaso**, **Annullato**.',
        },
        {
          type: 'table',
          headers: ['Campo', 'Cosa indicare'],
          rows: [
            ['Nome', 'Obbligatorio, al massimo 191 caratteri, diverso da quello degli altri stati.'],
            ['Colore', 'Obbligatorio, scelto dalla palette.'],
            ['Icona', 'Facoltativa: se manca, il badge mostra un puntino del colore dello stato.'],
            ['Valore di sistema', 'Sola lettura: è il valore su cui si basano le regole dell’ordine.'],
            ['Ordine', 'Sola lettura nel dettaglio: si cambia con **Riordina**.'],
          ],
        },
        {
          type: 'note',
          text: 'Cambia solo la presentazione: le regole non cambiano. I passaggi di stato consentiti, quando l’ordine è modificabile, l’invio al fornitore e il legame con le righe RDA restano quelli descritti nella guida **ODA**, qualunque nome abbia lo stato.',
        },
      ],
    },
    {
      id: 'editing',
      title: 'Modificare uno stato',
      blocks: [
        {
          type: 'steps',
          items: [
            'Apri la scheda dello stato con **Visualizza**.',
            'Premi la matita sulla riga **Nome**, **Colore** o **Icona**, cambia il valore e premi **Salva** (si salva solo quella riga); **Annulla** o Esc lasciano la riga com’era.',
            'Schede, badge e finestra **Cambia stato** mostrano subito il nuovo nome, colore e icona.',
          ],
        },
        {
          type: 'warning',
          text: 'Non si possono creare né eliminare stati. Il nome è un dato: non viene tradotto, quindi nell’interfaccia in inglese compare così com’è scritto qui. Esportazioni e storico delle attività continuano a riportare il valore di sistema dello stato.',
        },
      ],
    },
    {
      id: 'reordering',
      title: 'Cambiare l’ordine',
      blocks: [
        {
          type: 'steps',
          items: [
            'Premi **Riordina** nella barra in alto: tutti gli stati si possono spostare.',
            'Trascina una riga dalla maniglia **Trascina per riordinare** fino alla nuova posizione.',
            'Rilascia: l’ordine si salva subito e si riflette su schede, finestra **Cambia stato** e ordinamento della colonna Stato.',
          ],
        },
      ],
    },
  ],
}

export default guide
