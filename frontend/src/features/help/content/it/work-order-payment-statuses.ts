import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'work-order-payment-statuses',
  title: 'Stati pagamento commessa',
  summary: 'Gli stati di pagamento che si assegnano alle righe di una commessa, con il flag che indica se si può consegnare.',
  sections: [
    {
      id: 'overview',
      title: 'A cosa servono',
      blocks: [
        {
          type: 'paragraph',
          text: 'Si configurano in **Configurazione › Stati pagamento commessa**. Gli stati si scelgono, riga per riga, nel tab **Dati contrattuali** del dettaglio commessa.',
        },
        {
          type: 'table',
          headers: ['Campo', 'Cosa indicare'],
          rows: [
            ['Nome', 'Obbligatorio.'],
            ['Descrizione', 'Facoltativa.'],
            ['Colore', 'Obbligatorio: è il pallino mostrato accanto al nome.'],
            ['Attivo', 'Se lo spegni, lo stato sparisce dal menu a tendina delle righe; chi lo ha già assegnato lo mantiene.'],
            ['Si può consegnare', 'Se acceso, quando una riga passa a questo stato supervisori e partecipanti della commessa ricevono una notifica.'],
          ],
        },
      ],
    },
    {
      id: 'reordering',
      title: "Cambiare l'ordine",
      blocks: [
        {
          type: 'steps',
          items: [
            'Apri **Stati pagamento commessa** e premi **Riordina** nella barra in alto.',
            'Trascina una riga dalla maniglia **Trascina per riordinare** fino alla nuova posizione.',
            "Rilascia: l'ordine si salva subito.",
          ],
        },
      ],
    },
    {
      id: 'constraints',
      title: 'Vincoli',
      blocks: [
        {
          type: 'warning',
          text: 'Uno stato in uso su una riga commessa non si può eliminare: disattivalo invece di eliminarlo.',
        },
      ],
    },
  ],
}

export default guide
