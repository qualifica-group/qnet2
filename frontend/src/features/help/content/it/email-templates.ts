import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'email-templates',
  title: 'Modelli email',
  summary: 'Modelli riusabili con segnaposto per comporre le email delle commesse.',
  sections: [
    {
      id: 'overview',
      title: 'Panoramica',
      blocks: [
        {
          type: 'paragraph',
          text: "Il modulo si trova in **Configurazione › Modelli email**. Un modello è la coppia **Oggetto**/**Corpo** che il composer email di una commessa propone quando scegli **Modello email**: oggi l'unico **Modulo** disponibile è **Commesse**.",
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
            'Apri **Configurazione › Modelli email** e premi **Nuovo modello email**.',
            'Compila **Nome**, **Modulo** e **Oggetto**.',
            "Scrivi il **Corpo** con l'editor di testo: niente immagini, non sono ammesse nel corpo delle email.",
            "Se serve, aggiungi una **Descrizione** a uso interno e imposta **Attivo**.",
            'Salva il modello.',
          ],
        },
        { type: 'warning', text: 'Dopo la creazione il **Modulo** non si modifica più.' },
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
          text: 'Un modello **Attivo** è selezionabile dal composer email di una commessa.',
        },
      ],
    },
  ],
}

export default guide
