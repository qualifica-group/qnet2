import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'management-control-accounts',
  title: 'Conti',
  summary: 'Incassi e pagamenti del periodo per conto finanziario e mese per mese, con il netto.',
  sections: [
    {
      id: 'overview',
      title: 'Cos’è',
      blocks: [
        {
          type: 'paragraph',
          text: 'La pagina **Conti** risponde alla domanda “su quali conti sono entrati e usciti i soldi nel periodo?”. La trovi in **Controllo di gestione › Conti**. È **solo di consultazione** e mostra importi aggregati, senza elenchi per documento.',
        },
        {
          type: 'paragraph',
          text: 'In alto trovi 3 riquadri: **Incassi**, **Pagamenti** e **Netto** (incassi meno pagamenti). Sotto, le tabelle per conto e i grafici.',
        },
      ],
    },
    {
      id: 'filters',
      title: 'Filtri e periodo',
      blocks: [
        {
          type: 'paragraph',
          text: 'Di default il periodo va dal **1 gennaio a oggi**; può estendersi al massimo a **24 mesi**. Gli incassi contano per la **data di incasso** della rata, i pagamenti per la **data di pagamento**. Puoi restringere per **società**, **sede società** e **sede operativa**: le fatture attive prendono le sedi dall’offerta collegata, i documenti passivi dalla propria testata.',
        },
        {
          type: 'note',
          text: 'Gli importi sono calcolati sul **totale del documento**, non sul solo imponibile.',
        },
      ],
    },
    {
      id: 'accounts',
      title: 'Incassi e pagamenti per conto',
      blocks: [
        {
          type: 'paragraph',
          text: 'Due tabelle, una per gli **incassi** e una per i **pagamenti**, elencano i conti con **tipo** (conto bancario, carta, contanti), importo e numero di rate, dal più alto al più basso. Per i pagamenti, se la rata non ha un conto vale quello della fattura passiva. Le rate senza alcun conto vanno in **Non attribuito**, in fondo.',
        },
      ],
    },
    {
      id: 'months',
      title: 'Andamento per mese',
      blocks: [
        {
          type: 'paragraph',
          text: 'Il primo grafico affianca **incassi e pagamenti di ogni mese** del periodo, anche dei mesi senza movimenti. Gli altri due mostrano come si **ripartiscono** incassi e pagamenti tra i conti, con un colore per conto.',
        },
      ],
    },
    {
      id: 'permissions',
      title: 'Permessi',
      blocks: [
        {
          type: 'paragraph',
          text: 'Serve il permesso **Controllo di gestione** (dal form Ruoli), lo stesso delle altre pagine del modulo. Chi lo ha vede i dati di **tutte** le società. Senza il permesso la voce di menu non compare.',
        },
      ],
    },
  ],
}

export default guide
