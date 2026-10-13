import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'commission-settlement-runs',
  title: 'Lanci',
  summary: 'Il lancio mensile della liquidazione: crea una bozza di estratto per ogni agente e società.',
  sections: [
    {
      id: 'overview',
      title: 'Panoramica',
      blocks: [
        {
          type: 'paragraph',
          text: 'La pagina è sotto **Contabilità › Provvigioni agenti › Lanci**. Un **lancio** prende il maturato aperto dei tuoi agenti e, per ogni coppia **agente + società emittente**, crea un **estratto in bozza** con imponibile, IVA, ritenuta, ENASARCO e netto da pagare. L’elenco mostra i lanci passati, in sola lettura; aprendo una riga rivedi l’esito di quel lancio.',
        },
      ],
    },
    {
      id: 'new-run',
      title: 'Come fare un lancio',
      blocks: [
        {
          type: 'steps',
          items: [
            'Premi **Nuovo lancio**.',
            'Indica il periodo. **Periodo al** è la data che conta: entrano **tutti** i movimenti aperti maturati fino a quel giorno, anche quelli di periodi precedenti rimasti in sospeso. **Periodo dal** è solo informativo. Per default è proposto il mese scorso.',
            'Se vuoi, restringi con **Società**, **Agenti** e **Ruoli** (Commerciale, Segnalatore, Supervisore). Lasciando vuoto si considera tutto.',
            'Se vuoi, indica una **soglia minima**: gli estratti sotto quell’importo non vengono creati. Se la lasci vuota vale la soglia predefinita del sistema.',
            'Premi **Lancia**: il lancio è immediato e al termine vedi l’esito.',
          ],
        },
      ],
    },
    {
      id: 'outcome',
      title: 'L’esito: creati e saltati',
      blocks: [
        {
          type: 'paragraph',
          text: 'L’esito elenca gli **estratti creati**, con un link per aprire ogni bozza, e gli **agenti saltati** con il motivo. Un agente saltato **non perde nulla**: i suoi movimenti restano aperti e verranno ripresi dal lancio successivo.',
        },
        {
          type: 'table',
          headers: ['Motivo', 'Cosa significa'],
          rows: [
            ['**Esiste già una bozza**', 'Per quell’agente e società c’è già una bozza: ricalcolala o eliminala dalla pagina Estratti.'],
            ['**Saldo non positivo**', 'Il totale (maturato più rettifiche) è zero o negativo, per esempio per uno storno: il saldo viene **riportato** al lancio successivo.'],
            ['**Sotto la soglia minima**', 'L’imponibile è inferiore alla soglia: il maturato resta aperto e si somma al mese dopo.'],
            ['**Nessun profilo fiscale**', 'L’agente non ha un profilo fiscale valido alla data finale del periodo: crealo in Profili fiscali.'],
            ['**Parametri ENASARCO mancanti**', 'L’agente è soggetto a ENASARCO ma manca la riga dell’anno in Parametri ENASARCO.'],
          ],
        },
        {
          type: 'tip',
          text: 'Gli utenti interni (dipendenti) non vengono mai liquidati da qui: solo gli agenti, cioè i referenti.',
        },
      ],
    },
    {
      id: 'in-development',
      title: 'Modulo in sviluppo',
      blocks: [
        {
          type: 'note',
          text: 'Modulo in fase di sviluppo. La guida verrà completata quando il modulo sarà completato.',
        },
      ],
    },
  ],
}

export default guide
