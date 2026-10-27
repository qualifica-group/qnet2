import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'campaigns',
  title: 'Campagne',
  summary: "La campagna è l'azione di marketing che genera i lead, autonoma o collegata a un progetto.",
  sections: [
    {
      id: 'overview',
      title: "Cos'è una campagna",
      blocks: [
        {
          type: 'paragraph',
          text: 'La campagna è l\'azione di marketing che genera i lead. Può essere **Autonoma** oppure **Collegata a un progetto**.',
        },
      ],
    },
    {
      id: 'create-a-campaign',
      title: 'Creare una campagna',
      blocks: [
        {
          type: 'steps',
          items: [
            'Apri **Commerciale › Marketing e Lead › Campagne** e clicca **Nuova campagna**: il modulo ha lo stesso aspetto della scheda, con le stesse sezioni e **righe chiuse** (il Codice è già proposto, lo stato **Nuovo** e il paese predefinito sono già impostati).',
            "Fai clic su una riga (o sulla matita) per aprirla, compila e premi **Fatto** per tenere il valore o **Ripristina** per riportarlo com'era.",
            'Compila **Denominazione**, le **Righe prodotto** e la **Data inizio**; in **Collegamento al progetto** scegli un **Progetto**, se serve.',
            'Indica una **Fonte**, se serve (facoltativa): i lead generati da questa campagna la ereditano, precompilata e modificabile.',
            'Clicca **Salva**: QNet controlla tutto e crea la campagna. Se esci senza salvare ti viene chiesta conferma.',
          ],
        },
        {
          type: 'note',
          text: "L'azione **Duplica** apre la creazione già compilata con i dati della campagna di partenza (progetto compreso): nuovo codice e denominazione con « (copia)».",
        },
      ],
    },
    {
      id: 'edit-a-campaign',
      title: 'Modificare una campagna',
      blocks: [
        {
          type: 'paragraph',
          text: 'Non esiste una pagina di modifica separata: la campagna si modifica **direttamente dalla sua scheda**, un campo alla volta.',
        },
        {
          type: 'steps',
          items: [
            "Apri la campagna dall'elenco.",
            'Passa col mouse sul campo da cambiare e premi la **matita** (o fai clic sul valore).',
            'Modifica il valore nel controllo che compare.',
            "Premi **Salva** per salvare quel campo; **Annulla** (o Esc, o un clic fuori dal campo aperto) lo chiude lasciandolo com'era, senza salvare.",
          ],
        },
        {
          type: 'note',
          text: "Il **Codice** non si modifica dopo la creazione. La **Geografia** si modifica in un unico riquadro: paese, regione, provincia e città cambiano insieme. Un campo senza matita è in sola lettura per il tuo ruolo (la riga **Progetto** non ha la matita se non puoi modificare anche i campi che il progetto compila).",
        },
      ],
    },
    {
      id: 'linking-a-project',
      title: 'Collegare un progetto',
      blocks: [
        {
          type: 'paragraph',
          text: 'Collegando un progetto:',
        },
        {
          type: 'list',
          items: [
            'la **Classificazione** (stato, linee prodotto) viene dal progetto ed è in sola lettura;',
            'i livelli geografici del progetto vengono ereditati e bloccati;',
            '**Partner**, **Sede** e **Data inizio** (se il progetto ne ha una) vengono precompilati e restano modificabili;',
            'modificando il Budget totale compare il **Budget residuo del progetto**.',
          ],
        },
        {
          type: 'paragraph',
          text: 'Nella scheda, salvare la riga **Progetto** salva anche i campi che il progetto ha compilato.',
        },
        {
          type: 'tip',
          text: 'Per modificare la classificazione di una campagna collegata, scollega prima il progetto: nello stesso riquadro della riga Progetto compaiono **Stato**, **Righe prodotto** e **Geografia** da compilare.',
        },
      ],
    },
  ],
}

export default guide
