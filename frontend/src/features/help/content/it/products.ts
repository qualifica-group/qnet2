import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'products',
  title: 'Prodotti',
  summary: 'Il catalogo raccoglie tutti i prodotti e servizi da usare nelle offerte e nelle richieste.',
  sections: [
    {
      id: 'overview',
      title: 'Il catalogo prodotti',
      blocks: [
        {
          type: 'paragraph',
          text: 'Le tabelle di supporto del catalogo (categorie, attributi, IVA, unità di misura, tipologie) sono gestite nei rispettivi moduli di configurazione.',
        },
        {
          type: 'tip',
          text: 'Prima di inserire i prodotti, controlla che categorie, aliquote IVA e unità di misura siano già pronte.',
        },
      ],
    },
    {
      id: 'search-products',
      title: 'Cercare un prodotto',
      blocks: [
        {
          type: 'steps',
          items: [
            'Apri Prodotti › Prodotti.',
            'Scrivi nel campo Cerca… in alto nella tabella.',
            "Restringi l'elenco con i filtri sulle colonne: Categoria, Tipologia, Funzione aziendale o Utilizzo in offerta.",
            'Dal menu Azioni della riga scegli Visualizza per vedere la scheda, oppure Modifica per cambiarla.',
          ],
        },
      ],
    },
    {
      id: 'create-a-product',
      title: 'Creare un prodotto',
      blocks: [
        {
          type: 'steps',
          items: [
            'Premi Nuovo prodotto.',
            "Compila le sezioni nell'ordine: Anagrafica, Classificazione, Prezzi e fornitura, Attributi.",
            'Controlla il riquadro Riepilogo a destra.',
            'Premi Salva. Compare "Prodotto creato con successo."',
          ],
        },
      ],
    },
    {
      id: 'identity-section',
      title: 'Sezione Anagrafica',
      blocks: [
        {
          type: 'table',
          headers: ['Campo', 'Cosa indicare'],
          rows: [
            ['Codice', 'QNet propone il codice successivo della numerazione, ma puoi cambiarlo. Obbligatorio, al massimo 32 caratteri.'],
            ['Nome', 'Il nome del prodotto. Obbligatorio.'],
            ['Descrizione', 'Una descrizione libera.'],
          ],
        },
        {
          type: 'tip',
          text: 'Se non hai un codice tuo, lascia quello proposto: la numerazione resta ordinata.',
        },
      ],
    },
    {
      id: 'classification-section',
      title: 'Sezione Classificazione',
      blocks: [
        {
          type: 'paragraph',
          text: 'Questa sezione dice dove si trova il prodotto nel catalogo e dove si può usare.',
        },
        {
          type: 'table',
          headers: ['Campo', 'Cosa indicare'],
          rows: [
            ['Categoria', 'La categoria del prodotto. Obbligatoria.'],
            ['Tipologia', "Scelta dall'elenco Tipologie Prodotto."],
            ['Unità di misura', 'Per esempio ore o pezzi.'],
            ['Tipo', "Oggi l'unico valore è Servizio."],
            ['Utilizzo in offerta', "Dove si può usare il prodotto in un'offerta (vedi sotto)."],
          ],
        },
        {
          type: 'warning',
          text: 'Scegli per prima la Categoria: da lei dipendono gli attributi che compaiono più in basso.',
        },
        {
          type: 'paragraph',
          text: "Alcune categorie compaiono nell'elenco ma non si possono scegliere: servono solo a raggruppare le sottocategorie. In quel caso scegli una sottocategoria. La Funzione aziendale del prodotto deriva dalla categoria e nella scheda è in sola lettura.",
        },
      ],
    },
    {
      id: 'offer-usage',
      title: 'Utilizzo in offerta',
      blocks: [
        {
          type: 'paragraph',
          text: 'Due caselle indipendenti: puoi spuntarne una o entrambe.',
        },
        {
          type: 'table',
          headers: ['Casella', 'Effetto'],
          rows: [
            ['Vendibile', "Il prodotto si può scegliere nella scheda Prodotti di un'offerta e nelle righe offerta di Gestione Richieste."],
            ['Utilizzabile come costo', "Il prodotto si può scegliere nella scheda Costi di un'offerta."],
          ],
        },
        {
          type: 'list',
          items: [
            'In un prodotto nuovo, Vendibile è già spuntata.',
            'Serve almeno una casella, altrimenti compare "Seleziona almeno un utilizzo."',
            'Una voce di spesa, come un viaggio in treno o un albergo, di solito va segnata solo come Utilizzabile come costo.',
          ],
        },
        {
          type: 'warning',
          text: 'Un prodotto non abilitato per una scheda non compare tra quelli selezionabili lì. Se in seguito togli una casella, le offerte che lo usano già restano salvabili, ma non potrai aggiungerlo in nuove righe di quella scheda.',
        },
        {
          type: 'tip',
          text: "Quando converti un lead, i suoi prodotti di interesse non vendibili non diventano righe dell'offerta.",
        },
      ],
    },
    {
      id: 'pricing-and-supply-section',
      title: 'Sezione Prezzi e fornitura',
      blocks: [
        {
          type: 'table',
          headers: ['Campo', 'Cosa indicare'],
          rows: [
            ['Costo', 'Quanto costa a te il prodotto. Obbligatorio, zero o positivo.'],
            ['Prezzo', 'Il prezzo di vendita. Obbligatorio, zero o positivo.'],
            ['IVA', "L'aliquota IVA, dall'elenco IVA."],
            ['Fornitore', 'Chi fornisce il prodotto.'],
          ],
        },
        {
          type: 'paragraph',
          text: 'Sotto costo e prezzo compare il Margine (prezzo meno costo) con la percentuale sul prezzo. Si aggiorna mentre scrivi.',
        },
        {
          type: 'warning',
          text: "Nel campo Fornitore compaiono solo le anagrafiche segnate come Fornitore. Se non trovi un fornitore, apri la sua anagrafica e attiva l'opzione.",
        },
      ],
    },
    {
      id: 'attributes-section',
      title: 'Sezione Attributi',
      blocks: [
        {
          type: 'paragraph',
          text: 'Gli attributi sono campi in più stabiliti dalla categoria, per esempio la durata di un corso o il livello di un servizio.',
        },
        {
          type: 'list',
          items: [
            'Senza categoria la sezione mostra: "Seleziona una categoria per vedere i suoi attributi."',
            'Scelta la categoria compaiono i suoi campi, compresi quelli ereditati dalle categorie superiori.',
            'La categoria può raggrupparli in più sezioni; quelli fuori gruppo finiscono in Altre informazioni.',
            'Gli attributi indicati come Obbligatorio nella categoria vanno compilati per forza.',
          ],
        },
        {
          type: 'tip',
          text: 'Se manca un attributo, chi gestisce il catalogo può aggiungerlo alla categoria in Categorie Prodotto.',
        },
      ],
    },
    {
      id: 'product-detail',
      title: 'Il dettaglio prodotto',
      blocks: [
        {
          type: 'paragraph',
          text: 'Dal menu Azioni scegli Visualizza per aprire la scheda in sola lettura. Trovi codice e nome, il Margine in evidenza, le sezioni Anagrafica, Classificazione e Prezzi e fornitura, i valori degli attributi, lo storico delle modifiche (se hai il permesso) e la data Creato il.',
        },
        {
          type: 'paragraph',
          text: 'Per cambiare i dati usa Modifica. Per eliminare un prodotto scegli Elimina dal menu Azioni e conferma.',
        },
      ],
    },
    {
      id: 'cost-item-flow',
      title: 'Flusso tipico: inserire una voce di costo',
      blocks: [
        {
          type: 'steps',
          items: [
            'Premi Nuovo prodotto.',
            'Lascia il Codice proposto e scrivi il Nome, per esempio "Trasferta in treno".',
            'Scegli la Categoria giusta.',
            'In Utilizzo in offerta togli Vendibile e spunta Utilizzabile come costo.',
            "Inserisci Costo e Prezzo, poi scegli l'IVA.",
            'Compila gli attributi richiesti e premi Salva.',
          ],
        },
        {
          type: 'paragraph',
          text: 'Da questo momento la voce si può scegliere nella scheda Costi delle offerte.',
        },
      ],
    },
  ],
}

export default guide
