import axios from 'axios'
import type { TFunction } from 'i18next'

/**
 * Maps the shared 409 (bloccato)/422 (fase sbagliata) split to its toast
 * copy (AC-044/AC-064). Its own module — not a re-export from
 * `task-actions-bar.tsx` — because that file only exports the `TaskActionsBar`
 * component: mixing in a plain function trips `react-refresh/only-export-components`.
 * `TaskActionsBar` and `TaskRequestUpdateDialog` both import it from here so
 * neither keeps a second copy that could drift from it.
 */
export function actionErrorMessage(t: TFunction, error: unknown): string {
  const status = axios.isAxiosError(error) ? error.response?.status : undefined
  if (status === 409) {
    return t('tasks.actions.errors.blocked')
  }
  if (status === 422) {
    return t('tasks.actions.errors.wrongPhase')
  }
  return t('tasks.actions.errors.generic')
}
