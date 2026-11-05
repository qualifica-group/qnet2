import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'account-statements',
  title: 'Estratto conto',
  summary: 'Il saldo di ogni conto e tutti i suoi movimenti, uno per uno, con il saldo progressivo.',
  sections: [
    {
      id: 'overview',
      title: 'A cosa serve',
      blocks: [
        { type: 'paragraph', text: 'La pagina si trova in **Contabilità › Finanziario › Estratto conto**. Per un conto (banca, cassa o carta) mostra **quanto c’è oggi** e **come si è arrivati a quel saldo**, movimento per movimento.' },
      ],
    },
    {
      id: 'choose',
      title: 'Scegliere società, conto e periodo',
      blocks: [
        { type: 'steps', items: ['Scegli la **Società** (o **Tutte**) per restringere l’elenco dei conti.', 'Scegli il **Conto**: accanto al nome vedi il saldo.', 'Imposta il periodo con **Dal** e **Al** (di default dal primo del mese a oggi).'] },
        { type: 'paragraph', text: 'Per affinare ancora puoi usare i filtri **Tipi**, **Anagrafica** e **Ricerca** (descrizione o numero documento).' },
      ],
    },
    {
      id: 'sources',
      title: 'Cosa mostra',
      blocks: [
        { type: 'paragraph', text: 'L’estratto unisce in ordine di data:' },
        {
          type: 'list',
          items: [
            'gli **Incassi** delle fatture attive e i **Pagamenti** delle fatture passive, presi dalle rate;',
            'i movimenti registrati a mano in **Movimenti di tesoreria**: **Entrata manuale**, **Uscita manuale**, **Giroconto** e **Rettifica**.',
          ],
        },
        { type: 'paragraph', text: 'Ogni riga riporta **Data**, **Tipo**, **Descrizione**, **Anagrafica**, **Documento**, **Entrate**, **Uscite** e **Saldo**.' },
      ],
    },
    {
      id: 'balances',
      title: 'I saldi',
      blocks: [
        {
          type: 'table',
          headers: ['Voce', 'Significato'],
          rows: [
            ['**Saldo di apertura conto**', 'Il saldo da cui parte il conto, alla sua data di apertura (vedi la guida Gestione Conti). Quello che è successo prima non entra nell’estratto.'],
            ['**Saldo iniziale periodo**', 'Il saldo del conto il giorno prima del **Dal**.'],
            ['**Totale entrate** / **Totale uscite**', 'Somma delle entrate e delle uscite del periodo.'],
            ['**Saldo finale**', 'Il saldo del conto alla data del **Al**.'],
            ['**Saldo a oggi**', 'Il saldo del conto oggi, qualunque periodo tu stia guardando.'],
            ['**Saldo** (colonna)', 'Il saldo progressivo dopo ogni riga.'],
          ],
        },
        { type: 'note', text: 'Con i filtri attivi compare l’avviso: il **saldo progressivo resta quello reale del conto**, non quello delle sole righe mostrate.' },
      ],
    },
    {
      id: 'export',
      title: 'Esportare in Excel',
      blocks: [
        { type: 'paragraph', text: 'Il pulsante **Esporta** scarica un file **Excel** con le stesse righe, colonne e saldo progressivo che vedi a schermo, con gli stessi filtri e lo stesso periodo. Serve il permesso di esportazione.' },
      ],
    },
    {
      id: 'row-limit',
      title: 'Troppe righe',
      blocks: [
        { type: 'paragraph', text: 'Se il periodo contiene troppe righe compare il messaggio **Troppe righe: restringi il periodo.** Riduci l’intervallo tra **Dal** e **Al** (ad esempio un mese o un trimestre alla volta); lo stesso vale per l’esportazione.' },
      ],
    },
    {
      id: 'to-verify',
      title: 'Il riquadro Da verificare',
      blocks: [
        { type: 'paragraph', text: 'Un incasso o un pagamento **senza conto** non entra in nessun saldo. Il riquadro **Da verificare** ti dice quanti sono (**incassi o pagamenti senza conto, esclusi dai saldi**) e con **Mostra elenco** li elenca.' },
        { type: 'steps', items: ['Apri **Mostra elenco** e individua la rata.', 'Per un incasso, apri la scadenza in **Contabilità › Attiva › Scadenze**, usa **Annulla incasso** e registralo di nuovo scegliendo il **Conto** (vedi la guida Scadenze).', 'Per un pagamento, indica il conto sulla scadenza passiva o sulla fattura passiva.', 'Torna all’estratto: la voce esce dall’elenco ed entra nel saldo del conto.'] },
      ],
    },
    {
      id: 'cards',
      title: 'Le carte',
      blocks: [
        { type: 'paragraph', text: 'Una **carta** è un conto con un **saldo proprio**: le spese la fanno scendere. L’**addebito mensile** sul conto corrente si registra come **Giroconto** dalla banca alla carta, così il saldo della carta risale e quello della banca scende (il giroconto è ammesso solo tra conti della stessa società).' },
      ],
    },
  ],
}

export default guide
