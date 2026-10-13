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
          text: "Premi Nuova sede: il modulo ha lo stesso aspetto del dettaglio, con le righe chiuse. Fai clic sulla riga (o sulla matita) per aprirla, poi **Fatto** per tenere il valore o **Ripristina** per riportarlo com'era; **Salva** controlla tutto e crea la sede (se esci senza salvare ti viene chiesta conferma). Le sezioni sono:",
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
      id: 'editing',
      title: 'Modificare una sede',
      blocks: [
        {
          type: 'paragraph',
          text: "Non esiste una pagina di modifica separata: la sede si modifica **direttamente dal suo dettaglio**, una riga alla volta, campi personalizzati compresi. Premi la **matita** (o fai clic sul valore), cambia il valore e premi **Salva**: si salva solo quella riga; **Annulla** o Esc la lasciano com'era. I **Dati azienda** si modificano insieme (sono sempre di un'azienda) e anche le **Banche** si salvano come elenco unico. **Contatti**, **Indirizzo** e **Logo** si gestiscono lì stesso e si salvano subito.",
        },
      ],
    },
    {
      id: 'default-site',
      title: 'Sede predefinita',
      blocks: [
        {
          type: 'paragraph',
          text: "Per rendere una sede quella principale, aprila e premi Imposta come sede predefinita nella testata. Nella tabella ha l'etichetta Predefinita.",
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
