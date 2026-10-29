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
        { type: 'paragraph', text: 'Il modulo si trova in **Configurazione › Contabilità › Modalità di Pagamento**.' },
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
            ['**Condizioni di pagamento**', 'Condizione FatturaPA: **TP01** a rate, **TP02** pagamento completo, **TP03** pagamento anticipato.'],
            ['**Descrizione**', 'Descrizione della modalità di pagamento.'],
            ['**Istruzioni di pagamento**', 'Testo con le istruzioni per il cliente.'],
            ['**Giorni di pagamento**', 'Giorni alla **prima scadenza**, contati dalla data del documento.'],
            ['**Numero di rate**', 'In quante rate si divide il pagamento (1 = rata unica).'],
            ['**Giorni tra le rate**', 'Distanza in giorni tra una rata e la successiva (con più di una rata).'],
            ['**Fine mese**', 'Sposta ogni scadenza a fine mese (DF FM). Spento, la scadenza si conta dalla data fattura (DF).'],
            ['**Giorni aggiuntivi alla scadenza**', 'Giorni sommati a ogni scadenza, con o senza fine mese: ad esempio FM+10 = il 10 del mese successivo. Nel vecchio QNet era il «Giorno scadenza fissa».'],
            ['**Mesi esclusi**', 'Fino a due mesi (ad esempio agosto e dicembre): una scadenza che cade in un mese escluso slitta di **10 giorni**. Non vale se ci sono giorni aggiuntivi alla scadenza.'],
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
        { type: 'paragraph', text: 'Quando emetti un proforma (vedi la guida Fatture Attive) le **scadenze** si calcolano dalla modalità di pagamento scelta: la prima a **Giorni di pagamento** dalla data del documento, le successive ogni **Giorni tra le rate**, con l’eventuale fine mese. Poi si sommano i **Giorni aggiuntivi alla scadenza**; se non ci sono, una scadenza in un **mese escluso** slitta di 10 giorni. Gli importi seguono la **Ripartizione IVA**. Le scadenze già calcolate non cambiano se modifichi la modalità.' },
        { type: 'tip', text: 'Esempio: 3 rate, 30 giorni tra le rate, fine mese + 10 giorni, IVA proporzionale. Altro esempio: 30 gg FM con agosto escluso, fattura del 15 luglio: la scadenza del 31 agosto slitta al 10 settembre.' },
      ],
    },
    {
      id: 'manage',
      title: 'Creare, modificare, riordinare ed eliminare',
      blocks: [
        { type: 'steps', items: ['Apri **Configurazione › Contabilità › Modalità di Pagamento** e premi **Nuova modalità di pagamento**.', 'Si apre la scheda con le righe chiuse: per ogni riga premi la matita (o il valore), compila (vedi tabella sopra) e premi **Fatto**; **Ripristina** riporta la riga com’era. Poi premi **Salva**.', 'Il **Codice** si inserisce solo alla creazione.'] },
        { type: 'paragraph', text: 'Sulle righe dell’elenco trovi **Visualizza** ed **Elimina**, se il tuo ruolo lo consente. Per modificare non c’è un modulo a parte: apri la scheda con **Visualizza**, premi la matita sulla riga, cambia il valore e premi **Salva** (si salva solo quella riga); **Annulla** o Esc lasciano la riga com’era. **Numero di rate** e **Giorni tra le rate** sono una riga sola, così come i due **Mesi esclusi**: si aprono, si salvano e si annullano insieme. **Riordina** cambia l’ordine con cui le modalità compaiono nei menu a tendina.' },
        { type: 'warning', text: 'Non puoi eliminare una modalità di pagamento già usata: disattivala invece.' },
      ],
    },
  ],
}

export default guide
