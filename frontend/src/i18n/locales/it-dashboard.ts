/**
 * Dashboard home (spec 0151): sezione "Attività da completare" (D-2..D-6),
 * Segnatempo, intestazioni dei blocchi statistiche di modulo, stato vuoto
 * (D-9). Mirrors `en-dashboard.ts`.
 */

export const dashboard = {
  tasksSection: {
    title: 'Attività da completare',
    estimatedTime: 'Tempo stimato',
    toValidate: 'Da validare',
    loadError: 'I contatori della dashboard non sono disponibili al momento.',
    cards: {
      not_completed: {
        label: 'Tutti',
        description: 'Carico di lavoro aperto ancora da completare.',
      },
      assigned_to_me: {
        label: 'Assegnati a me',
        description: 'Attività di cui sei attualmente assegnatario.',
      },
      assigned_by_me: {
        label: 'Assegnati da me',
        description: 'Attività che hai assegnato ad altri utenti.',
      },
      created_by_me: {
        label: 'Creati da me',
        description: 'Attività che hai creato per altre persone.',
      },
      observed_by_me: {
        label: 'Osservati da me',
        description: "Attività di cui segui l'andamento come osservatore.",
      },
    },
  },
  timeEntriesSection: {
    title: 'Segnatempo',
  },
  absencesSection: {
    title: 'Assenze',
    toApprove: 'Da approvare',
    toApproveLink: 'Vai alle richieste da approvare',
    toApproveEmpty: 'Nessuna richiesta da approvare.',
    absentToday: 'Assenti oggi',
    absentTodayEmpty: "Nessuno e' assente oggi.",
    until: 'fino al {{date}}',
    myUpcoming: 'Le mie prossime assenze',
    myUpcomingEmpty: 'Nessuna assenza in programma.',
    error: 'Impossibile caricare le assenze.',
  },
  moduleSections: {
    opportunities: 'Opportunità',
    quotes: 'Offerte',
    leads: 'Lead',
    registries: 'Anagrafiche',
  },
  empty: {
    title: 'Nessun contenuto disponibile',
    description: 'Non hai i permessi per visualizzare alcun blocco di questa pagina.',
  },
}
