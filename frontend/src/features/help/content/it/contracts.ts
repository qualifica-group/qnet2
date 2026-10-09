import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'contracts',
  title: 'Contratti',
  summary: "Il contratto nasce quando un'offerta passa a uno stato Chiuso con esito positivo.",
  sections: [
    {
      id: 'overview',
      title: 'Come nasce un contratto',
      blocks: [
        {
          type: 'paragraph',
          text: "Il contratto non si crea a mano: nasce quando un'offerta passa a uno stato **Chiuso con esito positivo**, con data di **Accettazione** del giorno e lo stato **Predefinito** di **Stati Contratto**. Non nasce se la categoria prodotto è impostata per non generare contratti. Se l'offerta esce dallo stato positivo, il contratto viene **Sospeso** e QNet ricorda lo stato precedente.",
        },
      ],
    },
    {
      id: 'contract-actions',
      title: 'Azioni sul contratto',
      blocks: [
        {
          type: 'table',
          headers: ['Stato attuale', 'Azioni disponibili'],
          rows: [
            ['Aperto o In attesa', '**Modifica dati**, **Cambia stato**, **Valida contratto**, **Disdici contratto**'],
            ['Chiuso positivo', '**Programma**, **Disdici contratto**, **Riapri contratto**'],
            ['Chiuso negativo', '**Riapri contratto**'],
            ['Sospeso', '**Riapri contratto**'],
          ],
        },
        {
          type: 'list',
          items: [
            '**Valida contratto**: indica la **Data di validazione** (non futura).',
            '**Disdici contratto**: indica **Data di disdetta**, **Motivazione** e **Stato di destinazione**.',
            '**Modifica dati**: aggiorna **Data di scadenza**, **Data di rinnovo**, **Note di pagamento** e **Commenti**.',
            '**Riapri contratto**: riporta il contratto in lavorazione; se era sospeso, torna allo stato precedente.',
          ],
        },
        {
          type: 'note',
          text: "L'azione **Programma** crea una o più commesse dalle righe prodotto dell'offerta: vedi la sezione **Programmare le commesse**.",
        },
      ],
    },
    {
      id: 'program-work-orders',
      title: 'Programmare le commesse',
      blocks: [
        {
          type: 'paragraph',
          text: "**Programma** apre una finestra a due pannelli: a sinistra le **righe dell'offerta**, a destra le **commesse da creare** (gruppi). Con un solo **Crea N commesse** salvi tutti i gruppi insieme.",
        },
        {
          type: 'steps',
          items: [
            'Seleziona una o più righe libere a sinistra e premi **Nuovo gruppo**: compare il Gruppo 1 e le righe mostrano il badge **G1**.',
            'Per aggiungere altre righe a un gruppo, selezionale e usa **Aggiungi a gruppo**. Una riga sta in un solo gruppo: se è già in un altro, viene **spostata**.',
            'In ogni gruppo compila **Tipo**, **Data inizio**, **Responsabili** (almeno uno) e, se serve, il **Modello di Task**. Con il pulsante cestino elimini un gruppo e ne liberi le righe.',
            'Premi **Crea N commesse**.',
          ],
        },
        {
          type: 'list',
          items: [
            '**Una commessa per riga**: crea un gruppo per ogni riga selezionata (senza selezione, per tutte le righe libere).',
            '**Raggruppa per categoria**: crea un gruppo per categoria di prodotto, più uno per le righe **senza categoria**.',
            '**Valori comuni**: Tipo, Data inizio, Responsabili e Modello di Task che i **nuovi** gruppi ereditano; la Data inizio parte da oggi ed è modificabile. Cambiarli dopo non tocca i gruppi già creati, salvo **Applica a tutti i gruppi** (non cambia mai titoli e righe).',
            '**Duplica gruppo**: copia i quattro valori del gruppo, senza titolo né righe.',
          ],
        },
        {
          type: 'note',
          text: 'Il salvataggio è **tutto o niente**: se un gruppo ha un errore non viene creata nessuna commessa. Il gruppo con l\'errore si apre e mostra il campo da correggere. Le righe già in un\'altra commessa restano visibili ma non selezionabili; le righe che lasci libere non sono un errore. Chiudendo con gruppi non salvati ti viene chiesta conferma. Al massimo 50 commesse per volta.',
        },
        {
          type: 'tip',
          text: 'Il **Titolo** del gruppo è facoltativo: se lo lasci vuoto la commessa prende il titolo automatico **codice - prodotti** (es. COM-0042 - Consulenza + Audit), mostrato come anteprima nel campo.',
        },
      ],
    },
    {
      id: 'expiry-and-renewal',
      title: 'Scadenze e rinnovi',
      blocks: [
        {
          type: 'paragraph',
          text: 'La colonna **Avviso** mostra **In scadenza** (scadenza entro 30 giorni) o **Da rinnovare** (rinnovo entro 30 giorni); se valgono entrambe, prevale **In scadenza**. I contratti in **Chiuso negativo** non mostrano avvisi.',
        },
        {
          type: 'tip',
          text: 'Compila sempre **Data di scadenza** e **Data di rinnovo** con **Modifica dati**: senza queste date gli avvisi non compaiono.',
        },
      ],
    },
  ],
}

export default guide
