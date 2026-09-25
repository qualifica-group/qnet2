/**
 * /tasks view mode + Kanban strings (spec 0157). Split out of `it-tasks.ts`
 * purely to stay under the engineering.md §6 size budget; merged back as
 * `tasks.views` in `it-tasks.ts`.
 */
export const tasksViews = {
  analytic: 'Analitica',
  synthetic: 'Sintetica',
  kanban: 'Kanban',
  kanbanByStatus: 'Per stato',
  kanbanByDue: 'Per scadenza',
  loadError: 'Impossibile caricare i task. Riprova.',
  kanbanColumns: {
    completion: 'Completamento',
    hours: 'Ore',
    addTask: 'Nuovo task',
  },
  dueBuckets: {
    overdue: 'Scaduti',
    today: 'Oggi',
    tomorrow: 'Domani',
    this_week: 'Questa settimana',
    this_month: 'Questo mese',
    later: 'Più avanti',
    completed: 'Completati',
  },
}
