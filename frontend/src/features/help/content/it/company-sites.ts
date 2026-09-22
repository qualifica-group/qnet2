import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'company-sites',
  title: 'Società sedi',
  summary: 'Le Società sedi sono le sedi di ciascuna società aziendale, con dati fiscali, contatti e banche.',
  sections: [
    {
      id: 'overview',
      title: 'Cosa sono le società sedi',
      blocks: [
        {
          type: 'paragraph',
          text: 'Una società sede è una sede di una società aziendale: raccoglie i dati fiscali, i contatti e le banche usate per la fatturazione.',
        },
      ],
    },
    {
      id: 'creating-a-site',
      title: 'Creare una sede',
      blocks: [
        {
          type: 'paragraph',
          text: 'Premi Nuova sede e compila le sezioni:',
        },
        {
          type: 'table',
          headers: ['Sezione', 'Cosa indicare'],
          rows: [
            ['Generale', 'Nome (obbligatorio), Note e Logo.'],
            ['Dati azienda', 'Ragione sociale, partita IVA, codice fiscale e codice destinatario SDI.'],
            ['Contatti', 'Telefono, email, PEC e altri recapiti.'],
            ['Indirizzo', "L'indirizzo della sede. È ammesso un solo indirizzo."],
            ['Società', 'La società aziendale a cui appartiene la sede.'],
            ['Banche', 'Premi Aggiungi banca e indica Nome, IBAN e Note; spunta Banca preferita per la banca principale.'],
          ],
        },
      ],
    },
    {
      id: 'default-site',
      title: 'Sede predefinita',
      blocks: [
        {
          type: 'paragraph',
          text: "Per rendere una sede quella principale, aprila in modifica e premi Imposta come sede predefinita. Nella tabella ha l'etichetta Predefinita.",
        },
        {
          type: 'note',
          text: 'Può esserci una sola sede predefinita.',
        },
      ],
    },
  ],
}

export default guide
