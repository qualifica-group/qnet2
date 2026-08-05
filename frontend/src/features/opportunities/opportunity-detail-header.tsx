import { useTranslation } from 'react-i18next'
import { Handshake, Pencil } from 'lucide-react'
import { DetailEmpty, DetailMonogram } from '@/components/detail/detail-panel'
import { RecordCardHeader, RecordStat, RecordStatStrip } from '@/components/detail/record-panel'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Progress } from '@/components/ui/progress'
import { cn } from '@/lib/utils'
import { formatDecimal } from '@/features/products/column-renderers'
import { swatchClassFor } from '@/features/custom-fields/badge-color-tokens'
import { StatusDescriptionHint } from '@/features/opportunity-workflows/status-description-hint'
import { probabilityToneClass } from '@/features/opportunities/column-renderers'
import { OpportunityStatusBadge } from '@/features/opportunities/opportunity-status-badge'
import type { OpportunityDetailWithPermissions as OpportunityDetailData } from '@/features/opportunities/types'
import { formatDate } from '@/lib/formatting/date-display'

/**
 * Identity band and KPI strip of the opportunity record card. Kept in one
 * file: both pieces read the same handful of top-level fields (status,
 * working status, region, planning) and are always mounted together.
 */

interface OpportunityDetailHeaderProps {
  opportunity: OpportunityDetailData
  /** Opens the module's existing edit surface; absent = no edit affordance. */
  onEdit?: () => void
}

/** Identity band: monogram, name, registry subtitle, pipeline/working-status/region badges, edit action. */
export function OpportunityDetailHeader({ opportunity, onEdit }: OpportunityDetailHeaderProps) {
  const { t } = useTranslation()
  const canEdit = Boolean(onEdit) && opportunity.permissions.resource.update

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
      badges={
        <>
          <OpportunityStatusBadge summary={opportunity.status} />
          {opportunity.workflow_status ? (
            <span className="flex min-w-0 items-center gap-1">
              <Badge variant="secondary" className="h-5 min-h-5 gap-1.5">
                <span
                  className={cn(
                    'size-1.5 shrink-0 rounded-full',
                    swatchClassFor(opportunity.workflow_status.color) ?? 'bg-transparent',
                  )}
                  aria-hidden="true"
                />
                {opportunity.workflow_status.name}
              </Badge>
              <StatusDescriptionHint description={opportunity.workflow_status.description} />
            </span>
          ) : null}
          {opportunity.state ? (
            <Badge variant="outline" className="h-5 min-h-5">
              {opportunity.state.name}
            </Badge>
          ) : null}
        </>
      }
      actions={
        canEdit ? (
          <Button size="sm" onClick={onEdit}>
            <Pencil aria-hidden="true" />
            {t('common.edit')}
          </Button>
        ) : null
      }
    />
  )
}

interface OpportunityDetailStatsProps {
  opportunity: OpportunityDetailData
}

/** KPI strip: estimated value, success probability (with its tone bar), start date and expected close date. */
export function OpportunityDetailStats({ opportunity }: OpportunityDetailStatsProps) {
  const { t } = useTranslation()
  const estimatedValue = formatDecimal(opportunity.estimated_value)
  const startDate = formatDate(opportunity.start_date)
  const expectedCloseDate = formatDate(opportunity.expected_close_date)
  const probability = opportunity.success_probability

  return (
    <RecordStatStrip>
      <RecordStat label={t('opportunities.form.estimatedValue')} value={estimatedValue || <DetailEmpty />} />
      <RecordStat
        label={t('opportunities.form.successProbability')}
        value={
          probability !== null ? (
            <span className="flex items-center gap-2">
              <Progress
                value={probability}
                size="xs"
                aria-hidden="true"
                className="w-16 shrink-0"
                indicatorClassName={probabilityToneClass(probability)}
              />
              <span className="tabular-nums">{probability}%</span>
            </span>
          ) : (
            <DetailEmpty />
          )
        }
      />
      <RecordStat label={t('opportunities.form.startDate')} value={startDate || <DetailEmpty />} />
      <RecordStat
        label={t('opportunities.form.expectedCloseDate')}
        value={expectedCloseDate || <DetailEmpty />}
      />
    </RecordStatStrip>
  )
}
