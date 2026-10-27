import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'skills',
  title: 'Catalogo competenze',
  summary: 'L’elenco delle competenze che si possono assegnare alle persone e le mansioni che le richiedono.',
  sections: [
    {
      id: 'overview',
      title: 'A cosa serve',
      blocks: [
        {
          type: 'paragraph',
          text: 'Si trova in **Configurazione › Personale**. Il catalogo elenca le competenze; i livelli delle persone si registrano nella pagina **Competenze**. Non c’entra con gli **Ambiti di assegnazione** della scheda utente.',
        },
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
            ['Nome', 'Il nome della competenza, univoco.'],
            ['Categoria', 'Un testo libero e facoltativo per raggruppare le competenze (per esempio “Lingue”).'],
            ['Descrizione', 'Una nota facoltativa sul contenuto.'],
            ['Attiva', 'Se la competenza si può assegnare alle persone e conta nella Copertura.'],
            ['Mansioni che la richiedono', 'Una riga per mansione, con il livello minimo richiesto: 1 Base, 2 Intermedio, 3 Avanzato, 4 Esperto. La stessa mansione non può comparire due volte.'],
          ],
        },
        {
          type: 'paragraph',
          text: 'Se una persona ha più mansioni che richiedono la stessa competenza, vale il livello più alto.',
        },
      ],
    },
    {
      id: 'constraints',
      title: 'Vincoli',
      blocks: [
        {
          type: 'warning',
          text: 'Una competenza già assegnata ad almeno una persona non si può eliminare: disattivala. Le competenze disattivate non si assegnano più e non compaiono nella Copertura; quelle già assegnate restano com’erano.',
        },
      ],
    },
  ],
}

export default guide
