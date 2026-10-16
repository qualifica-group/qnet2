import { useTranslation } from 'react-i18next'
import { IncomeStatementFunctionTable } from '@/features/management-control/income-statement-function-table'
import { MoneyValue } from '@/features/management-control/money-value'
import { rowLabel } from '@/features/management-control/row-label'
import type { SegmentBy, SegmentRow } from '@/features/management-control/types'

interface IncomeStatementSegmentsProps {
  segments: SegmentRow[]
  segmentBy: SegmentBy
}

/**
 * One card per segment value (company, site, type or sales representative) with its
 * subtotal and the same by-function statement. Nothing is rendered without segmentation.
 */
export function IncomeStatementSegments({ segments, segmentBy }: IncomeStatementSegmentsProps) {
  const { t } = useTranslation()

  if (segmentBy === 'none' || segments.length === 0) {
    return null
  }
  const dimension = t(`managementControl.filters.segmentBys.${segmentBy}`)

  return (
    <section aria-label={t('managementControl.segments.title', { dimension })} className="flex flex-col gap-3">
      <h3 className="text-base font-semibold">{t('managementControl.segments.title', { dimension })}</h3>
      {segments.map((segment) => {
        const title = rowLabel(t, segment, segmentBy === 'work_order_type')
        return (
          <article
            key={segment.key}
            aria-label={title}
            className="flex min-w-0 flex-col gap-2 rounded-lg border bg-surface p-3"
          >
            <header className="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
              <h4 className="text-sm font-semibold">{title}</h4>
              <p className="text-xs text-muted-foreground">
                <span>{t('managementControl.segments.summary', { count: segment.work_orders_count })}</span>
                {' · '}
                {t('managementControl.figures.margin_actual')}: <MoneyValue value={segment.margin_actual} className="font-medium" />
              </p>
            </header>
            <IncomeStatementFunctionTable
              rows={segment.by_business_function}
              total={segment}
              caption={t('managementControl.segments.caption', { segment: title })}
            />
          </article>
        )
      })}
    </section>
  )
}
