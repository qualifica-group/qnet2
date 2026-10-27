import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'commission-configurations',
  title: 'Configuratore Commissioni',
  summary: 'Contiene le regole che calcolano in automatico le provvigioni sulle righe delle offerte: chi la riceve, su quali prodotti e quanto vale.',
  sections: [
    {
      id: 'overview',
      title: 'Panoramica',
      blocks: [
        { type: 'paragraph', text: 'Il modulo si trova in **Configurazione › Contabilità › Configuratore Commissioni**. Nell’ordine consigliato, le regole per categoria si preparano subito, quelle per prodotto dopo aver caricato i prodotti.' },
      ],
    },
    {
      id: 'create-configuration',
      title: 'Creare una configurazione',
      blocks: [
        { type: 'steps', items: ['Apri **Configurazione › Contabilità › Configuratore Commissioni** e premi **Nuova configurazione**.', 'In **Destinatario e ambito**: scrivi il **Nome configurazione**; scegli il **Ruolo destinatario** (Commerciale, Segnalatore, Supervisore o Fornitore); scegli l’**Ambito di applicazione** (Categoria prodotto, Prodotto o Destinatario specifico).', 'In base all’ambito indica **Categoria prodotto**, **Prodotto** oppure **Tipo destinatario** (Referente, Utente o Anagrafica) e **Destinatario**.', 'In **Calcolo** scegli la **Tipologia commissione** (Importo fisso o Percentuale), il **Valore commissione**, la **Priorità regola** e lo **Stato** (Attiva o Sospesa). Se il Ruolo è **Fornitore** scegli anche il **Verso** (obbligatorio): **Ricevuta** se la commissione la incassiamo noi ed è il ricavo della riga, **Pagata** se è un costo verso il fornitore.', 'In **Validità e note** indica la **Data inizio validità** (obbligatoria) e l’eventuale **Data fine validità**.', 'Se vuoi, aggiungi una **Nota di servizio interna** e premi **Salva**. Nella creazione la scheda è identica al dettaglio: ogni riga si apre con la matita e si conferma con **Fatto** (**Ripristina** per annullare la riga); uscendo prima di salvare ti viene chiesta conferma.'] },
        { type: 'tip', text: 'Le regole di una singola anagrafica (fornitore o no) si consultano e si creano anche dal suo dettaglio, nella scheda **Commissioni configurate**: lì il modulo parte già con Ruolo **Fornitore** e quell’anagrafica come Destinatario.' },
        { type: 'warning', text: 'Il **Verso** passa sulla riga d’offerta quando la commissione Fornitore viene creata: cambiarlo sulla regola vale per le nuove righe; per le offerte già salvate te lo chiede QNet subito dopo il salvataggio (vedi **Modificare, sospendere ed eliminare**). La commissione Fornitore si calcola solo se la **Tipologia prodotto** del prodotto ha attivo **Calcolo commissione Fornitore**.' },
      ],
    },
    {
      id: 'rule-selection',
      title: 'Come viene scelta la regola',
      blocks: [
        { type: 'list', items: ['Valgono solo le regole **Attiva** e valide alla data di riferimento.', 'Il destinatario non si sceglie sulla riga: Commerciale, Segnalatore e Supervisore sono quelli dell’offerta, il Fornitore è quello del prodotto.', 'Le regole per un destinatario preciso vincono su quelle valide per tutto il ruolo.', 'A parità, una regola su un Prodotto vince su una regola su una Categoria.', 'Una regola su una Categoria vale anche per tutte le sue sottocategorie, e si può mettere anche su una categoria madre (ad esempio ISO). Se più regole di categoria coprono il prodotto, vince quella sulla categoria più vicina al prodotto.', 'Se restano più regole sulla stessa categoria o sullo stesso prodotto, vince la **Priorità regola** più alta; a parità, la data di inizio più recente.'] },
      ],
    },
    {
      id: 'manage',
      title: 'Modificare, sospendere ed eliminare',
      blocks: [
        { type: 'paragraph', text: 'Dall’elenco puoi usare **Visualizza** ed **Elimina** sulla riga, se il tuo ruolo lo consente. Non c’è una pagina di modifica: apri la scheda con **Visualizza** e cambia un dato alla volta, con la matita accanto al valore (o cliccando il valore) e **Salva** per confermare, **Annulla** per tornare indietro. I dati che devono essere coerenti tra loro si modificano insieme in un’unica riga: **Destinatario** (ruolo, tipo destinatario e destinatario), **Ambito** (ambito di applicazione e categoria o prodotto) e **Validità** (data di inizio e di fine).' },
        { type: 'paragraph', text: "Quando salvi un dato della regola (tranne il nome) e la regola è usata da offerte non chiuse (Aperta, In attesa o Validata), QNet ti chiede cosa fare di quelle offerte. La regola è già salvata; devi scegliere una risposta, nessuna è preselezionata: **Solo le nuove righe d'offerta** (le offerte esistenti restano come sono), **Aggiorna solo le commissioni da regola** (le commissioni cambiate a mano restano) oppure **Rigenera tutte dalle regole** (anche le commissioni cambiate a mano vengono sostituite). Il riepilogo indica quante offerte, righe e commissioni manuali sono coinvolte. L'aggiornamento gira in background: al termine ricevi una notifica con quante offerte sono state aggiornate. Le offerte chiuse non cambiano mai. La domanda compare solo se puoi modificare anche le offerte." },
        { type: 'warning', text: 'Una configurazione in uso non si può eliminare: impostala su **Sospesa**.' },
      ],
    },
  ],
}

export default guide
