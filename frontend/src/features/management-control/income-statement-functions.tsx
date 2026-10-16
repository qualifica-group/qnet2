import { useTranslation } from 'react-i18next'
import { ComparisonBarChart } from '@/components/ui/comparison-bar-chart'
import { formatEuro } from '@/features/invoices/invoice-format'
import { IncomeStatementFunctionTable } from '@/features/management-control/income-statement-function-table'
import { rowLabel } from '@/features/management-control/row-label'
import type { FunctionRow, IncomeStatementTotals } from '@/features/management-control/types'

interface IncomeStatementFunctionsProps {
  rows: FunctionRow[]
  total: IncomeStatementTotals
}

/** Main statement, on every selected work order: by business function, plus expected vs actual margin chart. */
export function IncomeStatementFunctions({ rows, total }: IncomeStatementFunctionsProps) {
  const { t } = useTranslation()

  return (
    <section aria-label={t('managementControl.functions.title')} className="flex flex-col gap-2">
      <h3 className="text-base font-semibold">{t('managementControl.functions.title')}</h3>
      <IncomeStatementFunctionTable rows={rows} total={total} caption={t('managementControl.functions.caption')} />
      <ComparisonBarChart
        className="min-w-0"
        title={t('managementControl.functions.chartTitle')}
        points={rows.map((row) => ({
          label: rowLabel(t, row),
          budget: row.margin_budget,
          actual: row.margin_actual,
        }))}
        seriesLabels={{
          budget: t('managementControl.figures.margin_budget'),
          actual: t('managementControl.figures.margin_actual'),
        }}
        formatValue={formatEuro}
        emptyLabel={t('managementControl.functions.empty')}
      />
    </section>
  )
}
