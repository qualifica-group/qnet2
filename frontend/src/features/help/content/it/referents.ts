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
          text: "Per creare un referente vai in Referenti e premi **Nuovo referente**: il modulo ha lo stesso aspetto del dettaglio, con le stesse sezioni e **righe chiuse**. Fai clic sulla riga (o sulla matita) per aprirla, poi **Fatto** per tenere il valore o **Ripristina** per riportarlo com'era. Nei **Dati anagrafici** scegli prima il Tipo, Persona fisica o Azienda: con Fatto il nome compare in testa alla scheda. Contatti e Indirizzi sono nella colonna di destra, con i campi già pronti da compilare (Telefono obbligatorio). **Salva** controlla tutto e crea il referente; se esci senza salvare ti viene chiesta conferma.",
        },
        {
          type: 'table',
          headers: ['Sezione', 'Cosa contiene'],
          rows: [
            ['Dati anagrafici', "Gli stessi campi dell'anagrafica, con Persona fisica o Azienda."],
            ['Dettagli referente', 'Tipo referente (il ruolo della persona, che compare sotto il suo nome nelle card Referenti dell’anagrafica e nel campo Referente di opportunità, offerte e attività), Ambito contatto (Interno o Esterno), Utente collegato (se la persona usa QNet), **Anagrafica fornitore** e Note.'],
            ['Altri campi', 'I campi personalizzati dei referenti, raggruppati come nel modulo.'],
            ['Contatti', 'I recapiti. Il Telefono è obbligatorio in creazione.'],
            ['Indirizzi', 'Sedi e indirizzi di fatturazione.'],
          ],
        },
      ],
    },
    {
      id: 'editing-a-referent',
      title: 'Modificare un referente',
      blocks: [
        {
          type: 'paragraph',
          text: 'Non esiste una pagina di modifica separata: il referente si modifica **direttamente dal suo dettaglio**, un campo alla volta, campi personalizzati compresi.',
        },
        {
          type: 'steps',
          items: [
            "Apri il referente dall'elenco.",
            'Passa col mouse sul campo da cambiare e premi la **matita** (o fai clic sul valore).',
            'Modifica il valore nel controllo che compare.',
            "Premi **Salva** (o Invio nei campi di testo) per salvare solo quel campo; **Annulla** (o Esc, o un clic fuori dal campo aperto) per chiuderlo lasciandolo com'era, senza salvare.",
          ],
        },
        {
          type: 'note',
          text: "I **Dati anagrafici** si modificano insieme: la matita apre l'intera scheda e Salva aggiorna anche il nome del referente. **Contatti** e **Indirizzi** si aggiungono, modificano ed eliminano lì stesso e si salvano subito.",
        },
        {
          type: 'note',
          text: 'Un campo senza matita non è modificabile da te: i permessi del tuo ruolo lo rendono in sola lettura.',
        },
      ],
    },
    {
      id: 'referent-notes',
      title: 'Note del referente',
      blocks: [
        {
          type: 'paragraph',
          text: 'Nella colonna di destra del dettaglio c’è la scheda **Note**, la prima: qui discuti il referente con i colleghi, menzionandoli con **@** come nelle Opportunità. Dalla tabella apri le stesse note con l’azione **Note** della riga, che mostra quante ce ne sono. Le note scritte nel vecchio QNet arrivano con la migrazione.',
        },
        {
          type: 'tip',
          text: 'La scheda e l’azione Note compaiono a chi può vedere il referente; per scrivere una nota serve anche il permesso di creare note. Le note sono diverse dal campo **Note**, che resta un testo del record.',
        },
      ],
    },
    {
      id: 'supplier-registry',
      title: 'Anagrafica fornitore (agenti)',
      blocks: [
        {
          type: 'paragraph',
          text: 'Il campo **Anagrafica fornitore** collega il referente all’anagrafica, con ruolo fornitore, a cui intestare la fattura passiva delle sue provvigioni. Si imposta qui oppure direttamente dall’approvazione di un estratto (vedi la guida **Estratti**), dove puoi anche crearla dal referente.',
        },
        {
          type: 'note',
          text: 'Dopo l’approvazione di un estratto dell’agente il campo è **bloccato su quel fornitore**: non si può cambiare con un altro. Si può scegliere solo un’anagrafica che sia un fornitore.',
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
