import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'commission-settlements',
  title: 'Estratti',
  summary: 'Gli estratti di liquidazione delle provvigioni: dalla bozza al documento dell’agente, fino all’approvazione e alla fattura passiva.',
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
          text: 'Le **schede** in alto filtrano per stato: **Bozze** (predefinita), **Emessi**, **Documento ricevuto**, **Approvati**, **Annullati** e **Tutti**. L’elenco ha anche le colonne **Numero documento** e **Stato pagamento**. Sotto la tabella trovi i **totali** del filtro corrente; puoi raggruppare per stato, agente, società e mese. Dalla riga apri la scheda dell’estratto: in alto stato e pulsanti delle azioni consentite, poi dati dell’estratto, totali, movimenti e, se hai il permesso, la cronologia delle modifiche.',
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
            '**ENASARCO**: si calcola su una base che non supera il **massimale** annuo. Il massimale (monomandatario o plurimandatario) è quello dei Parametri ENASARCO dell’anno del periodo, al netto di quanto già versato negli estratti **emessi, con documento ricevuto o approvati** dello stesso agente e società nello stesso anno. Sulla base si applica l’aliquota totale; la quota dell’agente si **trattiene**, quella del preponente è **solo informativa** (è un costo dell’azienda).',
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
            ['**Emesso**', 'L’estratto riceve il **numero** progressivo (n/anno, per società) e diventa immutabile. Puoi **Registra documento** oppure **Annulla estratto**.'],
            ['**Documento ricevuto**', 'È stata registrata la fattura o notula dell’agente. Puoi **Approva** oppure **Rimuovi documento** (l’estratto torna **Emesso**). Da qui non si può annullare: rimuovi prima il documento.'],
            ['**Approvato**', 'È stata creata la fattura passiva collegata. L’estratto è **immutabile** e non si può annullare.'],
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
      id: 'agent-document',
      title: 'Documento dell’agente',
      blocks: [
        {
          type: 'paragraph',
          text: 'Quando l’agente invia la sua fattura o notula, da un estratto **Emesso** premi **Registra documento**. Inserisci **Numero documento**, **Data documento** (non nel futuro) e gli importi dichiarati: **Imponibile**, **IVA**, **Ritenuta d’acconto**, **ENASARCO agente** e **Totale documento**, che deve essere uguale a imponibile + IVA. I campi sono già compilati con gli importi dell’estratto.',
        },
        {
          type: 'paragraph',
          text: 'Gli importi sono confrontati con quelli dell’estratto entro la **tolleranza configurata** (di norma 1,00 €, voce per voce). Oltre la tolleranza il salvataggio è rifiutato e l’errore indica la voce, lo scarto e la tolleranza. Entro la tolleranza la differenza è accettata: l’estratto **conserva i suoi importi** e la sezione **Documento agente** della scheda mostra gli importi dichiarati e gli **scarti** rispetto all’estratto.',
        },
        {
          type: 'paragraph',
          text: 'Con **Rimuovi documento** (chiede conferma) i dati del documento vengono cancellati e l’estratto torna **Emesso**. Servono i permessi per registrare e rimuovere il documento.',
        },
      ],
    },
    {
      id: 'approval',
      title: 'Approvazione e fattura passiva',
      blocks: [
        {
          type: 'paragraph',
          text: 'Da un estratto **Documento ricevuto** premi **Approva**: la finestra **Approva estratto** crea la **fattura passiva** intestata al fornitore dell’agente, con le scadenze nello scadenziario passivo.',
        },
        {
          type: 'list',
          items: [
            '**Anagrafica fornitore**: se il referente ha già un’anagrafica fornitore collegata, è quella. Altrimenti la finestra propone le anagrafiche **candidate** (stesso codice fiscale o partita IVA del referente) da collegare, oppure **Crea dal referente** (serve anche il permesso di creare anagrafiche): la nuova anagrafica copia la scheda del referente, contatti e indirizzi compresi, ed è un fornitore. Se la scheda del referente è incompleta, il messaggio indica i campi mancanti. Il collegamento si può cambiare anche dal campo **Anagrafica fornitore** del referente.',
            '**Metodo di pagamento**, **Tipo di costo** e **Aliquota IVA** sono proposti dal **profilo fiscale** dell’agente (vedi la guida Profili fiscali), sono obbligatori e puoi modificarli.',
            'L’**anteprima delle rate** mostra le scadenze generate dal metodo di pagamento, calcolate sul **netto da pagare**.',
            'La fattura ha una riga unica «Provvigioni» del periodo, con numero e data del documento dell’agente, **Ritenuta d’acconto** ed **ENASARCO a carico** presi dal documento. L’IVA della riga deve stare entro la tolleranza dall’IVA del documento, altrimenti l’errore compare sull’aliquota IVA. Un numero già usato per lo stesso fornitore nell’anno viene rifiutato.',
          ],
        },
        {
          type: 'paragraph',
          text: 'Se la creazione della fattura fallisce l’estratto resta **Documento ricevuto**: nulla viene salvato a metà.',
        },
      ],
    },
    {
      id: 'after-approval',
      title: 'Dopo l’approvazione',
      blocks: [
        {
          type: 'list',
          items: [
            'La scheda dell’estratto mostra la sezione **Fattura passiva** con il link alla fattura, **Netto da pagare**, **Pagato**, **Residuo** e lo **Stato pagamento**.',
            'Lo **stato pagamento** è quello delle scadenze della fattura (le rate si pagano da **Contabilità › Passiva › Scadenze**): non è memorizzato sull’estratto. Nell’elenco puoi filtrare per **Stato pagamento**.',
            'Un estratto **Approvato** è **immutabile**: non si ricalcola, non si annulla e il documento non si rimuove.',
            'La fattura passiva collegata è bloccata: non si modifica né si elimina e il numero non cambia; restano consentiti note, documenti allegati, pagamenti e la modifica di scadenza e metodo delle rate non pagate.',
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
          text: 'Modulo in fase di sviluppo. Stampa e invio dell’estratto arriveranno nelle prossime versioni.',
        },
      ],
    },
  ],
}

export default guide
