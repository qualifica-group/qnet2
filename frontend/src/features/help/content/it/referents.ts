import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'referents',
  title: 'Referenti',
  summary: 'I Referenti sono le persone di contatto, interne o esterne, della tua organizzazione.',
  sections: [
    {
      id: 'overview',
      title: 'Cosa sono i referenti',
      blocks: [
        {
          type: 'paragraph',
          text: 'Un referente è una persona di contatto: interna alla tua organizzazione oppure esterna, legata a una o più anagrafiche.',
        },
      ],
    },
    {
      id: 'referent-record',
      title: 'La scheda referente',
      blocks: [
        {
          type: 'paragraph',
          text: 'Per creare un referente vai in Referenti e premi Nuovo referente.',
        },
        {
          type: 'table',
          headers: ['Sezione', 'Cosa contiene'],
          rows: [
            ['Dati anagrafici', "Gli stessi campi dell'anagrafica, con Persona fisica o Azienda."],
            ['Dettagli referente', 'Tipo referente, Utente collegato (se la persona usa QNet), Ambito contatto (Interno o Esterno) e Note.'],
            ['Contatti', 'I recapiti. Il Telefono è obbligatorio in creazione.'],
            ['Indirizzi', 'Sedi e indirizzi di fatturazione.'],
          ],
        },
      ],
    },
    {
      id: 'linking-a-referent',
      title: 'Collegare un referente a un cliente',
      blocks: [
        {
          type: 'tip',
          text: "Un referente si collega a un cliente dalla scheda dell'anagrafica, nel campo Referenti.",
        },
        {
          type: 'note',
          text: 'Quando crei un referente, QNet cerca schede simili e mostra Possibile duplicato: la procedura è la stessa delle Anagrafiche (vedi quella guida).',
        },
      ],
    },
  ],
}

export default guide
