/**
 * /tasks view mode + Kanban strings (spec 0157). Split out of `en-tasks.ts`
 * purely to stay under the engineering.md §6 size budget; merged back as
 * `tasks.views` in `en-tasks.ts`.
 */
export const tasksViews = {
  analytic: 'Analytic',
  synthetic: 'Tree',
  kanban: 'Kanban',
  kanbanByStatus: 'By status',
  kanbanByDue: 'By due date',
  loadError: 'Unable to load tasks. Please try again.',
  kanbanColumns: {
    completion: 'Completion',
    hours: 'Hours',
    addTask: 'New task',
  },
  dueBuckets: {
    overdue: 'Overdue',
    today: 'Today',
    tomorrow: 'Tomorrow',
    this_week: 'This week',
    this_month: 'This month',
    later: 'Later',
    completed: 'Completed',
  },
}
