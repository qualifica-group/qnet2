import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'opportunities',
  title: 'Opportunità',
  summary: "L'opportunità è una trattativa commerciale con un'anagrafica, nata da un lead o creata a mano.",
  sections: [
    {
      id: 'create-an-opportunity',
      title: "Creare un'opportunità",
      blocks: [
        {
          type: 'paragraph',
          text: "L'opportunità è una trattativa con un'**Anagrafica**. Nasce da un lead oppure viene creata a mano.",
        },
        {
          type: 'steps',
          items: [
            'Apri **Opportunità e Commesse › Opportunità**.',
            'Clicca **Nuova opportunità**.',
            'Compila le sezioni (vedi tabella).',
            'Clicca **Salva**.',
          ],
        },
        {
          type: 'table',
          headers: ['Sezione', 'Campi'],
          rows: [
            ['Anagrafica e contatti', '**Anagrafica** (obbligatoria), **Referente**, **Commerciale**'],
            ['Classificazione', '**Fonte**, **Sede operativa**'],
            ['Attribuzione', '**Segnalatore**, **Buoni assegnati**'],
            ['Funzioni aziendali e categorie prodotto', '**Righe di classificazione**'],
            ['Team', '**Supervisore**, **Gestori account**'],
            ['Pianificazione', '**Data inizio**, **Data chiusura prevista**, **Valore stimato**, **Probabilità di successo (%)**'],
            ['Note generali', 'Testo libero'],
          ],
        },
      ],
    },
    {
      id: 'from-a-lead',
      title: 'Ereditarietà da un lead',
      blocks: [
        {
          type: 'paragraph',
          text: "Se l'opportunità nasce da un lead, alcuni campi sono ereditati e bloccati: un avviso in alto lo segnala. I **Gestori account** sono sincronizzati con l'offerta collegata.",
        },
        {
          type: 'warning',
          text: "Un'anagrafica può avere una sola opportunità aperta alla volta. Se ne esiste già una, QNet propone **Aggiungi l'offerta a questa opportunità**.",
        },
      ],
    },
    {
      id: 'status-and-constraints',
      title: 'Stato e vincoli',
      blocks: [
        {
          type: 'paragraph',
          text: 'Lo **Stato** dell\'opportunità non si imposta a mano: è calcolato dagli stati delle sue offerte (per esempio "2 stati" se sono diversi). Nella scheda trovi l\'elenco delle **Offerte** e il pulsante **Nuova offerta**.',
        },
        {
          type: 'warning',
          text: "Alcune categorie prodotto ammettono una sola offerta per opportunità.",
        },
      ],
    },
  ],
}

export default guide
