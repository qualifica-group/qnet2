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
            ['**Giorni di pagamento**', 'Numero di giorni previsti per il pagamento.'],
            ['**Attivo**', 'Se lo spegni, la modalità sparisce dai menu a tendina ma i record che la usano restano intatti.'],
          ],
        },
      ],
    },
    {
      id: 'manage',
      title: 'Creare, modificare, riordinare ed eliminare',
      blocks: [
        { type: 'steps', items: ['Apri **Configurazione › Modalità di Pagamento** e premi **Nuova modalità di pagamento**.', 'Compila i campi (vedi tabella sopra).', 'Premi **Salva**.'] },
        { type: 'paragraph', text: 'Sulle righe dell’elenco trovi **Visualizza**, **Modifica** ed **Elimina**, se il tuo ruolo lo consente. **Riordina** cambia l’ordine con cui le modalità compaiono nei menu a tendina.' },
        { type: 'warning', text: 'Non puoi eliminare una modalità di pagamento già usata: disattivala invece.' },
      ],
    },
  ],
}

export default guide
