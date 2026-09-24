import axios from 'axios'
import type { Path, UseFormSetError } from 'react-hook-form'
import type { TaskFormValues } from '@/features/tasks/task-schema'

/** Matches a `subtasks.N.field` 422 key (spec 0155 D-3), field limited to what the compact block collects. */
const SUBTASK_ERROR_PATTERN = /^subtasks\.(\d+)\.(title|end_date|assignee_ids)$/

/** The "no rows yet" resting shape of one `subtasks[]` entry (spec 0155 D-3). */
export function emptySubtaskRow(): TaskFormValues['subtasks'][number] {
  return { title: '', end_date: null, assignee_ids: [] }
}

/**
 * Maps `subtasks.N.field` 422 keys (spec 0155 D-3) onto the matching row's
 * own RHF field, so an invalid child surfaces inline instead of a generic
 * toast. Returns true when at least one such key was found — mirrors
 * `applyServerValidationErrors`, but that helper takes a fixed field list and
 * cannot address a dynamic row index.
 */
export function applySubtaskServerErrors(error: unknown, setError: UseFormSetError<TaskFormValues>): boolean {
  if (!axios.isAxiosError(error) || error.response?.status !== 422) {
    return false
  }
  const errors = error.response.data?.errors as Record<string, string[]> | undefined
  if (!errors) {
    return false
  }
  let handled = false
  for (const [key, messages] of Object.entries(errors)) {
    const match = SUBTASK_ERROR_PATTERN.exec(key)
    const message = messages[0]
    if (!match || !message) {
      continue
    }
    const [, index, field] = match
    setError(`subtasks.${index}.${field}` as Path<TaskFormValues>, { message })
    handled = true
  }
  return handled
}
