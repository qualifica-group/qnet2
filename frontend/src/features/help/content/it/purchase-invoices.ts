import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'purchase-invoices',
  title: 'Fatture passive',
  summary: 'Fatture e proforma dei fornitori: registrazione da zero o dagli ODA, righe con tipo di costo e commessa, scadenze e allegati.',
  sections: [
    {
      id: 'overview',
      title: 'Cos’è',
      blocks: [
        {
          type: 'paragraph',
          text: 'Il modulo si trova in **Contabilità › Passiva › Fatture passive**. Raccoglie le **fatture e i proforma ricevuti dai fornitori**: ogni documento ha le sue **righe** (ciascuna con **tipo di costo** e, se serve, **commessa**), le **scadenze** di pagamento e gli **allegati** del fornitore.',
        },
        {
          type: 'paragraph',
          text: 'Un documento può nascere **da zero** oppure **da uno o più ODA** dello stesso fornitore e della stessa società. Gli ODA collegati sono sempre visibili nella scheda del documento.',
        },
      ],
    },
    {
      id: 'list',
      title: 'L’elenco',
      blocks: [
        {
          type: 'paragraph',
          text: 'L’elenco ha **una riga per documento**: tipo, numero del fornitore, data, fornitore, società, ODA collegati, **totale**, **pagato**, **residuo** e **stato del pagamento**. Come in ogni elenco puoi ordinare, cercare, filtrare, salvare viste ed esportare.',
        },
        {
          type: 'table',
          headers: ['Azione', 'Cosa fa'],
          rows: [
            ['**Visualizza**', 'Apre la scheda del documento: righe, scadenze, ODA collegati e documenti.'],
            ['**Modifica**', 'Apre l’editor (solo proforma, vedi “Proforma e fattura”).'],
            ['**Dettagli fornitore**', 'Numero e data del documento del fornitore e note.'],
            ['**Elimina**', 'Elimina il documento dopo conferma (solo proforma senza pagamenti).'],
            ['**Attività**', 'Storico delle modifiche del documento.'],
          ],
        },
        {
          type: 'note',
          text: 'Le azioni che vedi dipendono dai tuoi permessi e dallo stato del documento: quelle non consentite non compaiono.',
        },
      ],
    },
    {
      id: 'create',
      title: 'Registrare un documento',
      blocks: [
        {
          type: 'steps',
          items: [
            'Premi **Nuova fattura passiva** e scegli **Fornitore**, **Società**, **Data documento** e **Modalità di pagamento** (e, se serve, conto, sedi, note).',
            'Aggiungi le **righe**: dal catalogo prodotti oppure libere. Per ogni riga indica descrizione, quantità, prezzo, IVA, **tipo di costo** (obbligatorio) e **commessa**. Il tipo di costo e la commessa di testata sono solo il valore proposto alle nuove righe.',
            'Controlla i **totali** e l’**anteprima delle scadenze**, generate dalla modalità di pagamento.',
            'Premi **Salva**.',
          ],
        },
        {
          type: 'list',
          items: [
            'Un documento ha da 1 a 200 righe.',
            'Gli importi di riga si calcolano con la quantità a 3 decimali; l’IVA vuota vale 0. I totali del documento sono la somma delle righe.',
            'Fornitore e società **non si cambiano** dopo la creazione.',
          ],
        },
      ],
    },
    {
      id: 'from-orders',
      title: 'Registrare dagli ODA',
      blocks: [
        {
          type: 'paragraph',
          text: 'Dal dettaglio di un ODA **Approvato**, **Inviato al fornitore**, **Parzialmente evaso** o **Evaso** premi **Registra fattura**: l’editor si apre con le righe dell’ordine già compilate (descrizione, prodotto, quantità, prezzo, IVA, tipo di costo, commessa) e l’ODA collegato. Puoi aggiungere altri ODA dello stesso fornitore e società e modificare, aggiungere o togliere righe.',
        },
        {
          type: 'warning',
          text: 'Una riga d’ordine si può fatturare **una sola volta**: le righe già presenti su un’altra fattura passiva non vengono proposte e un tentativo di riutilizzarle viene rifiutato. Non puoi mescolare ODA di fornitori o società diversi.',
        },
      ],
    },
    {
      id: 'proforma-invoice',
      title: 'Proforma e fattura',
      blocks: [
        {
          type: 'paragraph',
          text: 'Come per le fatture attive, il documento nasce come **proforma**. Diventa **fattura** quando, da **Dettagli fornitore**, inserisci il **numero** e la **data** del documento del fornitore.',
        },
        {
          type: 'list',
          items: [
            'Una **fattura** è **bloccata**: righe, importi, fornitore, data ed eliminazione sono rifiutati.',
            'Cambiare o svuotare numero e data di una fattura richiede il permesso **Correggi/annulla registrazione** e viene registrato nell’attività; svuotando il numero il documento torna proforma.',
            'Il numero del fornitore deve essere **unico** per lo stesso fornitore e lo stesso anno tra le fatture; non vale per i proforma.',
          ],
        },
      ],
    },
    {
      id: 'installments',
      title: 'Scadenze e pagamenti',
      blocks: [
        {
          type: 'paragraph',
          text: 'Le scadenze sono generate dalla **modalità di pagamento** e, in modifica di un proforma senza pagamenti, vengono **ricalcolate**. Se esistono già pagamenti, data, modalità e fornitore sono congelati e le scadenze si **ribilanciano** lasciando intatte le rate pagate. I pagamenti si registrano da **Contabilità › Passiva › Scadenze**, dove l’azione **Apri documento** porta alla scheda del documento.',
        },
        {
          type: 'note',
          text: 'Un documento con pagamenti registrati non si elimina: annulla prima i pagamenti.',
        },
      ],
    },
    {
      id: 'documents',
      title: 'Documenti allegati',
      blocks: [
        {
          type: 'paragraph',
          text: 'Nella scheda del documento la sezione **Documenti** conserva il PDF o gli altri file del fornitore. Caricare ed eliminare file richiede i permessi sugli allegati. Eliminando il documento, anche i suoi allegati vengono rimossi.',
        },
      ],
    },
    {
      id: 'permissions',
      title: 'Permessi',
      blocks: [
        {
          type: 'table',
          headers: ['Azione', 'Permesso'],
          rows: [
            ['Vedere l’elenco e la scheda', 'Visualizzazione fatture passive'],
            ['**Nuova fattura passiva**, **Registra fattura** dall’ODA', 'Creazione fatture passive'],
            ['**Modifica**, **Dettagli fornitore**', 'Modifica fatture passive'],
            ['**Elimina**', 'Eliminazione fatture passive'],
            ['Cambiare o svuotare numero e data di una fattura', 'Correggi/annulla registrazione'],
            ['**Esporta**, **Attività**', 'Esportazione e visualizzazione attività'],
          ],
        },
      ],
    },
  ],
}

export default guide
