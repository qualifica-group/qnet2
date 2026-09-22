/**
 * Centralized TanStack Query keys for the work order Task board (spec 0146).
 * `board` is the single payload consumed by both viste (Lista/Kanban);
 * `stages` is the standalone list the task form's "Fase" select reads.
 */
export const taskBoardKeys = {
  board: (workOrderId: number) => ['work-orders', 'task-board', workOrderId] as const,
  stages: (workOrderId: number) => ['work-orders', 'stages', workOrderId] as const,
}
