import { useTranslation } from 'react-i18next'

/** Labels of the async multi-selects, shared by every filter built on them. */
export function useMultiSelectLabels() {
  const { t } = useTranslation()

  return (label: string) => ({
    placeholder: t('managementControl.filters.placeholder'),
    searchPlaceholder: t('managementControl.filters.search'),
    empty: t('managementControl.filters.empty'),
    error: t('managementControl.filters.error'),
    removeLabel: t('managementControl.filters.remove'),
    triggerLabel: label,
    retry: t('managementControl.filters.retry'),
  })
}
