import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'email-templates',
  title: 'Modelli email',
  summary: 'Modelli riusabili con segnaposto per comporre le email di commesse e fatture.',
  sections: [
    {
      id: 'overview',
      title: 'Panoramica',
      blocks: [
        {
          type: 'paragraph',
          text: "Il modulo si trova in **Configurazione › Documenti › Modelli email**. Un modello è la coppia **Oggetto**/**Corpo** che il composer email di una commessa propone quando scegli **Modello email**: i **Moduli** disponibili sono **Commesse** e **Fatture**.",
        },
        {
          type: 'note',
          text: 'Solo i modelli **Attivi** compaiono nel selettore del composer; un modello disattivato resta comunque modificabile ed eliminabile da qui.',
        },
      ],
    },
    {
      id: 'create-template',
      title: 'Creare un modello',
      blocks: [
        {
          type: 'steps',
          items: [
            'Apri **Configurazione › Documenti › Modelli email** e premi **Nuovo modello email**.',
            "Si apre la scheda con le righe chiuse: per **Nome**, **Modulo**, **Oggetto**, **Descrizione** e **Attivo** premi la matita (o il valore), compila e premi **Fatto**; **Ripristina** riporta la riga com'era.",
            "Il **Corpo** è già aperto: scrivilo con l'editor di testo (niente immagini, non sono ammesse nel corpo delle email).",
            'Premi **Salva**: il modello viene creato con tutto insieme.',
          ],
        },
        { type: 'warning', text: 'Dopo la creazione il **Modulo** non si modifica più.' },
      ],
    },
    {
      id: 'edit-template',
      title: 'Modificare un modello',
      blocks: [
        {
          type: 'paragraph',
          text: "Non c'è un modulo di modifica a parte: apri il modello con **Visualizza**, premi la matita sulla riga (**Nome**, **Oggetto**, **Descrizione**, **Attivo**), cambia il valore e premi **Salva** (si salva solo quella riga); **Annulla** o Esc lasciano la riga com'era. Il **Corpo** è una riga a tutta larghezza: la matita apre lo stesso editor della creazione, con il pannello dei segnaposto, e **Salva** registra solo il corpo.",
        },
      ],
    },
    {
      id: 'placeholders',
      title: 'Segnaposto',
      blocks: [
        {
          type: 'paragraph',
          text: "Un segnaposto è un testo tra parentesi graffe, per esempio **{work_order.code}**, che al momento della scelta del modello nel composer viene sostituito con il dato reale della commessa. Per inserirne uno, apri il pannello **Inserisci segnaposto** accanto all'Oggetto oppure sopra il Corpo, cerca la voce che ti serve e clicca per aggiungerla nel punto in cui si trova il cursore.",
        },
        {
          type: 'list',
          items: [
            '**Commessa** (codice, titolo, tipo, date, descrizione) e **Mittente** (nome ed email di chi invia).',
            "**Preventivo**, **Cliente**, **Opportunità**, **Referente**, **Commerciale**, **Segnalatore**, **Supervisore**, **Società** e **Sede**: gli stessi dati dell'offerta collegata già usati nei layout dei documenti.",
          ],
        },
        {
          type: 'paragraph',
          text: "Il modulo **Fatture** offre i segnaposto **Fattura**, **Cliente**, **Società**, **Pagamento**, **Totali** e **Mittente**, più quelli del **Sollecito**: la **elenco delle scadenze scadute** e l'**importo scaduto**.",
        },
        {
          type: 'note',
          text: "I segnaposto si risolvono UNA SOLA VOLTA, quando scegli il modello nel composer: oggetto e corpo restano poi liberamente modificabili e non vengono ricalcolati all'invio. Un segnaposto sconosciuto, o senza un dato da mostrare, diventa semplicemente vuoto.",
        },
      ],
    },
    {
      id: 'usage',
      title: 'Dove si usa',
      blocks: [
        {
          type: 'paragraph',
          text: 'Un modello **Attivo** è selezionabile dal composer email di una commessa (modelli del modulo Commesse) o di una fattura attiva, anche per il **Sollecito** (modelli del modulo Fatture).',
        },
        {
          type: 'paragraph',
          text: "Nel composer il campo **Mittente** permette di scegliere l'indirizzo da cui inviare: la tua email di accesso oppure una delle email (non PEC) presenti nella tua anagrafica utente. QNet ricorda in questo browser l'ultimo mittente usato e lo propone alla bozza successiva; se quell'indirizzo non è più tra i tuoi, propone l'email di accesso. Le email dell'anagrafica le aggiunge chi può modificare gli utenti.",
        },
        {
          type: 'warning',
          text: "L'invio parte dalla casella Microsoft 365 scelta: se l'indirizzo non è una casella aziendale abilitata, l'email risulta **Non riuscita** con il motivo dell'errore. Il segnaposto **{sender.email}** mostra sempre l'email di accesso.",
        },
      ],
    },
  ],
}

export default guide
