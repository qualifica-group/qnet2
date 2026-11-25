import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'formalab',
  title: 'Formalab',
  summary:
    'Formalab collega QNet al gestionale dei corsi: il catalogo delle classi viene sincronizzato in automatico e le commesse delle categorie collegate a Formalab ricevono i dati dell\'edizione nei campi scelti nella mappatura della categoria.',
  sections: [
    {
      id: 'overview',
      title: 'Cos\'è e chi la vede',
      blocks: [
        {
          type: 'paragraph',
          text: 'Le pagine si trovano nel menu **Integrazioni esterne › Formalab** (**Stato e configurazione** e **Classi**) e sono riservate al ruolo **super-admin**. Le voci di menu, le pagine, la sezione sulla categoria e la scheda sulla commessa compaiono **solo se l\'integrazione è attiva** nella tua installazione.',
        },
      ],
    },
    {
      id: 'status-card',
      title: 'Le card di stato',
      blocks: [
        {
          type: 'list',
          items: [
            '**Configurazione**: indirizzo del servizio, se le credenziali e la ricezione delle notifiche sono configurate, ogni quanti minuti avviene la sincronizzazione, classi nel catalogo e commesse collegate.',
            '**Ultima sincronizzazione**: esito (in corso, riuscita, fallita), origine, orari di avvio e fine, classi e commesse aggiornate ed eventuale messaggio di errore.',
            '**Chiamate nelle ultime 24 ore**: totale, fallite, in entrata e in uscita.',
            '**Commesse non collegate**: le commesse delle categorie collegate che la sincronizzazione non ha potuto collegare, divise per motivo: classe scelta non presente nel catalogo (l\'edizione scelta non è ancora stata letta da Formalab), senza classe Formalab scelta. **Si collegano al prossimo giro** conta quelle che hanno una classe scelta presente nel catalogo e verranno collegate dalla prossima sincronizzazione. Per collegarne una, scegli la classe nel campo **Nome corso / edizione** della commessa e salva: il collegamento è immediato.',
            '**Campi mappati non trovati**: compare solo se un campo della commessa usato nella mappatura di una categoria non esiste più tra gli attributi, perché il suo codice è stato rinominato o eliminato in Configurazione › Attributi. Elenca i codici mancanti: quei campi non vengono aggiornati finché il codice non viene ripristinato o la mappatura corretta.',
          ],
        },
        {
          type: 'note',
          text: 'Le credenziali non sono mai mostrate: vedi solo se sono configurate (Sì/No).',
        },
      ],
    },
    {
      id: 'actions',
      title: 'Le azioni',
      blocks: [
        {
          type: 'table',
          headers: ['Pulsante', 'Cosa fa'],
          rows: [
            ['**Sincronizza ora**', 'Avvia subito la sincronizzazione: rilegge il catalogo classi, allinea le commesse all\'edizione scelta nella loro select (abbinamento solo per ID edizione) e aggiorna i campi delle commesse collegate. Si esegue **una sincronizzazione alla volta**: finché quella avviata (anche quella automatica) non è finita il pulsante resta disattivato e mostra **Sincronizzazione in corso**, e la pagina si aggiorna da sola quando termina.'],
            ['**Verifica connessione**', 'Prova a contattare il servizio e mostra il tempo di risposta oppure il motivo dell\'errore.'],
          ],
        },
      ],
    },
    {
      id: 'call-log',
      title: 'Registro chiamate',
      blocks: [
        {
          type: 'paragraph',
          text: 'Sotto le card trovi la tabella di **tutte le chiamate** verso Formalab e da Formalab: data, direzione, metodo, indirizzo, codice di risposta, esito, durata, origine ed eventuale errore. Puoi filtrare per direzione, esito e data. I dati più vecchi di 90 giorni vengono eliminati automaticamente.',
        },
      ],
    },
    {
      id: 'classes-page',
      title: 'La pagina Classi',
      blocks: [
        {
          type: 'paragraph',
          text: 'In **Integrazioni esterne › Formalab › Classi** trovi il **catalogo delle classi** letto da Formalab, in sola lettura: ID edizione, codice, titolo, stato classe e corso, date, ore, sede, discenti e ultima lettura. È lo stesso elenco da cui la commessa sceglie la classe. Il pulsante **Sincronizza ora** avvia subito la lettura del catalogo (la sincronizzazione parte in coda e dura alcuni minuti: mentre è in corso il pulsante è disattivato; ricarica la tabella quando è finita).',
        },
      ],
    },
    {
      id: 'category-settings',
      title: 'Collegare una categoria a Formalab',
      blocks: [
        {
          type: 'paragraph',
          text: 'Quali commesse si collegano a Formalab lo decide la **categoria di prodotto**. Nel dettaglio della categoria (Configurazione › Categorie prodotto) trovi la sezione **Formalab** con due impostazioni.',
        },
        {
          type: 'list',
          items: [
            '**Collegata a Formalab**: si decide sulla **categoria radice** e vale per tutte le sue sottocategorie, che la mostrano come "Ereditato da …" e non possono cambiarla.',
            '**Mappatura dei campi**: per ogni campo di Formalab (classe, stato, date, ore, decreti, sede, discenti…) scegli il campo della commessa in cui scriverlo. L\'elenco propone solo i campi commessa della categoria di tipo compatibile; i campi lasciati "Non collegato" non vengono scritti.',
            'La **classe Formalab (edizione)** è obbligatoria e si mappa solo su un campo di tipo **relazione verso le classi Formalab**: è il campo da cui la commessa trova la sua edizione.',
            'Una sottocategoria usa la mappatura della categoria superiore ("Mappatura ereditata da …"); attivando **Mappatura propria** può definirne una diversa, e spegnendola torna a ereditare.',
            'Sulle commesse delle categorie collegate i campi mappati sono **in sola lettura**: li aggiorna solo Formalab e accanto al nome del campo compare l\'icona di sincronizzazione (passandoci sopra il mouse si legge che il valore arriva da Formalab). Fa eccezione il campo del **Nome corso / edizione**, che resta modificabile perché è la select con cui scegli la classe.',
          ],
        },
        {
          type: 'steps',
          items: [
            'Apri la categoria radice e, nella sezione **Formalab**, fai clic su **Modifica**.',
            'Attiva **Collegata a Formalab**.',
            'Controlla la mappatura: se la categoria ha i campi del corso GOL, sono già proposti (il Nome corso / edizione deve essere di tipo relazione verso le classi Formalab). Scegli o correggi il campo per ogni dato che vuoi ricevere.',
            'Fai clic su **Salva**.',
          ],
        },
        {
          type: 'note',
          text: 'Modificare la sezione richiede il permesso di modificare la categoria. Se una categoria viene scollegata, le sue commesse già sincronizzate smettono di aggiornarsi e la loro scheda mostra il motivo.',
        },
      ],
    },
    {
      id: 'work-order-tab',
      title: 'La scheda Formalab sulla commessa',
      blocks: [
        {
          type: 'paragraph',
          text: 'Sulle commesse di una categoria collegata a Formalab, o che sono già sincronizzate, compare la scheda **Formalab**. La classe si sceglie nel campo **Nome corso / edizione** della commessa (per le commesse GOL il **Nome Corso / Ed.**): una select sulle classi Formalab, che cerca per codice, titolo o ID edizione. L\'abbinamento avviene solo per **ID edizione**. Una volta scelta la classe, la scheda mostra i dati della classe: stato, date, ore, sede, discenti e ultimo aggiornamento.',
        },
        {
          type: 'steps',
          items: [
            'Scegli la classe nel campo **Nome corso / edizione** della commessa e salva: il collegamento è immediato e i campi mappati si compilano dal catalogo.',
            'Apri la scheda **Formalab** per vedere i dati della classe.',
            'Fai clic su **Sincronizza** per rileggere subito da Formalab l\'edizione scelta e riscrivere i campi mappati. Da quel momento la commessa si aggiorna anche da sola ogni ora.',
            'Se cambi la classe, salva: il collegamento passa alla nuova edizione. Se svuoti la select, il collegamento viene rimosso.',
          ],
        },
        {
          type: 'list',
          items: [
            '**Commessa non collegabile**: la sua categoria non è collegata a Formalab, oppure la mappatura non indica il campo Nome corso / edizione.',
            '**Nessuna classe scelta**: scegli prima la classe nel campo **Nome corso / edizione** (premendo **Sincronizza** a select vuota compare "Scegli la classe Formalab").',
            '**Classe non presente nel catalogo**: l\'edizione è nuova e il catalogo non l\'ha ancora ricevuta (si aggiorna ogni ora, oppure usa **Sincronizza ora** nella pagina Classi).',
          ],
        },
        {
          type: 'note',
          text: 'Sincronizzare richiede il permesso di modificare la commessa.',
        },
      ],
    },
    {
      id: 'learner-statement',
      title: 'Prospetto contabile discenti',
      blocks: [
        {
          type: 'paragraph',
          text: 'Sulle commesse di una categoria collegata a Formalab, con la classe scelta, la scheda **Amministrazione** > **Costi** mostra il pulsante **Prospetto contabile discenti** (serve il permesso di vedere i costi della commessa). Il prospetto legge i discenti della classe da Formalab, con le loro ore di presenza e l\'esito, e li incrocia con il preventivo della commessa, come nel QNet precedente.',
        },
        {
          type: 'list',
          items: [
            '**Costo del discente**: ore di presenza intere × 1 €/h; **ricavo**: ore arrotondate al quarto d\'ora per difetto × 0,85 €/h. Le ore non superano il totale ore del corso.',
            '**Riepilogo Costi e Ricavi**: costo discenti, altri costi del preventivo (escluse le provvigioni partner), ricavi formazione, utile, **MOL Qualifica** e **utile a discente** (diviso per i discenti che hanno superato il corso).',
            '**Scenari**: con solo l\'anticipo incassato compaiono acconto reale, saldo previsto e totale economico previsionale; con solo il saldo il pagamento unico; con anticipo e saldo le due tranche e il totale finanziario reale. Anticipo e saldo vengono dai campi **€ Anticipo Incassato** e **€ Saldo Incassato** della commessa.',
            '**Provvigioni partner**: il partner del discente è il **Partner commerciale** della sua offerta (se il discente ha più offerte, la più recente che ne ha uno); per cambiarlo si modifica l\'offerta. Per ogni discente con partner si indica una **percentuale** (applicata all\'utile a discente) oppure un **importo fisso**; le modifiche si salvano uscendo dal campo. Lo scenario **Economico** usa l\'utile previsto, il **Finanziario** quello incassato ed è disponibile solo con il saldo registrato.',
            '**Fornitore del partner**: nel riepilogo per partner si sceglie il fornitore con cui il partner viene pagato; vale per tutti i suoi discenti della classe. Accanto compare l\'ordine fornitore del partner sulla commessa, se esiste.',
            '**Avanzamento Commessa**: le percentuali di avanzamento per mese, con la quota del ricavo discenti.',
          ],
        },
        {
          type: 'paragraph',
          text: '**Esporta Excel** scarica il prospetto completo; **Esporta per Partner** un foglio per ogni partner con i soli suoi discenti, senza il MOL Qualifica. **Aggiorna da Formalab** rilegge subito la classe.',
        },
        {
          type: 'note',
          text: 'Modificare percentuali, importi e fornitori richiede il permesso di gestire i costi della commessa. In fase di sviluppo: generazione delle notule e degli ordini ai partner, importazione dei discenti, notule discenti.',
        },
      ],
    },
    {
      id: 'automatic-sync',
      title: 'Sincronizzazione automatica',
      blocks: [
        {
          type: 'paragraph',
          text: 'Ogni **ora** QNet rilegge il catalogo delle classi e collega le commesse delle categorie collegate che hanno scelto un\'edizione ora presente nel catalogo (sempre per ID edizione). Se una classe collegata a una commessa è cambiata, i campi mappati della commessa vengono aggiornati; se non è cambiato nulla, la commessa non viene toccata. Quando scegli, cambi o svuoti la classe di una commessa, il collegamento si aggiorna subito al salvataggio.',
        },
        {
          type: 'paragraph',
          text: 'Se all\'ora prevista è ancora in corso una sincronizzazione, quel giro viene saltato: due sincronizzazioni non partono mai insieme. Una sincronizzazione interrotta prima di finire (per esempio per un riavvio del server) compare in **Ultima sincronizzazione** come **Fallita** con il messaggio "Sincronizzazione interrotta"; la successiva riparte da capo.',
        },
        {
          type: 'paragraph',
          text: 'Se una classe **non esiste più su Formalab** (non compare più nel catalogo, oppure Formalab risponde che non esiste quando la rilegge con **Sincronizza** o con una notifica), QNet la toglie dal catalogo, **rimuove il collegamento** delle sue commesse e **svuota la classe scelta** nella loro select; gli altri campi della commessa restano con gli ultimi valori. Il numero di classi rimosse compare in **Ultima sincronizzazione**. Se Formalab restituisce un catalogo vuoto o incompleto non viene rimosso nulla.',
        },
      ],
    },
    {
      id: 'errors',
      title: 'Cosa succede in caso di errore',
      blocks: [
        {
          type: 'list',
          items: [
            'Se Formalab non è raggiungibile la sincronizzazione risulta **fallita**, l\'errore è nel registro chiamate e il catalogo resta quello dell\'ultima sincronizzazione riuscita; al giro successivo si riprova.',
            'Se l\'aggiornamento di una singola commessa fallisce, sulla sua scheda compare l\'avviso con il motivo e le altre commesse vengono aggiornate normalmente.',
            'Se premi **Sincronizza** mentre Formalab non è raggiungibile, i campi della commessa **non vengono toccati** e vedi il messaggio di errore.',
            'Se il codice di un campo usato in una mappatura viene rinominato o eliminato, quel campo smette di aggiornarsi: la card Formalab di **Stato del sistema** passa a Degradato con il controllo **Campi mappati**, e la cronologia delle sincronizzazioni lo annota.',
            'Lo stato dell\'integrazione compare anche nella pagina **Stato del sistema**.',
          ],
        },
      ],
    },
  ],
}

export default guide
