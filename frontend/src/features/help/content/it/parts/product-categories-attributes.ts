import type { HelpSection } from '../../../types'

/** Attribute sections of the product-categories guide, split out to stay under the file-size limit. */
export const attributeSections: HelpSection[] = [
    {
      id: 'attributes',
      title: 'Attributi',
      blocks: [
        {
          type: 'paragraph',
          text: 'Gli attributi si creano nel modulo Attributi; nella categoria decidi dove usarli. Lo stesso attributo può stare in uno, due o tutti e tre i blocchi.',
        },
        {
          type: 'table',
          headers: ['Blocco', 'Dove compaiono i campi'],
          rows: [
            ['Attributi Prodotto', 'Nella scheda Prodotto, per i prodotti della categoria.'],
            ['Attributi Offerta', "Nelle Informazioni aggiuntive dell'Offerta, quando una riga usa un prodotto della categoria; anche come colonne della scheda categoria in Gestione Richieste."],
            ['Attributi Commessa', 'Nelle Informazioni aggiuntive della Commessa, quando una riga usa un prodotto della categoria.'],
          ],
        },
        {
          type: 'steps',
          items: [
            'Nel blocco scelto apri Assegna un attributo….',
            "Cerca per nome e selezionalo: compare nell'elenco con il suo tipo.",
            'Attiva Obbligatorio se il campo va sempre compilato.',
            'In Ordine scrivi un numero: i più bassi vengono prima.',
            'Per togliere un attributo usa Rimuovi attributo.',
            'Premi Salva (sulla scheda di una categoria esistente) per salvare tutti gli attributi e i tre Eredita dal padre insieme; Annulla scarta le modifiche.',
          ],
        },
        {
          type: 'paragraph',
          text: 'Sotto il tuo elenco vedi, in sola lettura, gli attributi Ereditati dalle categorie antenate.',
        },
        {
          type: 'tip',
          text: "Assegna un attributo alla categoria più alta che ne ha bisogno: tutte le sottocategorie lo riceveranno. Puoi riassegnarlo su una sottocategoria per renderlo obbligatorio solo lì; vale l'impostazione della categoria più vicina.",
        },
        {
          type: 'paragraph',
          text: 'Se togli un attributo che ha già valori, i valori nei prodotti non vengono cancellati, ma il campo non compare più e non è più obbligatorio. Lo stesso accade spegnendo Eredita dal padre.',
        },
      ],
    },
    {
      id: 'attribute-layout',
      title: 'Layout attributi',
      blocks: [
        {
          type: 'paragraph',
          text: 'Il layout decide come sono disposti i campi; senza layout compaiono in un semplice elenco.',
        },
        {
          type: 'steps',
          items: [
            'Nella riga della categoria scegli Layout attributi.',
            'Scegli il Contesto: Prodotto, Offerta o Commessa.',
            'Scegli la Modalità form: Tutte le modalità, Creazione, Modifica o Visualizzazione.',
            'Clicca Aggiungi sezione e compila Titolo (obbligatorio), Descrizione, Aspetto (Standard, In evidenza, Informativo, Secondario), Colonne (da 1 a 4), Collassabile e Chiusa di default.',
            'Se la sezione deve comparire solo quando un campo sì/no è attivo, scegli quel campo in Visibile solo se attivo; con Sempre visibile la sezione compare sempre.',
            'Clicca Aggiungi riga dentro la sezione.',
            'Trascina un campo da Attributi non posizionati nella riga.',
            'Per ogni campo scegli la Larghezza: Intera, Due terzi, Metà, Un terzo o Un quarto.',
            "Sposta sezioni e righe con le frecce e controlla l'Anteprima live.",
            'Clicca Salva layout. Compare "Layout salvato." e puoi passare a un altro contesto; Annulla chiude senza salvare.',
          ],
        },
        {
          type: 'table',
          headers: ['Situazione', 'Cosa vedi', 'Cosa puoi fare'],
          rows: [
            ['Modalità senza layout dedicato', 'Compare "Questa modalità usa il layout di Tutte le modalità."', 'Personalizza questa modalità; per tornare indietro Torna a tutte le modalità (conferma con Rimuovi).'],
            ['Categoria senza layout proprio', 'Compare "Questa categoria usa il layout di …" e l\'editor bloccato', 'Personalizza questa categoria; per tornare indietro Torna al layout ereditato.'],
          ],
        },
        {
          type: 'note',
          text: 'Se Eredita dal padre è spento in un contesto, la categoria non eredita neanche il layout di quel contesto.',
        },
        {
          type: 'table',
          headers: ['Sezione condizionata', 'Cosa succede'],
          rows: [
            ['Quale campo la apre', 'Un attributo sì/no (casella o interruttore) posizionato in un\'altra sezione del layout, che a sua volta non è condizionata. Una sezione da cui dipendono altre sezioni non può essere condizionata.'],
            ['Nella scheda', 'Con il campo spento la sezione non compare; accendendolo compare al suo posto. Nel dettaglio di un record compare solo se il campo è attivo.'],
            ['Campi obbligatori', 'Valgono solo quando la sezione è visibile.'],
            ['Al salvataggio', 'Se il campo è spento, i valori della sezione vengono svuotati.'],
            ['Nell\'editor', 'Se togli il campo dal layout o lo sposti nella sezione che apre, la sezione torna Sempre visibile. Nell\'Anteprima live accendi il campo per vedere la sezione, con l\'indicazione "Visibile se: …".'],
          ],
        },
        {
          type: 'tip',
          text: 'Un campo non posizionato non si perde: compare in fondo, nella sezione Altre informazioni.',
        },
        {
          type: 'note',
          text: 'Se un\'offerta o una commessa contiene prodotti di più categorie, le sezioni vengono unite e ordinate per posizione: ad esempio, con righe ISO 9001 e 50001 i gruppi delle due norme compaiono prima di Tipologia di consulenza.',
        },
      ],
    },
]
