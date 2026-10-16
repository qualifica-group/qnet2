import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { SegmentedControl, type SegmentedControlOption } from '@/components/ui/segmented-control'
import { Skeleton } from '@/components/ui/skeleton'
import { MoneyValue } from '@/features/management-control/money-value'
import {
  STRUCTURE_GROUP_BYS,
  UNATTRIBUTED_KEY,
  type IncomeStatementFilters,
  type StructureCostTypeRow,
  type StructureDimensionRow,
  type StructureGroupBy,
} from '@/features/management-control/types'
import { useStructureCosts } from '@/features/management-control/use-structure-costs'

const HEAD_CELL = 'px-2 py-1.5 text-left text-xs font-medium text-muted-foreground'
const NUMERIC_HEAD_CELL = 'px-2 py-1.5 text-right text-xs font-medium text-muted-foreground'
const CELL = 'px-2 py-1.5 text-sm'
const NUMERIC_CELL = 'px-2 py-1.5 text-right text-sm'
const FIGURES = ['committed_net', 'invoiced_net', 'total_net'] as const

function CostTypeCell({ row }: { row: StructureCostTypeRow }) {
  const { t } = useTranslation()
  const { cost_type: costType } = row

  if (costType === null) {
    return <span className="text-muted-foreground">{t('managementControl.structure.noCostType')}</span>
  }
  return (
    <span className="flex items-center gap-1.5">
      {costType.color ? (
        <span aria-hidden="true" className="size-2.5 shrink-0 rounded-full border" style={{ backgroundColor: costType.color }} />
      ) : null}
      <span className="truncate">{costType.name}</span>
    </span>
  )
}

function CostTypeTable({ rows }: { rows: StructureCostTypeRow[] }) {
  const { t } = useTranslation()

  return (
    <div
      tabIndex={0}
      role="region"
      aria-label={t('managementControl.structure.byCostType')}
      className="min-w-0 overflow-auto rounded-lg border bg-card focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
    >
      <table className="w-full min-w-max border-collapse">
        <caption className="sr-only">{t('managementControl.structure.byCostType')}</caption>
        <thead className="bg-muted/40">
          <tr>
            <th scope="col" className={HEAD_CELL}>{t('managementControl.structure.costType')}</th>
            <th scope="col" className={HEAD_CELL}>{t('managementControl.structure.nature')}</th>
            {FIGURES.map((figure) => (
              <th key={figure} scope="col" className={NUMERIC_HEAD_CELL}>{t(`managementControl.structure.${figure}`)}</th>
            ))}
          </tr>
        </thead>
        <tbody>
          {rows.map((row) => (
            <tr key={row.cost_type?.id ?? 'none'} className="border-t hover:bg-muted/40">
              <th scope="row" className={`${CELL} text-left font-medium`}>
                <CostTypeCell row={row} />
              </th>
              <td className={CELL}>
                {row.cost_type?.nature ? t(`managementControl.structure.natures.${row.cost_type.nature}`) : ''}
              </td>
              {FIGURES.map((figure) => (
                <td key={figure} className={NUMERIC_CELL}>
                  <MoneyValue value={row[figure]} />
                </td>
              ))}
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  )
}

function DimensionTable({ rows, dimension }: { rows: StructureDimensionRow[]; dimension: string }) {
  const { t } = useTranslation()

  return (
    <div
      tabIndex={0}
      role="region"
      aria-label={t('managementControl.structure.byDimension', { dimension })}
      className="min-w-0 overflow-auto rounded-lg border bg-card focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
    >
      <table className="w-full min-w-max border-collapse">
        <caption className="sr-only">{t('managementControl.structure.byDimension', { dimension })}</caption>
        <thead className="bg-muted/40">
          <tr>
            <th scope="col" className={HEAD_CELL}>{dimension}</th>
            {FIGURES.map((figure) => (
              <th key={figure} scope="col" className={NUMERIC_HEAD_CELL}>{t(`managementControl.structure.${figure}`)}</th>
            ))}
          </tr>
        </thead>
        <tbody>
          {rows.map((row) => (
            <tr key={row.key} className="border-t hover:bg-muted/40">
              <th scope="row" className={`${CELL} text-left font-medium`}>
                {row.key === UNATTRIBUTED_KEY ? t('managementControl.unattributed') : (row.label ?? row.key)}
              </th>
              {FIGURES.map((figure) => (
                <td key={figure} className={NUMERIC_CELL}>
                  <MoneyValue value={row[figure]} />
                </td>
              ))}
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  )
}

/**
 * Costs of the purchase cycle tied to no work order (spec 0268 D-1, D-15), by cost
 * type and by company/site. Shares period, company and sites with the page filters.
 */
export function StructureCostsPanel({ filters }: { filters: IncomeStatementFilters }) {
  const { t } = useTranslation()
  const [groupBy, setGroupBy] = useState<StructureGroupBy>('company')
  const { data, isPending, isError, refetch } = useStructureCosts(filters, groupBy)
  const dimension = t(`managementControl.structure.groupBys.${groupBy}`)
  const hasData = data !== undefined && (data.by_cost_type.length > 0 || data.by_dimension.length > 0)

  const groupOptions: SegmentedControlOption<StructureGroupBy>[] = STRUCTURE_GROUP_BYS.map((value) => ({
    value,
    label: t(`managementControl.structure.groupBys.${value}`),
  }))

  return (
    <section aria-label={t('managementControl.structure.title')} className="flex flex-col gap-2">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <h3 className="text-base font-semibold">{t('managementControl.structure.title')}</h3>
        <SegmentedControl
          aria-label={t('managementControl.structure.groupBy')}
          className="w-full sm:w-auto"
          value={groupBy}
          options={groupOptions}
          onValueChange={setGroupBy}
        />
      </div>
      <p className="text-xs text-muted-foreground">{t('managementControl.structure.hint')}</p>
      {isPending ? (
        <div role="status" aria-label={t('managementControl.structure.loading')} className="flex flex-col gap-2">
          <Skeleton className="h-16 w-full" />
        </div>
      ) : null}
      {isError ? (
        <div role="alert" className="flex items-center gap-3 text-sm text-destructive">
          <span>{t('managementControl.structure.error')}</span>
          <Button type="button" size="sm" variant="secondary" onClick={() => void refetch()}>
            {t('managementControl.retry')}
          </Button>
        </div>
      ) : null}
      {data && !hasData ? (
        <p className="rounded-lg border bg-surface p-3 text-sm text-muted-foreground">{t('managementControl.structure.empty')}</p>
      ) : null}
      {data && hasData ? (
        <>
          <dl className="grid grid-cols-1 gap-2 sm:grid-cols-3">
            {FIGURES.map((figure) => (
              <div key={figure} className="flex min-w-0 flex-col gap-0.5 rounded-lg border bg-card p-3">
                <dt className="truncate text-xs text-muted-foreground">{t(`managementControl.structure.${figure}`)}</dt>
                <dd className="text-sm font-medium">
                  <MoneyValue value={data.totals[figure]} />
                </dd>
              </div>
            ))}
          </dl>
          <div className="grid grid-cols-1 gap-3 xl:grid-cols-2">
            <CostTypeTable rows={data.by_cost_type} />
            <DimensionTable rows={data.by_dimension} dimension={dimension} />
          </div>
        </>
      ) : null}
    </section>
  )
}
