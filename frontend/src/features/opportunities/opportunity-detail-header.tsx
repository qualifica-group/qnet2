import { useTranslation } from 'react-i18next'
import { Handshake, Pencil } from 'lucide-react'
import { DetailEmpty, DetailMonogram } from '@/components/detail/detail-panel'
import { RecordCardHeader, RecordStat, RecordStatStrip } from '@/components/detail/record-panel'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Progress } from '@/components/ui/progress'
import { cn } from '@/lib/utils'
import { badgeColorClass } from '@/features/table/cell-renderers'
import { formatDecimal } from '@/features/products/column-renderers'
import { swatchClassFor } from '@/features/custom-fields/badge-color-tokens'
import { StatusDescriptionHint } from '@/features/opportunity-workflows/status-description-hint'
import { probabilityToneClass } from '@/features/opportunities/column-renderers'
import type { OpportunityDetailWithPermissions as OpportunityDetailData } from '@/features/opportunities/types'

/**
 * Identity band and KPI strip of the opportunity record card. Kept in one
 * file: both pieces read the same handful of top-level fields (status,
 * working status, region, planning) and are always mounted together.
 */

/** Formats a `Y-m-d` planning date, blank when missing/invalid — mirrors the column renderer. */
function formatPlanningDate(value: string | null): string | null {
  if (!value) {
    return null
  }
  const date = new Date(value)
  return Number.isNaN(date.getTime()) ? null : date.toLocaleDateString()
}

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
          <Badge
            variant="secondary"
            className={cn('h-5 min-h-5', badgeColorClass(opportunity.opportunity_status.color))}
          >
            {opportunity.opportunity_status.name}
          </Badge>
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
  const startDate = formatPlanningDate(opportunity.start_date)
  const expectedCloseDate = formatPlanningDate(opportunity.expected_close_date)
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
      <RecordStat label={t('opportunities.form.startDate')} value={startDate ?? <DetailEmpty />} />
      <RecordStat
        label={t('opportunities.form.expectedCloseDate')}
        value={expectedCloseDate ?? <DetailEmpty />}
      />
    </RecordStatStrip>
  )
}
