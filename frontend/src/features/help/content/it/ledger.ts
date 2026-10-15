import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'ledger',
  title: 'Partitario',
  summary: 'Quanto resta da incassare da un cliente o da pagare a un fornitore, documento per documento, con saldo progressivo e anzianità delle scadenze.',
  sections: [
    {
      id: 'overview',
      title: 'Cos’è',
      blocks: [
        {
          type: 'paragraph',
          text: 'Il **Partitario** mostra, per una anagrafica, i suoi documenti in ordine di data con **Dare**, **Avere**, **saldo progressivo** e **stato**. Lo trovi in **Contabilità › Partitario** (scegli l’anagrafica dall’elenco) e nella scheda di ogni anagrafica, nella scheda **Partitario**.',
        },
        {
          type: 'paragraph',
          text: 'È **solo di consultazione**: gli incassi e i pagamenti si registrano dai moduli Scadenze, non da qui.',
        },
      ],
    },
    {
      id: 'sections',
      title: 'Cliente e fornitore',
      blocks: [
        {
          type: 'paragraph',
          text: 'La sezione **Cliente** raccoglie le fatture attive e compare se puoi vedere le fatture attive. La sezione **Fornitore** raccoglie le fatture passive e compare se puoi vedere le fatture passive e l’anagrafica è un fornitore o ha documenti passivi. Una sezione senza permesso non viene mostrata.',
        },
      ],
    },
    {
      id: 'debit-credit',
      title: 'Dare e Avere',
      blocks: [
        {
          type: 'table',
          headers: ['Lato', 'Dare', 'Avere'],
          rows: [
            ['**Cliente**', 'Importo del documento', 'Importo incassato'],
            ['**Fornitore**', 'Importo pagato', 'Importo del documento'],
          ],
        },
        {
          type: 'paragraph',
          text: 'La colonna **Saldo** è sempre il **residuo cumulato**: quanto resta da incassare (cliente) o da pagare (fornitore) dopo quel documento.',
        },
        {
          type: 'note',
          text: 'Per il fornitore l’importo del documento è il **Netto da pagare** della fattura passiva (totale meno **Ritenuta d’acconto** ed **ENASARCO a carico**), perché è quello che si paga davvero: il saldo torna a zero quando tutte le rate sono pagate.',
        },
      ],
    },
    {
      id: 'filters',
      title: 'Filtri e periodo',
      blocks: [
        {
          type: 'paragraph',
          text: 'Puoi scegliere il **tipo di documento** (Tutte, Proforma, Fatture; predefinito **Fatture**), il **periodo** sulla data documento (predefinito dal 1° gennaio dell’anno in corso a oggi) e filtrare per **società**, **sede società** e **sede operativa**, anche più valori insieme. I filtri valgono per documenti, saldo iniziale e riepilogo.',
        },
        {
          type: 'note',
          text: 'Le fatture attive senza offerta collegata non hanno sede: con un filtro sede attivo non compaiono.',
        },
      ],
    },
    {
      id: 'opening-balance',
      title: 'Saldo iniziale',
      blocks: [
        {
          type: 'paragraph',
          text: 'Quando indichi una data **Dal**, i documenti precedenti non vengono elencati ma confluiscono nella riga **Saldo iniziale**, da cui parte il saldo progressivo. Senza data iniziale vedi tutta la storia e il saldo iniziale è zero.',
        },
      ],
    },
    {
      id: 'as-of-today',
      title: 'Situazione a oggi',
      blocks: [
        {
          type: 'paragraph',
          text: 'Gli incassi e i pagamenti contano **a oggi**, qualunque sia la loro data, anche se successiva alla fine del periodo scelto.',
        },
      ],
    },
    {
      id: 'summary-aging',
      title: 'Riepilogo e anzianità',
      blocks: [
        {
          type: 'paragraph',
          text: 'Il riepilogo mostra **fatturato**, **incassato** (o **pagato**), **residuo**, **scaduto** e **a scadere**. L’**anzianità** divide le scadenze ancora aperte: scadute da 1-30, 31-60, 61-90 e oltre 90 giorni; a scadere entro 30 giorni (oggi incluso), tra 31 e 60 e oltre 60. Include anche le scadenze dei documenti del saldo iniziale.',
        },
      ],
    },
    {
      id: 'documents',
      title: 'Documenti e scadenze',
      blocks: [
        {
          type: 'paragraph',
          text: 'Lo **stato** del documento è **Chiusa** (tutto incassato o pagato), **Aperta** (scadenze non ancora scadute), **Scaduta** o **Gravemente scaduta** (oltre 21 giorni di ritardo). Con il pulsante a sinistra del numero apri le **scadenze** del documento: importo, data e importo incassato o pagato, residuo e giorni di ritardo.',
        },
      ],
    },
    {
      id: 'permissions',
      title: 'Permessi',
      blocks: [
        {
          type: 'paragraph',
          text: 'Servono il permesso **Partitario** (dal form Ruolo) e, per ciascuna sezione, quello delle fatture attive o passive. Senza il permesso Partitario la voce di menu e la scheda nell’anagrafica non compaiono.',
        },
      ],
    },
  ],
}

export default guide
