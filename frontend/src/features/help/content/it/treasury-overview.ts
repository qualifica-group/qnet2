import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'treasury-overview',
  title: 'Quadro finanziario',
  summary: 'In una pagina: liquidità dei conti, crediti e debiti aperti, posizione netta, previsione a 30/60/90 giorni e cosa verificare.',
  sections: [
    {
      id: 'overview',
      title: 'A cosa serve',
      blocks: [
        { type: 'paragraph', text: 'La pagina si trova in **Contabilità › Finanziario › Quadro finanziario**. È di sola lettura: riassume la situazione finanziaria a **oggi** e ogni numero apre l’elenco da cui viene.' },
        { type: 'paragraph', text: 'Con **Società** scegli una società o **Tutte**. Con una società scelta vedi solo i suoi conti e i documenti intestati a lei.' },
      ],
    },
    {
      id: 'liquidity',
      title: 'Liquidità',
      blocks: [
        { type: 'paragraph', text: 'Il **Totale conti** è la somma dei saldi a oggi di banche, carte e casse, calcolati come nell’**Estratto conto** (saldo di apertura più tutti i movimenti successivi). Sotto trovi ogni conto con il suo saldo: cliccandolo si apre il suo estratto conto.' },
      ],
    },
    {
      id: 'schedule',
      title: 'Crediti e debiti',
      blocks: [
        { type: 'paragraph', text: '**Crediti verso clienti** e **Debiti verso fornitori** sommano quanto resta da incassare o pagare sulle rate di fatture e proforma, già al netto di abbuoni e stralci.' },
        {
          type: 'table',
          headers: ['Voce', 'Significato'],
          rows: [
            ['**Aperto**', 'Tutto il residuo delle rate non ancora chiuse.'],
            ['**A scadere**', 'Il residuo delle rate con scadenza da oggi in poi.'],
            ['**Scaduto**', 'Il residuo delle rate con scadenza già passata, diviso nelle fasce 1-30, 31-60, 61-90 e oltre 90 giorni di ritardo.'],
          ],
        },
        { type: 'paragraph', text: 'I numeri aprono le **Scadenze** attive o passive.' },
      ],
    },
    {
      id: 'net-position',
      title: 'Posizione netta',
      blocks: [
        { type: 'paragraph', text: 'È **liquidità + crediti aperti - debiti aperti**: quanto resterebbe se oggi tutti pagassero e si pagasse tutto. Gli ordini ai fornitori non ancora fatturati non sono inclusi.' },
      ],
    },
    {
      id: 'forecast',
      title: 'Previsione 30/60/90 giorni',
      blocks: [
        { type: 'paragraph', text: 'Per ogni periodo la tabella mostra gli **Incassi attesi** e i **Pagamenti attesi** delle rate che scadono da oggi alla data indicata, e il **Saldo previsto** = liquidità di oggi + incassi attesi - pagamenti attesi.' },
        { type: 'note', text: 'Gli importi già scaduti **non** entrano nella previsione: non si sa quando arriveranno. Li trovi nelle fasce di scaduto.' },
      ],
    },
    {
      id: 'to-verify',
      title: 'Da verificare',
      blocks: [
        {
          type: 'list',
          items: [
            '**Incassi/pagamenti senza conto**: non entrano in nessun saldo. Il link apre l’**Estratto conto**, dove il riquadro Da verificare li elenca.',
            '**Rettifiche di saldo nel mese**: le rettifiche registrate nel mese corrente, con il totale in entrata e in uscita. Il link apre i **Movimenti di tesoreria**.',
          ],
        },
      ],
    },
    {
      id: 'permissions',
      title: 'Chi la vede',
      blocks: [
        { type: 'paragraph', text: 'La pagina è visibile a chi ha il permesso di consultare i **Movimenti di tesoreria**, lo stesso dell’Estratto conto.' },
      ],
    },
    {
      id: 'unreconciled',
      title: 'Righe non spuntate e link filtrati',
      blocks: [
        { type: 'paragraph', text: 'In **Da verificare** la voce **righe non spuntate** conta le righe dell\'estratto, fino a oggi, non ancora spuntate sull\'estratto della banca, con un link per conto che apre il suo estratto gia\' filtrato su **Non spuntate**.' },
        { type: 'paragraph', text: 'Le cifre di crediti e debiti aprono gli scadenziari gia\' filtrati: **Aperto** su non incassate/pagate, **A scadere** su a scadere, **Scaduto** e le fasce su scadute.' },
      ],
    },
  ],
}

export default guide
