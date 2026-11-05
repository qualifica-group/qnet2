import { useMutation } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { exportChartOfAccounts } from '@/features/chart-of-accounts/api'
import type { ChartExportFormat } from '@/features/chart-of-accounts/types'
import { saveBlob } from '@/lib/download'

/** Downloads the whole plan as XLSX or CSV. */
export function useChartExport() {
  const { t } = useTranslation()

  return useMutation({
    mutationFn: async (format: ChartExportFormat) => {
      const file = await exportChartOfAccounts(format)
      saveBlob(file.blob, file.filename ?? `chart-of-accounts.${format}`)
    },
    onError: () => toast.error(t('chartOfAccounts.export.error')),
  })
}
