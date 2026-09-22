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
        { type: 'paragraph', text: 'Il modulo si trova in **Configurazione › Configuratore Commissioni**. Nell’ordine consigliato, le regole per categoria si preparano subito, quelle per prodotto dopo aver caricato i prodotti.' },
      ],
    },
    {
      id: 'create-configuration',
      title: 'Creare una configurazione',
      blocks: [
        { type: 'steps', items: ['Apri **Configurazione › Configuratore Commissioni** e premi **Nuova configurazione**.', 'In **Identità e ambito**: scrivi il **Nome configurazione**; scegli il **Ruolo destinatario** (Commerciale, Segnalatore, Supervisore o Fornitore); scegli l’**Ambito di applicazione** (Categoria prodotto, Prodotto o Destinatario specifico).', 'In base all’ambito indica **Categoria prodotto**, **Prodotto** oppure **Tipo destinatario** (Referente, Utente o Anagrafica) e **Destinatario**.', 'In **Calcolo** scegli la **Tipologia commissione** (Importo fisso o Percentuale), il **Valore commissione** e la **Priorità regola**.', 'In **Validità** indica la **Data inizio validità** (obbligatoria), l’eventuale **Data fine validità** e lo **Stato** (Attiva o Sospesa).', 'Se vuoi, aggiungi una **Nota di servizio interna** e premi **Salva**.'] },
      ],
    },
    {
      id: 'rule-selection',
      title: 'Come viene scelta la regola',
      blocks: [
        { type: 'list', items: ['Valgono solo le regole **Attiva** e valide alla data di riferimento.', 'Il destinatario non si sceglie sulla riga: Commerciale, Segnalatore e Supervisore sono quelli dell’offerta, il Fornitore è quello del prodotto.', 'Le regole per un destinatario preciso vincono su quelle valide per tutto il ruolo.', 'A parità, una regola su un Prodotto vince su una regola su una Categoria.', 'Se restano più regole, vince la **Priorità regola** più alta; a parità, la data di inizio più recente.'] },
      ],
    },
    {
      id: 'manage',
      title: 'Modificare, sospendere ed eliminare',
      blocks: [
        { type: 'paragraph', text: 'Dall’elenco puoi usare **Visualizza**, **Modifica** ed **Elimina** sulla riga, se il tuo ruolo lo consente.' },
        { type: 'warning', text: 'Una configurazione in uso non si può eliminare: impostala su **Sospesa**.' },
      ],
    },
  ],
}

export default guide
