import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'migrations',
  title: 'Migrazioni',
  summary: 'Il modulo Migrazioni importa nel gestionale i dati di un sistema esterno, di solito all’avvio: ruoli, utenti, società, sedi, referenti, prodotti.',
  sections: [
    {
      id: 'overview',
      title: 'Panoramica',
      blocks: [
        { type: 'paragraph', text: 'Il modulo si trova in **Amministrazione › Migrazioni** ed è riservato al ruolo **super-admin**. Ogni **Sorgente** rappresenta un tipo di dato da importare: Ruoli, Utenti, Funzioni aziendali, Società aziendali, Società sedi, Sedi operative, Tipi referente, Referenti, Fonti, Tag, Settori, Aliquote IVA, Attributi, Categorie prodotto e Prodotti.' },
      ],
    },
    {
      id: 'preview-step',
      title: 'Fase 1: controllo e anteprima',
      blocks: [
        { type: 'steps', items: ['Apri **Amministrazione › Migrazioni**.', 'Nel campo **Sorgente** scegli il tipo di dati da importare, ad esempio **Utenti** o **Prodotti**.', 'Il riquadro **Template atteso** elenca i campi che QNet si aspetta dal sistema esterno.', 'Fai clic su **Mostra anteprima dati esterni**. Usa **Precedente** e **Successiva** per sfogliare i dati.'] },
        { type: 'note', text: 'In questa fase non viene creato nulla.' },
      ],
    },
    {
      id: 'import-step',
      title: 'Fase 2: importazione',
      blocks: [
        { type: 'steps', items: ['Dopo aver controllato l’anteprima, fai clic su **Importa questa sorgente**.', 'Leggi la finestra di conferma e fai clic su **Avvia import**.', 'Segui l’avanzamento. Alla fine vedi il riepilogo: **Righe totali**, **Creati**, **Saltati** e **Falliti**.', 'Controlla **Avvisi ed errori** per le righe con problemi.'] },
        { type: 'tip', text: 'L’import continua anche se chiudi la finestra. Le schede già importate vengono saltate, quindi puoi ripetere l’import senza creare doppioni.' },
      ],
    },
    {
      id: 'mass-import',
      title: 'Importare tutto insieme',
      blocks: [
        { type: 'steps', items: ['Fai clic su **Configura ordine**.', 'Scegli le sorgenti da includere, trascinale nell’ordine giusto e fai clic su **Salva ordine**.', 'Fai clic su **Importa tutto** e poi su **Avvia import**.'] },
        { type: 'paragraph', text: 'Le sorgenti vengono importate una dopo l’altra. Se una fallisce, l’import si ferma e le successive risultano **Non eseguita**.' },
        { type: 'tip', text: 'Metti prima i dati da cui dipendono gli altri: i ruoli prima degli utenti, le società prima delle sedi.' },
      ],
    },
  ],
}

export default guide
