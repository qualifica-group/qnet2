import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'purchase-request-priorities',
  title: 'Priorità RDA',
  summary: 'La Priorità RDA indica l’urgenza di una richiesta di acquisto.',
  sections: [
    {
      id: 'overview',
      title: 'A cosa serve',
      blocks: [
        {
          type: 'paragraph',
          text: 'Si configura in **Acquisti › Priorità RDA** e compare nel campo **Priorità** della RDA, come badge colorato nelle griglie RDA e Righe RDA. Le cinque voci iniziali (Bassa, Media, Alta, Urgente, Critica) si possono rinominare, ricolorare, riordinare o disattivare, e se ne possono aggiungere altre.',
        },
        {
          type: 'table',
          headers: ['Campo', 'Cosa indicare'],
          rows: [
            ['Nome', 'Obbligatorio, al massimo 191 caratteri.'],
            ['Descrizione', 'Facoltativa, al massimo 500 caratteri.'],
            ['Colore', 'Obbligatorio.'],
            ['Icona', 'Facoltativa.'],
            ['Attiva', 'Se la spegni, la voce sparisce dai menu a tendina.'],
            ['Predefinita', 'Se attiva, precompila il campo Priorità sulle nuove RDA.'],
          ],
        },
        {
          type: 'note',
          text: 'Solo una priorità alla volta può essere predefinita: attivarla su una la disattiva automaticamente sulle altre. Una priorità disattivata non può essere predefinita.',
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
            'Premi **Nuova priorità**.',
            'Si apre la scheda con le righe chiuse: per ogni riga premi la matita (o il valore), compila e premi **Fatto**; **Ripristina** riporta la riga com’era. Poi premi **Salva**.',
            'Per modificare non c’è un modulo a parte: apri la scheda con **Visualizza**, premi la matita sulla riga, cambia il valore e premi **Salva** (si salva solo quella riga); **Annulla** o Esc lasciano la riga com’era.',
            'Dall’elenco puoi anche modificare direttamente nelle celle **Nome**, **Descrizione**, **Colore** (scegli dalla palette), **Attiva** e **Predefinita**: fai doppio clic sulla cella, cambia il valore e conferma. Rendendo predefinita una priorità, la precedente smette di esserlo. L’icona si cambia dalla scheda.',
            'Per disattivare, premi la matita su **Attiva** e spegnila: le RDA che la usano restano intatte.',
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
            'Rilascia: l’ordine si salva subito e si riflette su tabella, menu a tendina e ordinamento della colonna Priorità.',
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
          text: 'Una priorità in uso su una RDA non si può eliminare: disattivala invece di eliminarla.',
        },
      ],
    },
  ],
}

export default guide
