import { useTranslation } from 'react-i18next'
import { Workflow } from 'lucide-react'
import { DetailEmpty, DetailMonogram } from '@/components/detail/detail-panel'
import {
  RecordCanvas,
  RecordCard,
  RecordCardHeader,
  RecordField,
  RecordFieldList,
  RecordMeta,
  RecordSection,
  RecordSectionsGrid,
} from '@/components/detail/record-panel'
import { RecordBody } from '@/components/detail/record-body'
import { RecordCollaborationCard } from '@/components/detail/record-collaboration-card'
import { RecordEditButton } from '@/components/detail/record-edit-button'
import { activityLogTab } from '@/features/activity-log/activity-log-tab'
import { Badge } from '@/components/ui/badge'
import { formatDateTime } from '@/features/table/cell-renderers'
import { swatchClassFor } from '@/features/custom-fields/badge-color-tokens'
import { cn } from '@/lib/utils'
import type { QuoteWorkflowDetailWithPermissions } from '@/features/quote-workflows/types'

interface QuoteWorkflowDetailViewProps {
  quoteWorkflow: QuoteWorkflowDetailWithPermissions
  /** Opens the module's existing edit surface (sheet or page); absent = no edit affordance. */
  onEdit?: () => void
}

/**
 * Read-only detail of a single opportunity workflow (spec 0047 Lane C): its
 * criteria (field label + resolved value label) and its status set, in
 * `sort_order`. Rendered as an enterprise-CRM record on the same kit
 * Opportunita' uses: the identity/fields card on the left, the activity card
 * on the right, a metadata footer.
 */
export function QuoteWorkflowDetailView({ quoteWorkflow, onEdit }: QuoteWorkflowDetailViewProps) {
  const { t } = useTranslation()
  const createdAt = formatDateTime(quoteWorkflow.created_at)
  const canEdit = quoteWorkflow.permissions.resource.update
  const canViewActivity = quoteWorkflow.permissions.actions.view_activity

  return (
    <RecordCanvas>
      <RecordBody
        side={
          canViewActivity ? (
            <RecordCollaborationCard
              tabs={[activityLogTab('quote-workflows', quoteWorkflow.id, t('activityLog.title'))]}
            />
          ) : null
        }
      >
        <RecordCard>
          <RecordCardHeader
            media={<DetailMonogram name={quoteWorkflow.name} icon={<Workflow />} />}
            title={quoteWorkflow.name}
            badges={
              <Badge variant={quoteWorkflow.is_active ? 'default' : 'secondary'}>
                {t(quoteWorkflow.is_active ? 'quoteWorkflows.detail.active' : 'quoteWorkflows.detail.inactive')}
              </Badge>
            }
            actions={canEdit && onEdit ? <RecordEditButton onClick={onEdit} /> : null}
          />
          <RecordSectionsGrid>
            <RecordSection title={t('quoteWorkflows.detail.criteriaTitle')}>
              {quoteWorkflow.criteria.length === 0 ? (
                <DetailEmpty />
              ) : (
                <RecordFieldList>
                  {quoteWorkflow.criteria.map((criterion) => (
                    <RecordField
                      key={criterion.id}
                      label={criterion.field_source === 'custom' ? criterion.field_label : t(criterion.field_label)}
                    >
                      {criterion.value_label}
                    </RecordField>
                  ))}
                </RecordFieldList>
              )}
            </RecordSection>

            <RecordSection title={t('quoteWorkflows.detail.statusesTitle')}>
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
            </RecordSection>
          </RecordSectionsGrid>
        </RecordCard>
      </RecordBody>

      {createdAt ? (
        <RecordMeta>
          <span>
            <span className="font-medium">{t('quoteWorkflows.detail.createdAt')}</span>{' '}
            <span aria-hidden="true">·</span> {createdAt}
          </span>
        </RecordMeta>
      ) : null}
    </RecordCanvas>
  )
}
