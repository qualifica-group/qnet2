import { useTranslation } from 'react-i18next'
import { History, Workflow } from 'lucide-react'
import {
  DetailEmpty,
  DetailField,
  DetailGrid,
  DetailHero,
  DetailMeta,
  DetailMonogram,
  DetailPanel,
  DetailSection,
} from '@/components/detail/detail-panel'
import { Badge } from '@/components/ui/badge'
import { formatDateTime } from '@/features/table/cell-renderers'
import { ActivityLogSection } from '@/features/activity-log/activity-log-section'
import { swatchClassFor } from '@/features/custom-fields/badge-color-tokens'
import { cn } from '@/lib/utils'
import type { QuoteWorkflowDetailWithPermissions } from '@/features/quote-workflows/types'

interface QuoteWorkflowDetailViewProps {
  quoteWorkflow: QuoteWorkflowDetailWithPermissions
}

/**
 * Read-only detail of a single opportunity workflow (spec 0047 Lane C): its
 * criteria (field label + resolved value label) and its status set, in
 * `sort_order`. Purely presentational: the caller fetches the fresh detail
 * and passes it down. Composed from the shared detail kit for a consistent
 * CRM look, mirroring `OpportunityStatusDetailView`.
 */
export function QuoteWorkflowDetailView({ quoteWorkflow }: QuoteWorkflowDetailViewProps) {
  const { t } = useTranslation()
  const createdAt = formatDateTime(quoteWorkflow.created_at)

  return (
    <DetailPanel>
      <DetailHero
        media={<DetailMonogram name={quoteWorkflow.name} icon={<Workflow />} />}
        title={quoteWorkflow.name}
        badges={
          <Badge variant={quoteWorkflow.is_active ? 'default' : 'secondary'}>
            {t(
              quoteWorkflow.is_active
                ? 'quoteWorkflows.detail.active'
                : 'quoteWorkflows.detail.inactive',
            )}
          </Badge>
        }
      />

      <DetailSection title={t('quoteWorkflows.detail.criteriaTitle')}>
        {quoteWorkflow.criteria.length === 0 ? (
          <DetailEmpty />
        ) : (
          <DetailGrid>
            {quoteWorkflow.criteria.map((criterion) => (
              <DetailField
                key={criterion.id}
                label={criterion.field_source === 'custom' ? criterion.field_label : t(criterion.field_label)}
              >
                {criterion.value_label}
              </DetailField>
            ))}
          </DetailGrid>
        )}
      </DetailSection>

      <DetailSection title={t('quoteWorkflows.detail.statusesTitle')}>
        {quoteWorkflow.statuses.length === 0 ? (
          <DetailEmpty />
        ) : (
          <ul className="flex flex-col gap-1.5">
            {quoteWorkflow.statuses.map((status) => {
              const swatch = swatchClassFor(status.color)
              return (
                <li key={status.id} className="flex items-center gap-2 text-sm">
                  <span
                    className={cn('size-2.5 shrink-0 rounded-full border', swatch ?? 'bg-transparent')}
                    aria-hidden="true"
                  />
                  <span className="flex-1 truncate">{status.name}</span>
                  <Badge variant="secondary" className="shrink-0">
                    {t(`quoteWorkflows.form.statuses.group.${status.group}`)}
                  </Badge>
                </li>
              )
            })}
          </ul>
        )}
      </DetailSection>

      {quoteWorkflow.permissions.actions.view_activity ? (
        <DetailSection title={t('activityLog.title')} icon={<History />}>
          <ActivityLogSection resource="quote-workflows" id={quoteWorkflow.id} />
        </DetailSection>
      ) : null}

      {createdAt ? (
        <DetailMeta label={t('quoteWorkflows.detail.createdAt')}>{createdAt}</DetailMeta>
      ) : null}
    </DetailPanel>
  )
}
