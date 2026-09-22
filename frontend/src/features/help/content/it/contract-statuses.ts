import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'contract-statuses',
  title: 'Stati Contratto',
  summary: 'Gli stati che un contratto attraversa, con nome, descrizione, colore e gruppo.',
  sections: [
    {
      id: 'overview',
      title: 'Cosa sono',
      blocks: [
        {
          type: 'paragraph',
          text: 'Per ogni stato indichi **Nome**, **Descrizione**, **Colore**, **Gruppo** (Aperto, In attesa, Chiuso positivo, Chiuso negativo), **Attivo** e **Predefinito**. Gli stati di sistema sono **Da validare** (sempre primo), **Sospeso**, **Annullato** e **Disdetto** (sempre ultimi): di questi cambi solo nome e colore.',
        },
      ],
    },
    {
      id: 'default-status',
      title: 'Stato predefinito',
      blocks: [
        {
          type: 'paragraph',
          text: "Lo stato **Predefinito** è quello di ogni nuovo contratto: ce n'è uno solo, deve essere attivo e si cambia designandone un altro.",
        },
        {
          type: 'warning',
          text: 'Uno stato in uso non si può eliminare.',
        },
      ],
    },
    {
      id: 'reorder-statuses',
      title: "Cambiare l'ordine degli stati",
      blocks: [
        {
          type: 'steps',
          items: [
            'Apri la tabella e premi **Riordina** nella barra in alto.',
            'Trascina una riga dalla maniglia **Trascina per riordinare** fino alla nuova posizione.',
            "Rilascia: l'ordine si salva subito.",
          ],
        },
        {
          type: 'paragraph',
          text: 'Gli stati di sistema restano bloccati in testa o in coda.',
        },
      ],
    },
  ],
}

export default guide
