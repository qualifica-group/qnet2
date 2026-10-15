import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'commission-settlements',
  title: 'Estratti',
  summary: 'Gli estratti di liquidazione delle provvigioni: bozze da rivedere, emessi e annullati.',
  sections: [
    {
      id: 'overview',
      title: 'Panoramica',
      blocks: [
        {
          type: 'paragraph',
          text: 'La pagina è sotto **Contabilità › Provvigioni agenti › Estratti**. Un **estratto** raccoglie i movimenti di provvigione di un agente per una società e calcola quanto va pagato. Nascono come **bozza** da un lancio.',
        },
        {
          type: 'paragraph',
          text: 'Le **schede** in alto filtrano per stato: **Bozze** (predefinita), **Emessi**, **Annullati** e **Tutti**. Sotto la tabella trovi i **totali** del filtro corrente; puoi raggruppare per stato, agente, società e mese. Dalla riga apri la scheda dell’estratto: in alto stato e pulsanti delle azioni consentite, poi dati dell’estratto, totali, movimenti e, se hai il permesso, la cronologia delle modifiche.',
        },
      ],
    },
    {
      id: 'amounts',
      title: 'Come si calcolano gli importi',
      blocks: [
        {
          type: 'list',
          items: [
            '**Imponibile** = maturato (provvigioni meno storni) + rettifiche.',
            '**IVA** = imponibile × aliquota IVA del profilo fiscale. Senza aliquota nel profilo l’operazione è senza IVA e l’IVA è zero.',
            '**Ritenuta d’acconto** = imponibile × base imponibile (%) × aliquota ritenuta (%), se la ritenuta è attiva nel profilo.',
            '**ENASARCO**: si calcola su una base che non supera il **massimale** annuo. Il massimale (monomandatario o plurimandatario) è quello dei Parametri ENASARCO dell’anno del periodo, al netto di quanto già versato negli estratti **emessi** dello stesso agente e società nello stesso anno. Sulla base si applica l’aliquota totale; la quota dell’agente si **trattiene**, quella del preponente è **solo informativa** (è un costo dell’azienda).',
            '**Netto da pagare** = imponibile + IVA − ritenuta − ENASARCO a carico dell’agente.',
          ],
        },
        {
          type: 'note',
          text: 'Ogni importo è arrotondato al centesimo voce per voce. Aliquote, base e tipo di mandato sono **copiati nell’estratto** al calcolo: modificare dopo il profilo non cambia un estratto già emesso; una bozza invece usa i valori correnti quando la ricalcoli.',
        },
        {
          type: 'paragraph',
          text: 'Alcuni importi possono essere nascosti dai permessi del tuo ruolo: in quel caso non compaiono.',
        },
      ],
    },
    {
      id: 'carry-over',
      title: 'Salti e riporto',
      blocks: [
        {
          type: 'paragraph',
          text: 'Se il saldo di un agente è zero o negativo, o sotto la soglia, o manca il profilo o i parametri ENASARCO, **l’estratto non viene creato** (lo vedi nell’esito del lancio) ma i movimenti restano aperti: al lancio successivo si sommano al nuovo maturato. Così uno storno non va mai perso.',
        },
      ],
    },
    {
      id: 'states',
      title: 'Stati e azioni',
      blocks: [
        {
          type: 'table',
          headers: ['Stato', 'Cosa puoi fare'],
          rows: [
            ['**Bozza**', '**Ricalcola** (aggancia i movimenti nuovi fino alla data finale e rifà i conti), modificare le **note** (matita sulla riga Note, poi **Salva**), aggiungere o correggere **rettifiche**, **Emetti**, **Elimina bozza** (i movimenti tornano aperti).'],
            ['**Emesso**', 'L’estratto riceve il **numero** progressivo (n/anno, per società) e diventa immutabile. L’unica azione è **Annulla estratto**.'],
            ['**Annullato**', 'Il motivo è obbligatorio. I movimenti tornano aperti e potranno finire in un nuovo estratto; il numero non viene riutilizzato.'],
          ],
        },
        {
          type: 'paragraph',
          text: '**Emetti** chiede conferma, ricalcola un’ultima volta e non è possibile se l’imponibile è zero o negativo. Se un’azione non è più consentita (per esempio qualcuno ha già emesso la bozza) compare un messaggio di errore e l’estratto resta com’è. I pulsanti compaiono solo se il tuo ruolo ha il permesso.',
        },
      ],
    },
    {
      id: 'adjustments',
      title: 'Rettifiche',
      blocks: [
        {
          type: 'paragraph',
          text: 'Una **rettifica** è una correzione manuale con **segno**: positiva aumenta il dovuto all’agente, negativa lo riduce. Servono importo (diverso da zero), data e **note obbligatorie** (max 500 caratteri).',
        },
        {
          type: 'list',
          items: [
            '**Dalla pagina Estratti o dal Maturato** (**Nuova rettifica**): si apre una scheda laterale; apri le righe Agente e Società, poi Importo, Data e Note, conferma ognuna con **Fatto** e premi **Salva**; la rettifica resta aperta ed entra nel primo lancio utile.',
            '**Dentro una bozza** (pulsante **Nuova rettifica** sulla sezione Movimenti): agente e società sono quelli della bozza, la rettifica vi viene agganciata subito e la bozza si ricalcola. Finché la bozza non è emessa puoi **modificare** o **eliminare** le rettifiche dalla lista dei movimenti.',
          ],
        },
      ],
    },
    {
      id: 'in-development',
      title: 'Modulo in sviluppo',
      blocks: [
        {
          type: 'note',
          text: 'Modulo in fase di sviluppo. Stampa e invio dell’estratto, registrazione del documento dell’agente e pagamento arriveranno nelle prossime versioni.',
        },
      ],
    },
  ],
}

export default guide
