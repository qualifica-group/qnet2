import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'purchase-request-line-statuses',
  title: 'Stati righe RDA',
  summary: 'Gli Stati righe RDA permettono di rinominare, colorare e riordinare gli stati delle righe di una richiesta di acquisto.',
  sections: [
    {
      id: 'overview',
      title: 'A cosa serve',
      blocks: [
        {
          type: 'paragraph',
          text: 'Si configura in **Acquisti › Stati righe RDA**. Gli stati sono sei e sono predefiniti: **Da approvare**, **Approvato**, **Ordinato**, **Ricevuto**, **Rifiutato** e **Stand by**. Nome, colore e icona che scegli compaiono nelle schede di stato, nei badge delle griglie RDA e Righe RDA, nella finestra **Cambia stato** e nello storico stati.',
        },
        {
          type: 'table',
          headers: ['Campo', 'Cosa indicare'],
          rows: [
            ['Nome', 'Obbligatorio, al massimo 191 caratteri, diverso da quello degli altri stati.'],
            ['Colore', 'Obbligatorio, scelto dalla palette.'],
            ['Icona', 'Facoltativa.'],
            ['Chiave di sistema e Ordine', 'Sola lettura: l’ordine si cambia con **Riordina**.'],
          ],
        },
        {
          type: 'note',
          text: 'Cambiano solo nome, colore, icona e ordine di presentazione. Il significato degli stati e i passaggi consentiti restano quelli del sistema: non si possono aggiungere, eliminare o disattivare stati. Il nome non viene tradotto: è quello che scrivi.',
        },
      ],
    },
    {
      id: 'managing',
      title: 'Modificare uno stato',
      blocks: [
        {
          type: 'steps',
          items: [
            'Apri la scheda con **Visualizza**.',
            'Premi la matita sulla riga da cambiare (Nome, Colore o Icona), modifica il valore e premi **Salva** (si salva solo quella riga); **Annulla** o Esc lasciano la riga com’era.',
            'Il nuovo nome, colore o icona compaiono subito in schede, badge e finestre.',
          ],
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
            'Premi **Riordina** nella barra in alto.',
            'Trascina una riga dalla maniglia **Trascina per riordinare** fino alla nuova posizione.',
            'Rilascia: l’ordine si salva subito e si riflette su schede di stato, finestra Cambia stato e ordinamento della colonna Stato.',
          ],
        },
      ],
    },
  ],
}

export default guide
