import type { TaskSubtask } from '@/features/tasks/types'

/** A child is done once its status phase is the positive closure, as the grid's title cell reads it. */
export function isSubtaskDone(subtask: TaskSubtask): boolean {
  return subtask.task_status.group === 'closed_positive'
}

export interface SubtaskProgress {
  done: number
  total: number
  /** Rounded mean of the children's percentages, the same roll-up the parent's own completion uses. */
  average: number
}

/** The panel's header figures: how many children are done and their mean completion. */
export function subtaskProgress(subtasks: TaskSubtask[]): SubtaskProgress {
  const total = subtasks.length
  const sum = subtasks.reduce((accumulator, subtask) => accumulator + subtask.completion_percentage, 0)

  return {
    done: subtasks.filter(isSubtaskDone).length,
    total,
    average: total === 0 ? 0 : Math.round(sum / total),
  }
}
