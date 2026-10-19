import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'staff-trainings',
  title: 'Formazione',
  summary: 'Il registro dei corsi fatti dal personale, con esito, attestato e scadenza, e la situazione di chi è in regola.',
  sections: [
    {
      id: 'overview',
      title: 'A cosa serve',
      blocks: [
        {
          type: 'paragraph',
          text: 'La pagina **Formazione** raccoglie i corsi interni fatti da ogni persona e mostra quali corsi richiesti dalle sue mansioni sono validi, in scadenza, scaduti o mancanti. Ha due schede: **Registro** e **Situazione**.',
        },
      ],
    },
    {
      id: 'permissions',
      title: 'Chi vede cosa',
      blocks: [
        {
          type: 'table',
          headers: ['Permesso', 'Cosa permette'],
          rows: [
            ['Visualizza elenco', 'Apre la pagina e mostra la propria formazione.'],
            ['Visualizza team', 'Mostra anche la formazione delle persone che fanno capo a te.'],
            ['Visualizza tutti', 'Mostra la formazione di tutte le persone.'],
            ['Gestire', 'Registra, modifica ed elimina le formazioni e carica l’attestato per chiunque (ufficio del personale).'],
          ],
        },
        {
          type: 'note',
          text: 'Vale il livello più ampio che hai. Una formazione fuori dalla tua visibilità non si può aprire.',
        },
      ],
    },
    {
      id: 'register',
      title: 'Registro',
      blocks: [
        {
          type: 'paragraph',
          text: 'L’elenco delle formazioni che puoi vedere, con persona, corso, data di completamento, scadenza, esito e presenza dell’attestato. Fai clic su una riga per aprirne il dettaglio.',
        },
        {
          type: 'steps',
          items: [
            'Fai clic su **Registra formazione** (visibile con il permesso Gestire).',
            'Scegli la persona e il corso, indica la data di completamento (non può essere futura) e l’esito.',
            'Aggiungi eventuali note e salva: la scadenza viene calcolata e la trovi nel dettaglio.',
            'Dal dettaglio puoi caricare l’attestato (PDF, JPG o PNG), sostituirlo o rimuoverlo.',
          ],
        },
      ],
    },
    {
      id: 'expiry',
      title: 'Scadenza',
      blocks: [
        {
          type: 'paragraph',
          text: 'La scadenza è la data di completamento più la validità in mesi del corso; se il corso non scade, resta vuota. Viene calcolata al salvataggio e non cambia più: se modifichi la validità del corso, le formazioni già registrate restano com’erano.',
        },
      ],
    },
    {
      id: 'compliance',
      title: 'Situazione',
      blocks: [
        {
          type: 'paragraph',
          text: 'Per ogni persona e corso mostra uno stato, sempre con icona e testo. Fai clic sul corso di una riga per aprire la formazione che lo giustifica. Puoi filtrare per stato, funzione aziendale e corso.',
        },
        {
          type: 'table',
          headers: ['Stato', 'Significato'],
          rows: [
            ['Valido', 'L’ultima formazione superata è ancora valida.'],
            ['In scadenza', 'L’ultima formazione superata scade a breve (la finestra è indicata nella legenda, per esempio entro 60 giorni).'],
            ['Scaduto', 'L’ultima formazione superata è scaduta.'],
            ['Mancante', 'Il corso è richiesto da una mansione della persona e non c’è una formazione superata.'],
          ],
        },
        {
          type: 'tip',
          text: 'Un corso non richiesto compare solo per chi l’ha fatto. Contano solo le formazioni con esito Superato, la più recente per ogni corso.',
        },
      ],
    },
  ],
}

export default guide
