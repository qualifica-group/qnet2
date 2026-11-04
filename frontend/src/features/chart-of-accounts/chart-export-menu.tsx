import { Download } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu'
import type { ChartExportFormat } from '@/features/chart-of-accounts/types'
import { useChartExport } from '@/features/chart-of-accounts/use-chart-export'

const FORMATS: { format: ChartExportFormat; labelKey: string }[] = [
  { format: 'xlsx', labelKey: 'chartOfAccounts.export.excel' },
  { format: 'csv', labelKey: 'chartOfAccounts.export.csv' },
]

/** "Export" button with the Excel / CSV choice. */
export function ChartExportMenu() {
  const { t } = useTranslation()
  const exportPlan = useChartExport()

  return (
    <DropdownMenu>
      <DropdownMenuTrigger asChild>
        <Button variant="outline" size="sm" className="bg-card" disabled={exportPlan.isPending}>
          <Download aria-hidden="true" />
          {t('chartOfAccounts.export.label')}
        </Button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align="end">
        {FORMATS.map(({ format, labelKey }) => (
          <DropdownMenuItem key={format} onSelect={() => exportPlan.mutate(format)}>
            {t(labelKey)}
          </DropdownMenuItem>
        ))}
      </DropdownMenuContent>
    </DropdownMenu>
  )
}
