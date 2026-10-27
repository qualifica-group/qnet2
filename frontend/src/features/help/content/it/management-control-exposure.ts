import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'management-control-exposure',
  title: 'Scadenzario ed esposizione',
  summary: 'Quanto resta da incassare e da pagare e con quale ritardo, gli impegni verso i fornitori, l’esposizione netta per società e sede, i tempi medi di incasso e pagamento e la concentrazione dei clienti.',
  sections: [
    {
      id: 'overview',
      title: 'Cos’è',
      blocks: [
        {
          type: 'paragraph',
          text: 'La pagina **Scadenzario ed esposizione** risponde alle domande “quanto devo ancora incassare e pagare?” e “quanto sono esposto?”. La trovi in **Contabilità › Controllo di gestione › Scadenzario ed esposizione**. È **solo di consultazione** e mostra importi **sul totale del documento** (IVA compresa), in forma aggregata: non c’è l’elenco dei singoli documenti, che restano nelle pagine **Scadenze** e **Partitario**.',
        },
        {
          type: 'paragraph',
          text: 'In alto trovi 8 riquadri: **Da incassare**, **Scaduto attivo**, **Da pagare**, **Scaduto passivo**, **Impegnato**, **Esposizione netta**, **DSO** e **DPO**. Sotto, le scadenze per fascia, i documenti per stato, i clienti e fornitori con più scaduto, gli impegni, l’esposizione per società o sede e la concentrazione dei clienti.',
        },
        {
          type: 'tip',
          text: 'I pulsanti **Apri Scadenze attive** e **Apri Scadenze passive** portano alle pagine con l’elenco delle rate; il nome di un cliente o fornitore apre il suo **Partitario**.',
        },
      ],
    },
    {
      id: 'filters',
      title: 'Filtri e periodo',
      blocks: [
        {
          type: 'paragraph',
          text: 'Gli importi **da incassare, da pagare, scaduti e impegnati** sono una **fotografia a oggi**: non cambiano se sposti il periodo. Il **periodo** (di default dal 1° gennaio a oggi) vale invece per i **flussi**: fatturato attivo e passivo del periodo, incassato e pagato nel periodo, tempi medi (DSO e DPO) e concentrazione dei clienti.',
        },
        {
          type: 'paragraph',
          text: 'Puoi restringere per **società**, **sede società** e **sede operativa**. Le fatture attive prendono le sedi dall’offerta collegata: una fattura senza offerta non ha sede, quindi esce quando filtri per sede e compare in **Non attribuito** quando raggruppi per sede.',
        },
        {
          type: 'table',
          headers: ['Filtro', 'Cosa fa'],
          rows: [
            ['**Tipo documento**', 'Fatture (predefinito), proforma oppure tutti, su entrambi i lati'],
            ['**Esposizione per**', 'Raggruppa l’esposizione per società, sede società o sede operativa'],
            ['**Primi per scaduto**', 'Quante righe mostrare nelle classifiche (5, 10 o 25)'],
          ],
        },
      ],
    },
    {
      id: 'aging',
      title: 'Scadenzario per fascia',
      blocks: [
        {
          type: 'paragraph',
          text: 'Ogni rata aperta finisce in una fascia in base ai giorni rispetto a oggi: **scaduto** da 1-30, 31-60, 61-90 e oltre 90 giorni, oppure **a scadere** tra 0-30, 31-60 e oltre 60 giorni. La tabella affianca **da incassare** e **da pagare**, con il totale aperto in fondo; il grafico mostra le stesse coppie di valori.',
        },
        {
          type: 'paragraph',
          text: 'Le rate passive sono già **al netto di ritenuta ed ENASARCO**, cioè l’importo che si paga davvero. Sono comprese le fatture passive degli estratti conto provvigioni, perché sono debiti reali verso gli agenti.',
        },
        {
          type: 'list',
          items: [
            '**DSO**: giorni medi tra la data del documento e l’incasso, per le rate incassate nel periodo, pesati sull’importo incassato.',
            '**DPO**: lo stesso calcolo per i pagamenti ai fornitori.',
            'Se nel periodo non c’è nessun incasso o pagamento, il valore è **n.d.**',
          ],
        },
      ],
    },
    {
      id: 'status',
      title: 'Documenti per stato',
      blocks: [
        {
          type: 'paragraph',
          text: 'Per ogni lato vedi quanti documenti sono **Pagati**, **Non scaduti**, **Scaduti** (fino a 21 giorni di ritardo) e **Scaduti gravi** (oltre 21 giorni), con l’importo ancora aperto. Lo stato di un documento segue la sua **rata aperta più vecchia**.',
        },
      ],
    },
    {
      id: 'top',
      title: 'Clienti e fornitori con più scaduto',
      blocks: [
        {
          type: 'paragraph',
          text: 'Le due classifiche ordinano per **scaduto decrescente**, poi per aperto. Per ogni riga vedi l’aperto, lo scaduto e la **fascia peggiore**: la fascia di scaduto più alta con importo, oppure la fascia a scadere più lontana se non c’è scaduto.',
        },
      ],
    },
    {
      id: 'committed',
      title: 'Impegni verso i fornitori',
      blocks: [
        {
          type: 'paragraph',
          text: 'L’**impegnato** è il valore degli ordini a fornitore approvati (o evasi) **non ancora fatturato**. Lo vedi in totale, **con commessa** e **senza commessa**, per **fornitore** e per **tipo di costo** (con colore e natura: diretto, indiretto, generale). Sotto trovi anche il **da pagare per tipo di costo**, con la parte scaduta.',
        },
      ],
    },
    {
      id: 'exposure',
      title: 'Esposizione netta',
      blocks: [
        {
          type: 'paragraph',
          text: '**Esposizione netta = da incassare − da pagare − impegnato**. Un valore **negativo** (segno meno, in rosso) significa che gli impegni e i debiti superano i crediti. La tabella la scompone per **società**, **sede società** o **sede operativa** secondo il filtro “Esposizione per”; la riga **Non attribuito** raccoglie i documenti senza quel dato, e in fondo c’è il totale.',
        },
      ],
    },
    {
      id: 'concentration',
      title: 'Concentrazione clienti',
      blocks: [
        {
          type: 'paragraph',
          text: 'Mostra quanto del **fatturato del periodo** e dello **scaduto a oggi** è in mano ai primi clienti: per ciascuno l’importo e la **quota in percentuale** sul totale, più la **quota cumulata** dei primi N. Una quota alta su pochi clienti è un rischio di dipendenza.',
        },
      ],
    },
    {
      id: 'permissions',
      title: 'Permessi',
      blocks: [
        {
          type: 'paragraph',
          text: 'Serve il permesso **Controllo di gestione** (dal form Ruoli), lo stesso del conto economico. Chi lo ha vede i dati di **tutte** le società. Senza il permesso la voce di menu non compare.',
        },
      ],
    },
  ],
}

export default guide
