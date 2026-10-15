import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'enasarco-parameters',
  title: 'Parametri ENASARCO',
  summary: 'Aliquote e massimali annuali usati per calcolare l’ENASARCO degli estratti.',
  sections: [
    {
      id: 'overview',
      title: 'Panoramica',
      blocks: [
        {
          type: 'paragraph',
          text: 'La pagina è sotto **Contabilità › Provvigioni agenti › Parametri ENASARCO**. Contiene **una riga per anno**: l’estratto usa i parametri dell’anno della **data finale** del periodo. I valori li inserisci tu: il sistema non ne contiene di preimpostati.',
        },
      ],
    },
    {
      id: 'fields',
      title: 'Campi',
      blocks: [
        {
          type: 'table',
          headers: ['Campo', 'Significato'],
          rows: [
            ['**Anno**', 'Da 2000 a 2100, uno solo per anno: un anno già presente dà errore sul campo.'],
            ['**Aliquota totale (%)**', 'Percentuale di contributo applicata alla base, da 0 a 100 con 2 decimali.'],
            ['**Quota agente (%)**', 'Parte del contributo che si trattiene all’agente. Il resto è a carico del preponente ed è solo informativo.'],
            ['**Massimale monomandatario / plurimandatario**', 'Importo annuo oltre il quale non si calcola più contributo. Si usa quello corrispondente al tipo di mandato del profilo fiscale.'],
            ['**Note**', 'Testo libero, fino a 2000 caratteri.'],
          ],
        },
        {
          type: 'warning',
          text: 'Se un agente ha ENASARCO attivo e manca la riga dell’anno, il lancio **salta** quell’agente (motivo: parametri mancanti). Inserisci l’anno e rilancia.',
        },
        {
          type: 'paragraph',
          text: 'Un anno già usato da un estratto **emesso** non si può eliminare.',
        },
      ],
    },
    {
      id: 'edit',
      title: 'Modificare i parametri',
      blocks: [
        {
          type: 'paragraph',
          text: 'Non c’è una pagina di modifica: nella scheda dell’anno clicca la **matita** accanto al valore, cambialo e conferma con **Salva**; **Annulla** ripristina il valore. Ogni riga si salva da sola.',
        },
        {
          type: 'paragraph',
          text: 'Nella lista puoi modificare direttamente in cella **Anno**, **Aliquota totale**, **Quota agente** e i due **massimali**. Le stesse regole valgono ovunque: un anno già presente o una percentuale oltre 100 vengono rifiutati.',
        },
        {
          type: 'paragraph',
          text: 'Il nuovo anno si crea con la stessa scheda: apri le righe, confermale con **Fatto** e premi **Salva**. Se esci senza salvare ti viene chiesta conferma.',
        },
      ],
    },
    {
      id: 'in-development',
      title: 'Modulo in sviluppo',
      blocks: [
        {
          type: 'note',
          text: 'Modulo in fase di sviluppo. Minimale, FIRR e versamenti trimestrali non sono ancora gestiti.',
        },
      ],
    },
  ],
}

export default guide
