import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'rewarded-referents',
  title: 'Referenti con Buoni',
  summary: 'I buoni, premi e incentivi si assegnano sempre al Segnalatore di una richiesta, opportunità o offerta, e si seguono in Referenti con Buoni.',
  sections: [
    {
      id: 'overview',
      title: 'La pagina Referenti con Buoni',
      blocks: [
        {
          type: 'paragraph',
          text: 'Mostra una riga per ogni referente con almeno un buono assegnato.',
        },
        {
          type: 'table',
          headers: ['Colonna', 'Significato'],
          rows: [
            ['Nome', 'Nome del referente.'],
            ['Anagrafiche collegate', 'Clienti collegati al referente.'],
            ['Email, Telefono', 'Contatti del referente.'],
            ['Totale buoni', 'Numero di buoni assegnati.'],
            ['Buoni in pending', 'Buoni ancora in attesa.'],
            ['Buoni approvati', 'Buoni riconosciuti.'],
            ['Ultima assegnazione', 'Data dell\'ultimo buono assegnato.'],
          ],
        },
        {
          type: 'note',
          text: 'Puoi filtrare per Tipologia di buono, Opportunità, Offerta, Stato commerciale, Stato di lavorazione, Operatore, Data assegnazione e Stato buono.',
        },
      ],
    },
    {
      id: 'assigning-a-reward',
      title: 'Assegnare un buono a un referente',
      blocks: [
        {
          type: 'steps',
          items: [
            'Apri una richiesta in **Gestione Richieste**, oppure un\'opportunità o un\'offerta.',
            'Nella sezione **Attribuzione** scegli il **Segnalatore**.',
            'In **Buoni assegnati** premi **Aggiungi buono**.',
            'Cerca e scegli la tipologia.',
            'Salva il record.',
          ],
        },
        {
          type: 'paragraph',
          text: 'Per togliere un buono premi **Rimuovi** accanto al suo nome e salva. La data di assegnazione viene registrata quando aggiungi il buono e non cambia ai salvataggi successivi.',
        },
        {
          type: 'warning',
          text: 'Senza **Segnalatore** non puoi assegnare buoni: compare "Seleziona prima un segnalatore per assegnare un buono."',
        },
        {
          type: 'tip',
          text: 'Se cambi il Segnalatore, i buoni già assegnati passano al nuovo segnalatore.',
        },
      ],
    },
    {
      id: 'changing-a-reward-status',
      title: 'Cambiare lo stato di un buono',
      blocks: [
        {
          type: 'paragraph',
          text: 'Ogni nuovo buono parte da **In attesa**. Gli stati sono divisi in tre gruppi: In attesa (da valutare), Approvato (riconosciuto), Negato (non riconosciuto).',
        },
        {
          type: 'steps',
          items: [
            'Espandi la riga del referente: compare una scheda per ogni buono, con cliente, categorie prodotto, stati commerciali e operatore.',
            'Nel campo **Stato** scegli il nuovo stato.',
          ],
        },
        {
          type: 'note',
          text: 'Senza il permesso di modifica del modulo lo stato è visibile ma non modificabile.',
        },
        {
          type: 'tip',
          text: 'Dalla scheda puoi aprire l\'opportunità o l\'offerta da cui nasce il buono. Se l\'origine è stata eliminata compare "Origine non più disponibile".',
        },
      ],
    },
  ],
}

export default guide
