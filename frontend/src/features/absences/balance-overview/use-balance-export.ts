import { useMutation } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { exportAbsenceBalanceOverview } from '@/features/absences/api'
import type { AbsenceBalanceOverviewFilters, BalanceExportFormat } from '@/features/absences/types'
import { saveBlob } from '@/lib/download'

/** Downloads the overview (current filters, no paging) as CSV or XLSX. */
export function useBalanceExport(filters: AbsenceBalanceOverviewFilters) {
  const { t } = useTranslation()

  return useMutation({
    mutationFn: async (format: BalanceExportFormat) => {
      const file = await exportAbsenceBalanceOverview(filters, format)
      saveBlob(file.blob, file.filename ?? `absence-balances-${filters.year}.${format}`)
    },
    onError: () => toast.error(t('absences.balances.overview.export.error')),
  })
}
