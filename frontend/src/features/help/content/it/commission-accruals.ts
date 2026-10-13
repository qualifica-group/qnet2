import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'commission-accruals',
  title: 'Maturato',
  summary: 'Il registro delle provvigioni maturate all’incasso delle fatture, con gli storni.',
  sections: [
    {
      id: 'overview',
      title: 'Panoramica',
      blocks: [
        {
          type: 'paragraph',
          text: 'La pagina è sotto **Contabilità › Provvigioni agenti › Maturato** ed è **di sola lettura**: elenca i movimenti di provvigione maturati per ogni destinatario (agente o utente), con fattura, cliente, offerta e prodotto di origine.',
        },
        {
          type: 'paragraph',
          text: 'Le **schede** in alto restringono l’elenco: **Aperto** (predefinita), **Liquidato esternamente** e **Tutti**. Sotto la tabella il **totale** riguarda tutti i movimenti del filtro corrente, non solo quelli visibili. Come in ogni elenco puoi ordinare, filtrare, esportare e **raggruppare** per destinatario, ruolo, mese di maturazione, tipo di movimento o cliente.',
        },
      ],
    },
    {
      id: 'accrual-rules',
      title: 'Come matura la provvigione',
      blocks: [
        {
          type: 'list',
          items: [
            'La provvigione di una riga d’offerta **matura quando la fattura che la contiene viene incassata**, in proporzione all’incassato: con metà fattura incassata matura metà della provvigione.',
            'Se la riga d’offerta è fatturata in più fatture, ogni fattura porta la sua quota; non si matura mai **più del 100%** della provvigione della riga.',
            'Se un incasso viene **annullato**, il sistema registra uno **storno** dello stesso importo. I movimenti già registrati non si modificano mai: la correzione è sempre un nuovo movimento.',
            'Lo storno cade alla **data di oggi**, anche se l’incasso annullato era di un periodo passato.',
            'Si maturano le provvigioni di **Commerciale, Segnalatore e Supervisore**; le provvigioni del Fornitore sono escluse.',
          ],
        },
      ],
    },
    {
      id: 'recalculation',
      title: 'Ricalcolo notturno',
      blocks: [
        {
          type: 'paragraph',
          text: 'Se cambi una provvigione sull’offerta dopo l’incasso, il maturato si adegua con un **ricalcolo automatico notturno**: registra solo la **differenza** (un nuovo maturato o uno storno) alla data del ricalcolo. Rieseguire il ricalcolo sugli stessi dati non crea movimenti duplicati.',
        },
        {
          type: 'note',
          text: 'I movimenti precedenti a una data di avvio possono essere marcati come **Liquidato esternamente**: non entreranno in nessuna liquidazione futura.',
        },
      ],
    },
    {
      id: 'in-development',
      title: 'Modulo in sviluppo',
      blocks: [
        {
          type: 'note',
          text: 'Modulo in fase di sviluppo. La guida verrà pubblicata quando il modulo sarà completato.',
        },
      ],
    },
  ],
}

export default guide
