import { useTranslation } from 'react-i18next'
import { Handshake } from 'lucide-react'
import { DetailEmpty, DetailMonogram } from '@/components/detail/detail-panel'
import { RecordCardHeader, RecordStat, RecordStatStrip } from '@/components/detail/record-panel'
import { Progress } from '@/components/ui/progress'
import { formatDecimal } from '@/features/products/column-renderers'
import { probabilityToneClass } from '@/features/opportunities/column-renderers'
import { OpportunityStatusBadge } from '@/features/opportunities/opportunity-status-badge'
import type { OpportunityDetailWithPermissions as OpportunityDetailData } from '@/features/opportunities/types'
import { formatDate } from '@/lib/formatting/date-display'

/**
 * Identity band and KPI strip of the opportunity record card. Kept in one
 * file: both pieces read the same handful of top-level fields (status,
 * working status, planning) and are always mounted together.
 */

interface OpportunityDetailHeaderProps {
  opportunity: OpportunityDetailData
}

/**
 * Identity band: monogram, name, registry subtitle and the computed status
 * badge. No edit action: the fields edit in place (spec 0198).
 */
export function OpportunityDetailHeader({ opportunity }: OpportunityDetailHeaderProps) {
  return (
    <RecordCardHeader
      media={
        <DetailMonogram
          name={opportunity.name}
          icon={<Handshake />}
          className="size-10 text-base [&>svg]:size-5"
        />
      }
      title={opportunity.name}
      subtitle={opportunity.registry?.name}
      badges={<OpportunityStatusBadge summary={opportunity.status} />}
    />
  )
}

interface OpportunityStatsStripProps {
  estimatedValue: string | number | null
  successProbability: number | null
  startDate: string | null
  expectedCloseDate: string | null
}

/**
 * KPI strip: estimated value, success probability (with its tone bar), start
 * date and expected close date — shared by the detail (persisted values) and
 * the create form (the draft, live).
 */
export function OpportunityStatsStrip({
  estimatedValue,
  successProbability,
  startDate,
  expectedCloseDate,
}: OpportunityStatsStripProps) {
  const { t } = useTranslation()
  const formattedValue = formatDecimal(estimatedValue)
  const formattedStart = formatDate(startDate)
  const formattedClose = formatDate(expectedCloseDate)

  return (
    <RecordStatStrip>
      <RecordStat label={t('opportunities.form.estimatedValue')} value={formattedValue || <DetailEmpty />} />
      <RecordStat
        label={t('opportunities.form.successProbability')}
        value={
          successProbability !== null ? (
            <span className="flex items-center gap-2">
              <Progress
                value={successProbability}
                size="xs"
                aria-hidden="true"
                className="w-16 shrink-0"
                indicatorClassName={probabilityToneClass(successProbability)}
              />
              <span className="tabular-nums">{successProbability}%</span>
            </span>
          ) : (
            <DetailEmpty />
          )
        }
      />
      <RecordStat label={t('opportunities.form.startDate')} value={formattedStart || <DetailEmpty />} />
      <RecordStat label={t('opportunities.form.expectedCloseDate')} value={formattedClose || <DetailEmpty />} />
    </RecordStatStrip>
  )
}

/** The detail's KPI strip, on the persisted opportunity. */
export function OpportunityDetailStats({ opportunity }: { opportunity: OpportunityDetailData }) {
  return (
    <OpportunityStatsStrip
      estimatedValue={opportunity.estimated_value}
      successProbability={opportunity.success_probability}
      startDate={opportunity.start_date}
      expectedCloseDate={opportunity.expected_close_date}
    />
  )
}
