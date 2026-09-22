import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'projects',
  title: 'Progetti',
  summary:
    'Il progetto è il contenitore più ampio del percorso commerciale: raggruppa campagne di marketing con budget e obiettivo di lead.',
  sections: [
    {
      id: 'overview',
      title: "Cos'è un progetto",
      blocks: [
        {
          type: 'paragraph',
          text: 'Il progetto è il contenitore più ampio: classificazione, area geografica, budget e obiettivo di lead. Il selettore **Griglia** / **Tabella** cambia la vista; nella griglia ogni scheda mostra quante campagne e lead ha il progetto.',
        },
      ],
    },
    {
      id: 'create-a-project',
      title: 'Creare un progetto',
      blocks: [
        {
          type: 'steps',
          items: [
            'Apri **Marketing e Lead › Progetti**.',
            'Clicca **Nuovo progetto**.',
            'Compila le sezioni (vedi tabella).',
            'Clicca **Salva**.',
          ],
        },
        {
          type: 'table',
          headers: ['Sezione', 'Campi', 'Note'],
          rows: [
            ['Identità', '**Codice**, **Denominazione**, **Descrizione**', 'Il codice è proposto in automatico, ma modificabile.'],
            [
              'Classificazione',
              '**Stato**, **Righe prodotto**, **Partner**, **Sede**',
              'Compila **Partner** se il progetto è richiesto dal partner: i costi vengono attribuiti a lui.',
            ],
            ['Ambito geografico', 'Paese, regione, provincia, città', 'Scegli prima il paese, poi restringi.'],
            [
              'Pianificazione e budget',
              '**Data inizio**, **Data fine**, **Budget totale**, **Target lead**',
              "La fine non può precedere l'inizio.",
            ],
          ],
        },
      ],
    },
    {
      id: 'site-and-budget',
      title: 'Sede e budget',
      blocks: [
        {
          type: 'paragraph',
          text: 'La **Sede** del progetto viene proposta a ogni campagna e lead del progetto, ma resta modificabile. Nella scheda trovi **Budget allocato** alle campagne e **Budget residuo**; se le campagne superano il budget totale compare un avviso.',
        },
        {
          type: 'warning',
          text: 'Non puoi eliminare un progetto che ha ancora campagne collegate.',
        },
      ],
    },
  ],
}

export default guide
