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
            ['Aperto o In attesa', 'Dati modificabili sul posto, **Cambia stato**, **Valida contratto**, **Disdici contratto**'],
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
            'Dati del contratto: **Scadenza**, **Rinnovo**, **Note di pagamento** e **Commenti** si modificano direttamente nella scheda, finché il contratto è Aperto o In attesa: premi la matita accanto al campo (o fai clic sul valore), cambia il valore e premi **Salva** (Annulla, Esc o un clic fuori chiudono senza salvare). I Commenti si scrivono dentro il loro riquadro in cima alla scheda.',
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
      id: 'program-multi-quote-work-order',
      title: 'Programmare una commessa unica da più contratti',
      blocks: [
        {
          type: 'paragraph',
          text: 'Se le categorie prodotto del ramo hanno attiva l\'impostazione **Commessa multi-offerta**, puoi raccogliere in **una sola commessa** le righe di più contratti vinti, anche di clienti diversi.',
        },
        {
          type: 'steps',
          items: [
            'Nella tabella **Contratti** seleziona da 2 a 50 contratti **Chiusi con esito positivo**.',
            'Premi l\'azione **Programma commessa unica** (serve il permesso di programmare).',
            'Nella finestra le righe sono raggruppate per contratto. Sono selezionabili solo le **righe di ricavo** di un ramo con l\'impostazione attiva e **non ancora in una commessa**; le altre restano visibili ma disattivate.',
            'Scegli le righe: devono essere tutte dello **stesso ramo** e provenire da **almeno due offerte** diverse.',
            'Compila i campi comuni: **Titolo** (facoltativo), **Tipo**, **Data inizio**, **Responsabili** (almeno uno) e, se serve, il **Modello di Task**.',
            'Premi il pulsante di conferma: viene creata **una commessa** e si apre subito.',
          ],
        },
        {
          type: 'note',
          text: 'Il salvataggio è **tutto o niente**: se qualcosa non va non viene creata nessuna commessa e l\'errore compare sul campo delle righe. Una riga già in un\'altra commessa non si può riutilizzare. Se tra i contratti selezionati ce n\'è almeno uno non ancora in uno stato **Chiuso positivo** (per esempio **Da validare**) o che non si può cumulare (la sua offerta non ha righe di un ramo con **Commessa multi-offerta** attivo), la finestra si apre comunque e mostra quali contratti, con il cliente, vanno tolti dalla selezione.',
        },
      ],
    },
    {
      id: 'expiry-and-renewal',
      title: 'Scadenze e rinnovi',
      blocks: [
        {
          type: 'paragraph',
          text: 'La colonna **Avviso** mostra **In scadenza** (scadenza entro 30 giorni) o **Da rinnovare** (rinnovo entro 30 giorni); se valgono entrambe, prevale **In scadenza**. I contratti in **Chiuso negativo** non mostrano avvisi. La colonna **Linee di prodotto** elenca i prodotti delle righe di ricavo dell\'offerta, ciascuno con la sua categoria tra parentesi (sola lettura). La colonna **Categoria madre** mostra la macrocategoria dei prodotti dell\'offerta, ad esempio Formazione (sola lettura, filtrabile).',
        },
        {
          type: 'tip',
          text: 'Compila sempre **Scadenza** e **Rinnovo** nella scheda del contratto: senza queste date gli avvisi non compaiono.',
        },
      ],
    },
  ],
}

export default guide
