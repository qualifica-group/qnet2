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
        { type: 'paragraph', text: 'Il modulo si trova in **Amministrazione › Ruoli**. L’elenco dei ruoli mostra per ognuno il numero di **Permessi** e di **Utenti**.' },
      ],
    },
    {
      id: 'create-edit-role',
      title: 'Creare o modificare un ruolo',
      blocks: [
        { type: 'steps', items: ['Apri **Amministrazione › Ruoli**.', 'Fai clic su **Nuovo ruolo**, oppure scegli **Modifica** su una riga.', 'In **Dettagli ruolo** scrivi il **Nome**. Se vuoi, scegli subito gli utenti in **Membri**.', 'Nella sezione **Permessi** scegli cosa può fare il ruolo.', 'Fai clic su **Salva**.'] },
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
  ],
}

export default guide
