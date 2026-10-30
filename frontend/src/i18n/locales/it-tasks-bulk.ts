/**
 * Task list bulk actions (spec 0156 D-6, `POST /api/tasks/bulk`). Split out
 * of `it-tasks.ts` purely to stay under the engineering.md §6 size budget;
 * merged back as `tasks.bulk` in `it.ts`.
 */
export const tasksBulk = {
  assign: 'Assegna',
  complete: 'Completa',
  uncomplete: 'Riapri',
  uncompleteConfirmDescription_one: 'Il task selezionato torna in stato "In corso".',
  uncompleteConfirmDescription_other: 'I {{count}} task selezionati tornano in stato "In corso".',
  block: 'Blocca',
  blockConfirmDescription_one: 'Il task selezionato viene bloccato.',
  blockConfirmDescription_other: 'I {{count}} task selezionati vengono bloccati.',
  unblock: 'Sblocca',
  unblockConfirmDescription_one: 'Il task selezionato viene sbloccato.',
  unblockConfirmDescription_other: 'I {{count}} task selezionati vengono sbloccati.',
  priority: 'Priorità',
  startDate: 'Data inizio',
  endDate: 'Data fine',
  delete: 'Elimina',
  deleteConfirmDescription_one: 'Il task selezionato verrà eliminato. Azione non reversibile.',
  deleteConfirmDescription_other: 'I {{count}} task selezionati verranno eliminati. Azione non reversibile.',
  success_one: 'Task aggiornato.',
  success_other: '{{count}} task aggiornati.',
  genericError: "Si è verificato un errore nell'azione massiva. Riprova.",
  incompatibleError_one: 'Un task non è compatibile con questa azione: nessuna modifica applicata.',
  incompatibleError_other: '{{count}} task non sono compatibili con questa azione: nessuna modifica applicata.',
  incompatibleReason: 'Task #{{id}}: {{reason}}',
  assignDialog: {
    title_one: 'Assegna il task selezionato',
    title_other: 'Assegna {{count}} task',
    assigneesRequired: 'Seleziona almeno un assegnatario.',
    confirm: 'Assegna',
  },
  completeDialog: {
    description:
      "Il feedback verrà applicato a ciascuno dei {{count}} task; il tempo inserito è il totale e verrà ripartito tra i task.",
    title_one: 'Completa il task selezionato',
    title_other: 'Completa {{count}} task',
    confirm: 'Completa',
  },
  shares: {
    title: 'Ripartizione del tempo',
    hint: 'Il tempo inserito è il totale: modifica le quote dei singoli task se non vuoi le parti uguali.',
    equalSplit: 'Ripartisci in parti uguali',
    assigned: 'Assegnati {{assigned}} di {{total}} min',
    mismatch: 'La somma delle quote deve essere uguale al totale ({{total}} min).',
    tooFewMinutes: 'Ogni task richiede almeno 1 minuto: aumenta il totale o riduci i task.',
    slot: '{{start}} - {{end}}',
    minutesFor: 'Quota di {{title}} (minuti)',
  },
  dateDialog: {
    title: '{{field}} — {{count}} task',
    dateRequired: 'La data è obbligatoria.',
    confirm: 'Salva',
  },
  priorityDialog: {
    title_one: 'Priorità del task selezionato',
    title_other: 'Priorità di {{count}} task',
    priorityRequired: 'Scegli una priorità.',
    confirm: 'Salva',
  },
}
