import { useTranslation } from 'react-i18next'
import type { AsyncPaginatedSelectLabels } from '@/components/ui/async-paginated-select'

/** Strings of the person picker above the balances. */
export function useSelectLabels(): AsyncPaginatedSelectLabels {
  const { t } = useTranslation()

  return {
    placeholder: t('absences.balances.person.placeholder'),
    searchPlaceholder: t('absences.balances.person.search'),
    empty: t('absences.balances.person.empty'),
    error: t('absences.balances.person.error'),
    clearLabel: t('common.clear'),
    triggerLabel: t('absences.balances.person.label'),
    retry: t('common.retry'),
  }
}
