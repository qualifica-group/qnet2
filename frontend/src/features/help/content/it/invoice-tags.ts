import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'invoice-tags',
  title: 'Tag fattura',
  summary: 'Il Tag fattura classifica una fattura attiva (per esempio Preventivo o Consuntivo).',
  sections: [
    {
      id: 'overview',
      title: 'A cosa serve',
      blocks: [
        {
          type: 'paragraph',
          text: 'Si configura in **Configurazione › Contabilità › Tag fattura** e compare nel campo **Tag** della fattura, nella testata dell’editor e nei **Dettagli**, e come colonna con filtro nell’elenco. Il tag è facoltativo: puoi lasciare **Nessun tag**.',
        },
        {
          type: 'table',
          headers: ['Campo', 'Cosa indicare'],
          rows: [
            ['Nome', 'Obbligatorio, al massimo 191 caratteri.'],
            ['Descrizione', 'Facoltativa, al massimo 500 caratteri.'],
            ['Colore', 'Obbligatorio: è il colore del badge in elenco e nel dettaglio fattura.'],
            ['Attivo', 'Se lo spegni, la voce sparisce dal menu a tendina; le fatture che la usano la mantengono.'],
          ],
        },
        {
          type: 'note',
          text: 'Esistono già **Preventivo** e **Consuntivo**: puoi rinominarli, ricolorarli, disattivarli o aggiungerne altri. Il nome del tag è disponibile nei layout dei documenti con la variabile **tag_label**.',
        },
      ],
    },
    {
      id: 'managing',
      title: 'Creare, modificare, disattivare',
      blocks: [
        {
          type: 'steps',
          items: [
            'Premi **Nuovo tag**.',
            'Si apre la scheda con le righe chiuse: per ogni riga premi la matita (o il valore), compila e premi **Fatto**; **Ripristina** riporta la riga com’era. Poi premi **Salva**.',
            'Per modificare non c’è un modulo a parte: apri la scheda con **Visualizza**, premi la matita sulla riga, cambia il valore e premi **Salva** (si salva solo quella riga); **Annulla** o Esc lasciano la riga com’era.',
            'Dall’elenco puoi anche modificare direttamente nelle celle **Nome**, **Descrizione**, **Colore** (scegli dalla palette) e **Attivo**: fai doppio clic sulla cella, cambia il valore e conferma.',
            'Per disattivare, premi la matita su **Attivo** e spegnilo: le fatture che lo usano restano intatte.',
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
            'Rilascia: l’ordine si salva subito e si riflette su tabella e menu a tendina.',
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
          text: 'Un tag in uso su una fattura non si può eliminare: disattivalo invece di eliminarlo.',
        },
      ],
    },
  ],
}

export default guide
