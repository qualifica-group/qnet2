import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'sectors',
  title: 'Settori',
  summary: 'I Settori classificano le anagrafiche per attività; con un Settore padre puoi costruire una gerarchia.',
  sections: [
    {
      id: 'overview',
      title: 'Panoramica',
      blocks: [
        { type: 'paragraph', text: 'Il modulo si trova in **Configurazione › Settori** e si usa nelle **Anagrafiche**, per classificarle in base all’attività svolta.' },
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
            ['**Codice**', 'Facoltativo: il codice del settore, esattamente come lo usi (es. 01, 07a, NA). Due settori non possono avere lo stesso codice. Si può cercare per codice.'],
            ['**Nome**', 'Il nome del settore.'],
            ['**Settore padre**', 'Facoltativo: collega il settore a un settore padre per creare una gerarchia.'],
            ['**Attivo**', 'Attivo di default. Spegnilo per non proporre più il settore nei campi di scelta.'],
          ],
        },
        { type: 'note', text: 'Un settore **non attivo**, con tutti i suoi sotto-settori, sparisce dal campo **Settori** delle **Anagrafiche** e non si può collegare a una nuova anagrafica. Le anagrafiche che lo hanno già lo conservano e si possono salvare senza toglierlo. Nell’elenco dei settori resta visibile: la colonna **Attivo** permette di filtrarlo.' },
        { type: 'tip', text: 'Se un sotto-settore risulta attivo ma il suo padre non lo è, il modulo te lo segnala sotto l’interruttore **Attivo**: il sotto-settore resta nascosto finché il padre è non attivo.' },
      ],
    },
    {
      id: 'manage',
      title: 'Creare, modificare ed eliminare',
      blocks: [
        { type: 'steps', items: ['Apri **Configurazione › Settori** e premi **Nuovo settore**.', 'Compila **Nome** e, se serve, **Settore padre**.', 'Premi **Salva**.'] },
        { type: 'paragraph', text: 'Sulle righe dell’elenco trovi **Visualizza** ed **Elimina**, se il tuo ruolo lo consente. Per modificare apri la scheda con **Visualizza** e premi **Modifica**.' },
        { type: 'warning', text: 'Un settore con sotto-settori non si può eliminare.' },
      ],
    },
  ],
}

export default guide
