import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'payment-methods',
  title: 'Modalità di Pagamento',
  summary: 'Le Modalità di Pagamento sono le modalità di pagamento proposte al cliente, usate nel campo Metodo di pagamento delle Offerte.',
  sections: [
    {
      id: 'overview',
      title: 'Panoramica',
      blocks: [
        { type: 'paragraph', text: 'Il modulo si trova in **Configurazione › Modalità di Pagamento**.' },
      ],
    },
    {
      id: 'fields',
      title: 'Campi',
      blocks: [
        {
          type: 'table',
          headers: ['Campo', 'Cosa indicare'],
          rows: [
            ['**Codice**', 'Identificativo interno. Non si modifica dopo la creazione.'],
            ['**Codice modalità di pagamento**', 'Codice per l’esterno, ad esempio MP01.'],
            ['**Descrizione**', 'Descrizione della modalità di pagamento.'],
            ['**Istruzioni di pagamento**', 'Testo con le istruzioni per il cliente.'],
            ['**Giorni di pagamento**', 'Giorni alla **prima scadenza**, contati dalla data del documento.'],
            ['**Numero di rate**', 'In quante rate si divide il pagamento (1 = rata unica).'],
            ['**Giorni tra le rate**', 'Distanza in giorni tra una rata e la successiva (con più di una rata).'],
            ['**Fine mese**', 'Sposta ogni scadenza a fine mese; puoi aggiungere **giorni extra** (ad esempio FM+10 = fine mese più 10 giorni).'],
            ['**Ripartizione IVA**', 'Come si distribuisce l’IVA sulle rate: **proporzionale** su tutte, **tutta sulla prima**, **tutta sull’ultima**, oppure **prima rata solo IVA**. Le ultime tre richiedono almeno 2 rate.'],
            ['**Attivo**', 'Se lo spegni, la modalità sparisce dai menu a tendina ma i record che la usano restano intatti.'],
          ],
        },
      ],
    },
    {
      id: 'installments',
      title: 'Rate e scadenze',
      blocks: [
        { type: 'paragraph', text: 'Quando emetti un proforma (vedi la guida Fatture Attive) le **scadenze** si calcolano dalla modalità di pagamento scelta: la prima a **Giorni di pagamento** dalla data del documento, le successive ogni **Giorni tra le rate**, con l’eventuale fine mese. Gli importi seguono la **Ripartizione IVA**.' },
        { type: 'tip', text: 'Esempio: 3 rate, 30 giorni tra le rate, fine mese + 10 giorni, IVA proporzionale.' },
      ],
    },
    {
      id: 'manage',
      title: 'Creare, modificare, riordinare ed eliminare',
      blocks: [
        { type: 'steps', items: ['Apri **Configurazione › Modalità di Pagamento** e premi **Nuova modalità di pagamento**.', 'Compila i campi (vedi tabella sopra).', 'Premi **Salva**.'] },
        { type: 'paragraph', text: 'Sulle righe dell’elenco trovi **Visualizza** ed **Elimina**, se il tuo ruolo lo consente. Per modificare apri la scheda con **Visualizza** e premi **Modifica**. **Riordina** cambia l’ordine con cui le modalità compaiono nei menu a tendina.' },
        { type: 'warning', text: 'Non puoi eliminare una modalità di pagamento già usata: disattivala invece.' },
      ],
    },
  ],
}

export default guide
