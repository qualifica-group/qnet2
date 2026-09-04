import { useTranslation } from 'react-i18next'

/** The five strings every `RelationSelectField`/`RelationMultiSelectField` of this module takes. */
export interface TaskSelectLabels {
  placeholder: string
  emptyLabel: string
  errorLabel: string
  clearLabel: string
  retryLabel: string
}

/**
 * The picker strings shared by every relation field of the Task form.
 * Resolved through a hook rather than a module constant because they are
 * translated: a language change must rebuild them.
 */
export function useTaskSelectLabels(): TaskSelectLabels {
  const { t } = useTranslation()

  return {
    placeholder: t('tasks.form.selectPlaceholder'),
    emptyLabel: t('tasks.form.selectEmpty'),
    errorLabel: t('tasks.form.selectError'),
    clearLabel: t('common.clear'),
    retryLabel: t('common.retry'),
  }
}
