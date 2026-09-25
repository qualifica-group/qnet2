import axios from 'axios'
import type { Path, UseFormSetError } from 'react-hook-form'
import type { TaskFormValues } from '@/features/tasks/task-schema'
import type { SubtaskGrandchildValues, SubtaskGreatGrandchildValues } from '@/features/tasks/task-subtask-types'

/**
 * Matches a `subtasks.N.field` 422 key at ANY depth up to the 3 levels the
 * form can produce (spec 0161 D-1): `subtasks.0.title`,
 * `subtasks.0.subtasks.1.title`, `subtasks.0.subtasks.1.subtasks.2.title`.
 */
const SUBTASK_ERROR_PATTERN = /^subtasks(?:\.\d+\.subtasks)*\.\d+\.(title|end_date|assignee_ids)$/

/** The "no rows yet" resting shape of one root `subtasks[]` entry (figlio, level 1). */
export function emptySubtaskRow(): TaskFormValues['subtasks'][number] {
  return { title: '', end_date: null, assignee_ids: [], subtasks: [] }
}

/** The resting shape of a level-2 row (nipote), appended under a figlio's own `subtasks`. */
export function emptySubtaskGrandchildRow(): SubtaskGrandchildValues {
  return { title: '', end_date: null, assignee_ids: [], subtasks: [] }
}

/** The resting shape of a level-3 row (pronipote), the deepest level — no `subtasks` field of its own (D-1). */
export function emptySubtaskGreatGrandchildRow(): SubtaskGreatGrandchildValues {
  return { title: '', end_date: null, assignee_ids: [] }
}

/**
 * Maps `subtasks.N.field` 422 keys (spec 0155 D-3, any depth per spec 0161
 * D-1) onto the matching row's own RHF field, so an invalid child surfaces
 * inline instead of a generic toast. Returns true when at least one such key
 * was found — mirrors `applyServerValidationErrors`, but that helper takes a
 * fixed field list and cannot address a dynamic, arbitrarily nested row path.
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
    const message = messages[0]
    if (!SUBTASK_ERROR_PATTERN.test(key) || !message) {
      continue
    }
    setError(key as Path<TaskFormValues>, { message })
    handled = true
  }
  return handled
}
