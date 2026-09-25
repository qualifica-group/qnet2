import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'leads',
  title: 'Lead',
  summary: "Il lead è un contatto generato da una campagna, sempre legato a un'anagrafica e a una campagna.",
  sections: [
    {
      id: 'create-a-lead',
      title: 'Creare un lead',
      blocks: [
        {
          type: 'steps',
          items: ['Apri **Marketing e Lead › Lead** e clicca **Nuovo lead**.', 'Compila i campi (vedi tabella).', 'Clicca **Salva**.'],
        },
        {
          type: 'table',
          headers: ['Campo', 'Note'],
          rows: [
            ['**Anagrafica**', "Obbligatoria: un'anagrafica già presente."],
            ['**Campagna**', 'Obbligatoria.'],
            ['**Sede**', 'La sede operativa del lead.'],
            ['**Operatore**', "Chi segue il lead; l'elenco mostra solo gli operatori della sede scelta."],
            ['**Fonte**', 'Il canale da cui arriva il lead.'],
            ['Prodotti di interesse', 'Limitati alle categorie della campagna.'],
            ['**Note**', 'Testo libero, massimo 5000 caratteri.'],
            ['Campi extra / Dati importati', 'Coppie **Chiave** e **Valore**, di solito da un import.'],
          ],
        },
        {
          type: 'paragraph',
          text: 'Se cambi campagna e alcuni prodotti non sono più coperti, QNet chiede conferma: clicca **Rimuovi e continua**.',
        },
        {
          type: 'tip',
          text: "Attiva **Converti automaticamente in Opportunità** per creare subito l'opportunità insieme al lead (serve il permesso di creare opportunità).",
        },
      ],
    },
    {
      id: 'lead-status',
      title: 'Stato del lead',
      blocks: [
        { type: 'paragraph', text: 'Lo **Stato lead** si aggiorna da solo:' },
        {
          type: 'table',
          headers: ['Stato lead', 'Quando'],
          rows: [
            ['**Non associato**', 'Il lead non ha ancora un operatore.'],
            ['**Associato**', 'Il lead ha un operatore assegnato.'],
            ["**Convertito in opportunità**", "Dal lead è nata un'opportunità."],
          ],
        },
        {
          type: 'paragraph',
          text: 'Le colonne **Assegnato** e **Convertito** permettono di filtrare i lead per stato.',
        },
      ],
    },
    {
      id: 'assign-operators',
      title: 'Assegnare i lead agli operatori',
      blocks: [
        {
          type: 'steps',
          items: [
            'Nella tabella **Lead** seleziona i lead.',
            'Clicca **Assegna operatori**.',
            'Scegli il **Tipo di assegnazione**: **Smistamento equo** (distribuisce bilanciando il carico) o **Assegna a operatore** (tutti allo stesso operatore).',
            'Con **Smistamento equo** compare la lista degli operatori raggruppati per Sede: sono tutti selezionati, deseleziona chi non deve ricevere lead (per gruppo intero o singolo operatore).',
            'Clicca **Assegna**.',
          ],
        },
        {
          type: 'paragraph',
          text: 'Compaiono solo gli operatori della sede competenti per i lead scelti, con il numero di lead già in carico a ciascuno; i lead senza operatore competente vengono indicati come esclusi.',
        },
        {
          type: 'warning',
          text: 'Alcune modalità non sono disponibili se la selezione contiene lead di campagne diverse. Un gruppo lasciato senza operatori selezionati non riceve i suoi lead.',
        },
      ],
    },
    {
      id: 'convert-to-opportunity',
      title: 'Convertire un lead in opportunità',
      blocks: [
        { type: 'paragraph', text: 'Tre modi, senza compilare moduli:' },
        {
          type: 'list',
          items: [
            'dalla scheda del lead, con **Crea opportunità** (poi il pulsante diventa **Vai all\'opportunità**);',
            'dalla tabella, selezionando più lead e cliccando **Converti in opportunità**;',
            'alla fine di un import, con la conversione automatica.',
          ],
        },
        {
          type: 'table',
          headers: ["Nell'opportunità", 'Da dove arriva'],
          rows: [
            ['Anagrafica, Fonte, Note generali', 'Dal lead.'],
            ['Sede operativa', 'La sede del lead, modificabile.'],
            ['Gestori account', 'L\'operatore del lead diventa il secondo gestore account.'],
            ['Righe di classificazione', 'Funzioni aziendali e categorie della campagna (o del suo progetto).'],
            ['Prodotti di interesse', 'Quelli del lead.'],
          ],
        },
        {
          type: 'paragraph',
          text: "Insieme all'opportunità nasce un'offerta collegata, con una riga per ogni prodotto di interesse vendibile (quantità 1, prezzo del prodotto).",
        },
        {
          type: 'paragraph',
          text: "La conversione viene bloccata se il lead ha già un'opportunità, se la campagna non ha funzione aziendale o categoria prodotto, o se l'anagrafica ha già un'opportunità aperta (in quel caso aggiungi l'offerta a quella esistente).",
        },
        {
          type: 'warning',
          text: 'La conversione multipla vale tutta o niente. Se un solo lead non è convertibile, QNet elenca quelli bloccati e il motivo: deselezionali e riprova.',
        },
      ],
    },
  ],
}

export default guide
