import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'quote-system-statuses',
  title: 'Stati di sistema offerta',
  summary: "Gli stati che si vedono in Offerte, collegati agli stati di lavorazione di Gestione Richieste.",
  sections: [
    {
      id: 'overview',
      title: 'Cosa sono',
      blocks: [
        {
          type: 'paragraph',
          text: "Si trovano nel menu **Configurazione › Commerciale** (pagina **Stati di sistema offerta**). In **Offerte** non compaiono gli stati di lavorazione di Gestione Richieste ma questi stati di sistema. Si possono modificare, aggiungere ed eliminare (se non usati). Ogni stato di lavorazione di un workflow offerta si collega a uno stato di sistema: cambiando lo stato in Offerte cambia in Gestione Richieste e viceversa.",
        },
        {
          type: 'paragraph',
          text: 'Per ogni stato indichi **Nome**, **Colore** e **Comportamento**. Gli stati iniziali sono **Aperto**, **In attesa**, **Vinto provvisorio** (al posto del vecchio Validato), **Vinto** e **Perso**. Nel dettaglio vedi anche **Stati di lavorazione collegati**.',
        },
      ],
    },
    {
      id: 'behavior',
      title: 'Il comportamento',
      blocks: [
        {
          type: 'paragraph',
          text: 'Il **Comportamento** (Aperto, In attesa, Vinto provvisorio, Vinto, Perso) decide cosa fa lo stato: con **Vinto** nasce il contratto, con **Vinto provvisorio** e **Vinto** l\'offerta compare in Gestione Iscritti, **Vinto provvisorio** richiede almeno una riga prodotto, **Vinto** e **Perso** chiudono l\'opportunità.',
        },
        {
          type: 'warning',
          text: 'Aperto, Vinto e Perso sono obbligatori: non si eliminano e il loro comportamento è fisso (cambi solo nome e colore). Cambiare il comportamento di uno stato collegato non crea né sospende contratti sulle offerte già in quello stato.',
        },
      ],
    },
    {
      id: 'managing',
      title: 'Creare e modificare',
      blocks: [
        {
          type: 'steps',
          items: [
            'Premi **Nuovo stato**.',
            "Si apre la scheda con le righe chiuse: per ogni riga premi la matita (o il valore), compila e premi **Fatto**; **Ripristina** riporta la riga com'era. Poi premi **Salva**.",
            "Per modificare apri la scheda con **Visualizza**, premi la matita sulla riga, cambia il valore e premi **Salva** (si salva solo quella riga); **Annulla** o Esc lasciano la riga com'era.",
          ],
        },
        {
          type: 'warning',
          text: "Uno stato collegato a stati di lavorazione non si può eliminare: il messaggio elenca gli stati di lavorazione che lo usano. Scollegali prima (nel Configuratore Stati Offerta).",
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
          text: '**Aperto** resta in testa, **Vinto** e **Perso** restano in coda. Quando scegli uno stato di sistema in Offerte, l\'offerta passa al primo stato di lavorazione collegato, nell\'ordine configurato.',
        },
      ],
    },
  ],
}

export default guide
