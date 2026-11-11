import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'enrollee-management',
  title: 'Gestione Iscritti',
  summary:
    'Gestione Iscritti funziona come Gestione Richieste, con le stesse colonne, filtri e azioni: mostra solo le richieste arrivate a uno stato Validato o Chiuso con esito positivo.',
  sections: [
    {
      id: 'overview',
      title: 'Cosa cambia rispetto a Gestione Richieste',
      blocks: [
        {
          type: 'paragraph',
          text: 'Si trova in **Commerciale › Gestione Iscritti**. È lo stesso banco di lavoro di **Gestione Richieste**: stesse colonne, filtri, ricerca (dal terzo carattere, per parole che iniziano con quanto scrivi), azioni sulle righe.',
        },
        {
          type: 'table',
          headers: ['Differenza', 'Dettaglio'],
          rows: [
            [
              'Cosa mostra',
              'Solo le richieste con stato di lavorazione del gruppo **Validato** o **Chiuso con esito positivo**.',
            ],
            [
              'Creazione',
              'Non ha il pulsante di creazione: una richiesta entra negli iscritti solo cambiando stato.',
            ],
            ['Permessi', 'Ha permessi propri, separati da quelli di Gestione Richieste.'],
            ['Categorie', 'Come in Gestione Richieste: le categorie con **Visibile in** su **Opportunità / Offerte** non hanno una scheda e non si possono scegliere, e gli iscritti che hanno solo categorie di questo tipo non compaiono.'],
            [
              'Documenti e note',
              "L'iscritto è la stessa richiesta: documenti e note scritti in Gestione Richieste restano gli stessi, senza copie. La scheda **Documenti** compare con il permesso **Visualizza documenti** di Gestione Iscritti, che hanno anche commerciali, segreteria didattica e supervisore didattica.",
            ],
            [
              'Chi vede cosa',
              'Senza altri permessi vedi solo le richieste del cui **Team** fai parte: come operatore, Supervisore o Gestore account. **Visualizza sede fisica** aggiunge quelle della tua sede fisica (non delle sedi remote); **Visualizza per sede** quelle di tutte le tue sedi; **Visualizza per competenza** quelle con un prodotto in una categoria del tuo ambito di assegnazione, in qualunque sede (con **Ambito su tutte le categorie**, tutte); **Visualizza tutti** tutte. Resta comunque il filtro sugli stati del modulo.',
            ],
            [
              'Notifiche di trasferimento',
              'Stesse regole di Gestione Richieste; il riepilogo di ogni trasferimento arriva a chi ha **Visualizza tutti** in Gestione Iscritti.',
            ],
          ],
        },
        {
          type: 'note',
          text: 'Per creare, lavorare o assegnare le richieste vedi la guida di **Gestione Richieste**: le stesse azioni valgono qui.',
        },
      ],
    },
    {
      id: 'offer-rows-editor',
      title: "Righe dell'offerta",
      blocks: [
        {
          type: 'paragraph',
          text: "La cella **Linee di prodotto** e la scheda della richiesta usano lo stesso editor di Gestione Richieste: a sinistra il **Catalogo prodotti** (ricerca per nome o descrizione, chip delle categorie scelte; per ogni prodotto codice, tipologia, prezzo con unità, categoria e descrizione), a destra le **Righe dell'offerta** con importi e **Totale offerta**.",
        },
        {
          type: 'list',
          items: [
            'Spunta uno o più prodotti e premi **Aggiungi N selezionati**: ogni prodotto diventa una riga con quantità 1, prezzo e IVA del prodotto.',
            "Se il prodotto ha dei fornitori, sulla riga compare **Fornitore** con i fornitori del prodotto: il predefinito è già scelto e il campo è obbligatorio. Il prezzo della riga è quello del fornitore scelto e cambia se cambi fornitore. Se il fornitore ha una commissione Fornitore su quel prodotto, accanto al fornitore compare l'avviso (per esempio 'Commissione 10% pagata'). I prodotti proposti automaticamente arrivano con il fornitore predefinito.",
            'Un prodotto già nelle righe è segnato **Già aggiunto** e non si seleziona: la quantità si cambia sulla riga.',
            'Con una categoria a riga singola il clic su un prodotto sostituisce quello della riga; con una categoria semplificata la riga mostra solo gli importi.',
          ],
        },
      ],
    },
    {
      id: 'statistics-differences',
      title: "Le statistiche non ci sono più",
      blocks: [
        {
          type: 'paragraph',
          text: "Le statistiche non sono più disponibili in Gestione Iscritti: tabella senza pannello né pulsante statistiche. Le statistiche sulle richieste hanno una pagina propria, **Statistiche Gestione Richieste** (vedi la relativa guida).",
        },
      ],
    },
  ],
}

export default guide
