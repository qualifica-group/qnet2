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
            'Apri **Marketing e Lead › Progetti** e clicca **Nuovo progetto**: il modulo ha lo stesso aspetto della scheda, con le stesse sezioni e **righe chiuse** (il Codice è già proposto).',
            "Fai clic su una riga (o sulla matita) per aprirla, compila e premi **Fatto** per tenere il valore o **Ripristina** per riportarlo com'era (vedi tabella).",
            'Clicca **Salva**: QNet controlla tutto e crea il progetto. Se esci senza salvare ti viene chiesta conferma.',
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
            [
              'Ambito geografico',
              'Paese, regione, provincia, città',
              'Una sola riga: scegli prima il paese (quello predefinito è già proposto), poi restringi.',
            ],
            [
              'Pianificazione e budget',
              '**Data inizio**, **Data fine**, **Budget totale**, **Target lead**',
              "La fine non può precedere l'inizio.",
            ],
          ],
        },
        {
          type: 'note',
          text: "L'azione **Duplica** apre la creazione già compilata con i dati del progetto di partenza: nuovo codice e denominazione con « (copia)».",
        },
      ],
    },
    {
      id: 'edit-a-project',
      title: 'Modificare un progetto',
      blocks: [
        {
          type: 'paragraph',
          text: 'Non esiste una pagina di modifica separata: il progetto si modifica **direttamente dalla sua scheda**, un campo alla volta. Anche la matita sulla scheda della griglia apre la scheda del progetto.',
        },
        {
          type: 'steps',
          items: [
            "Apri il progetto dall'elenco o dalla griglia.",
            'Passa col mouse sul campo da cambiare e premi la **matita** (o fai clic sul valore).',
            'Modifica il valore nel controllo che compare.',
            "Premi **Salva** per salvare quel campo; **Annulla** (o Esc, o un clic fuori dal campo aperto) lo chiude lasciandolo com'era, senza salvare.",
          ],
        },
        {
          type: 'note',
          text: "Il **Codice** non si modifica dopo la creazione. L'**Ambito geografico** si modifica in un unico riquadro: paese, regione, provincia e città cambiano insieme. Un campo senza matita è in sola lettura per il tuo ruolo.",
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
