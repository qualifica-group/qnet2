import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'roles',
  title: 'Ruoli',
  summary: 'Un ruolo è un insieme di permessi: assegni i ruoli agli utenti e ogni utente riceve i permessi di tutti i suoi ruoli.',
  sections: [
    {
      id: 'overview',
      title: 'Panoramica',
      blocks: [
        { type: 'paragraph', text: 'Il modulo si trova in **Configurazione › Sistema › Ruoli**. L’elenco dei ruoli mostra per ognuno il numero di **Permessi** e di **Utenti**.' },
      ],
    },
    {
      id: 'create-role',
      title: 'Creare un ruolo',
      blocks: [
        { type: 'steps', items: ['Apri **Configurazione › Sistema › Ruoli**.', 'Fai clic su **Nuovo ruolo**: si apre la scheda del nuovo ruolo, con le stesse sezioni di un ruolo già salvato.', 'In **Dettagli ruolo** le righe **Nome** e **Membri** sono chiuse: premi la matita (o il valore), compila e premi **Fatto**; **Ripristina** riporta la riga com\'era.', 'La sezione **Permessi** è già aperta: scegli cosa può fare il ruolo. Se il modulo ha campi personalizzati, compaiono in una sezione a parte (**Altri campi**).', 'Fai clic su **Salva**. Se esci prima, QNet chiede di confermare: il ruolo non è ancora stato creato.'] },
        {
          type: 'table',
          headers: ['Campo', 'Cosa indicare'],
          rows: [
            ['**Nome**', 'Nome del ruolo, ad esempio il reparto o la mansione. Obbligatorio.'],
            ['**Membri**', 'Gli utenti con questo ruolo. Puoi gestirli anche dalla scheda di ogni utente.'],
            ['**Permessi**', 'Le azioni consentite, modulo per modulo.'],
          ],
        },
      ],
    },
    {
      id: 'editing-a-role',
      title: 'Modificare un ruolo',
      blocks: [
        { type: 'paragraph', text: 'La scheda di un ruolo non ha un modulo di modifica: ogni riga si modifica sul posto. Premi la matita su **Nome** o **Membri**, cambia il valore e premi **Salva**; **Annulla** o Esc lasciano la riga com\'era. Ogni salvataggio invia solo la riga che hai cambiato.' },
        { type: 'paragraph', text: '**Permessi** è un\'unica riga a tutta larghezza: la matita apre lo stesso esploratore della creazione (azioni e permessi sui campi). Premi **Salva** per salvare i permessi, **Annulla** per scartare le modifiche.' },
        { type: 'note', text: 'Se il ruolo non è modificabile (come **super-admin**) o non hai il permesso di modifica, le righe non hanno la matita e restano in sola lettura.' },
      ],
    },
    {
      id: 'permission-catalog',
      title: 'Come è organizzato il catalogo permessi',
      blocks: [
        { type: 'paragraph', text: 'La sezione **Permessi** ha due pannelli: a sinistra un albero che segue il menu del gestionale, con in fondo l’area **Trasversali** per funzioni presenti ovunque come **Note** e **Allegati**; a destra il dettaglio del modulo selezionato, con **Azioni** e **Campi**.' },
        {
          type: 'table',
          headers: ['Azione', 'Cosa consente'],
          rows: [
            ['**Visualizza elenco**', 'Aprire il modulo e vederne l’elenco.'],
            ['**Visualizza**', 'Aprire una singola scheda.'],
            ['**Crea**', 'Aggiungere nuove schede.'],
            ['**Modifica**', 'Cambiare le schede esistenti.'],
            ['**Elimina**', 'Cancellare le schede.'],
          ],
        },
        { type: 'paragraph', text: 'Le altre azioni stanno sotto **Configurazione avanzata**, nel riquadro **Azioni aggiuntive**: ad esempio **Esporta**, **Importa**, **Visualizza attività** e **Impersona**. Alcuni moduli hanno azioni proprie, come **Valida**, **Cambia stato** o **Visualizza tutti**.' },
        { type: 'paragraph', text: 'Nell\'area **Trasversali** trovi anche **Filtri salvati**, con il permesso **Pubblica filtri condivisi**: senza, un utente può salvare solo viste e filtri personalizzati privati, mai condivisi con i colleghi, su qualunque modulo con elenco.' },
        { type: 'paragraph', text: 'Sempre in **Trasversali** c’è **ID legacy**, con il permesso **Visualizza**: mostra l’id del vecchio gestionale, come colonna **ID legacy** negli elenchi e come badge **OLD ID #123** accanto al titolo delle schede. Senza il permesso l’id non compare da nessuna parte; il super-admin lo vede sempre.' },
        { type: 'list', items: ['**Cerca moduli o permessi…** filtra l’albero.', '**Seleziona area** attiva tutti i permessi di un’area.', '**Seleziona tutti** attiva tutti i permessi di un modulo.', '**Seleziona tutti i permessi** attiva l’intero catalogo.', 'Accanto a ogni area e modulo un contatore indica i permessi scelti sul totale.'] },
      ],
    },
    {
      id: 'field-permissions',
      title: 'Permessi sui singoli campi',
      blocks: [
        { type: 'paragraph', text: 'Nel dettaglio del modulo, la parte **Campi** regola ogni campo della scheda, diviso tra **Nativi** e **Personalizzati**.' },
        {
          type: 'table',
          headers: ['Casella', 'Significato'],
          rows: [
            ['**Visibile**', 'Il campo compare nella scheda e nell’elenco.'],
            ['**Modificabile**', 'Il campo si può cambiare. Se la spegni, resta in sola lettura.'],
            ['**Obbligatorio**', 'Il campo va compilato per poter salvare.'],
          ],
        },
        { type: 'note', text: 'Se non tocchi nulla, un campo resta visibile, modificabile e non obbligatorio. Alcuni campi sono indispensabili e non si possono limitare. Con più ruoli vale il permesso più ampio: basta un ruolo che rende visibile il campo.' },
      ],
    },
    {
      id: 'delete-role',
      title: 'Eliminare un ruolo',
      blocks: [
        { type: 'paragraph', text: 'Scegli **Elimina** sulla riga del ruolo e conferma. Gli utenti che lo avevano perdono i permessi che ne derivavano.' },
      ],
    },
    {
      id: 'super-admin-role',
      title: 'Il ruolo super-admin',
      blocks: [
        { type: 'paragraph', text: 'Il ruolo **super-admin** è un ruolo di sistema con accesso a tutto. Non si può modificare né eliminare.' },
        { type: 'list', items: ['Solo un super-admin vede il ruolo super-admin e può assegnarlo.', 'Non si può togliere il ruolo all’ultimo super-admin rimasto, né eliminarlo.', 'Solo un super-admin accede al modulo **Migrazioni**.'] },
      ],
    },
    {
      id: 'best-practices',
      title: 'Buone pratiche',
      blocks: [
        { type: 'list', items: ['**Dai solo i permessi necessari.** Ogni persona deve poter fare il proprio lavoro, niente di più.', '**Crea ruoli per mansione**, non per persona: "Commerciale" o "Back office", non "Mario Rossi".', '**Usa il super-admin con parsimonia.** Riservalo a una o due persone di fiducia.', '**Verifica con l’impersonificazione.** Dopo aver creato un ruolo, impersona un utente che lo ha e controlla cosa vede.', '**Limita i campi delicati.** Spegni **Visibile** per i ruoli che non devono vedere dati riservati.'] },
      ],
    },
    {
      id: 'visibility',
      title: "Livelli di visibilità dei record",
      blocks: [
        { type: 'paragraph', text: "Per **Opportunità**, **Offerte**, **Commesse** e **Contratti** la visibilità si sceglie con tre permessi, uno per modulo, che si sommano:" },
        { type: 'list', items: ["**Visualizza**: solo i record in cui la persona fa parte del team (ad esempio Supervisore o Gestore account).", "**Visualizza per sede**: in più i record della propria sede operativa (non previsto per le Opportunità).", "**Visualizza tutti**: tutti i record."] },
        { type: 'note', text: "I livelli sono per modulo: dare **Visualizza tutti** sulle Offerte non lo dà sui Contratti. Serve sempre anche **Visualizza**. Vedere un record non dà il diritto di modificarlo o eliminarlo." },
      ],
    },
  ],
}

export default guide
