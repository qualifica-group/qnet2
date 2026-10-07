import { useTranslation } from 'react-i18next'
import { Flag } from 'lucide-react'
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
import { formatDateTime } from '@/features/table/cell-renderers'
import { swatchClassFor } from '@/features/custom-fields/badge-color-tokens'
import { cn } from '@/lib/utils'
import type { WorkOrderPaymentStatusDetailWithPermissions } from '@/features/work-order-payment-statuses/types'

interface WorkOrderPaymentStatusDetailViewProps {
  workOrderPaymentStatus: WorkOrderPaymentStatusDetailWithPermissions
  /** Opens the module's existing edit surface (sheet or page); absent = no edit affordance. */
  onEdit?: () => void
}

/**
 * Read-only detail of a single work order payment status, rendered as an enterprise-CRM
 * record (Opportunita' reference layout): the identity/fields card on the
 * left, the activity card on the right, a metadata footer.
 */
export function WorkOrderPaymentStatusDetailView({ workOrderPaymentStatus, onEdit }: WorkOrderPaymentStatusDetailViewProps) {
  const { t } = useTranslation()
  const canEdit = workOrderPaymentStatus.permissions.resource.update
  const createdAt = formatDateTime(workOrderPaymentStatus.created_at)
  const updatedAt = formatDateTime(workOrderPaymentStatus.updated_at)
  const swatch = swatchClassFor(workOrderPaymentStatus.color)
  const canViewActivity = workOrderPaymentStatus.permissions.actions.view_activity

  return (
    <RecordCanvas>
      <RecordBody
        side={
          canViewActivity ? (
            <RecordCollaborationCard
              tabs={[activityLogTab('work-order-payment-statuses', workOrderPaymentStatus.id, t('activityLog.title'))]}
            />
          ) : null
        }
      >
        <RecordCard>
          <RecordCardHeader
            media={<DetailMonogram name={workOrderPaymentStatus.name} icon={<Flag />} className="size-10 text-base [&>svg]:size-5" />}
            title={workOrderPaymentStatus.name}
            actions={canEdit && onEdit ? <RecordEditButton onClick={onEdit} /> : null}
          />
          <RecordSectionsGrid>
            <RecordSection title={t('workOrderPaymentStatuses.form.sections.identity.title')} full>
              <RecordFieldList>
                <RecordField label={t('workOrderPaymentStatuses.detail.description')}>
                  {workOrderPaymentStatus.description ? workOrderPaymentStatus.description : <DetailEmpty />}
                </RecordField>
                <RecordField label={t('workOrderPaymentStatuses.detail.color')}>
                  <span className="flex items-center gap-2">
                    <span
                      className={cn('size-3.5 shrink-0 rounded-full border', swatch ?? 'bg-transparent')}
                      aria-hidden="true"
                    />
                    {t(`customFields.colors.${workOrderPaymentStatus.color}`)}
                  </span>
                </RecordField>
                <RecordField label={t('workOrderPaymentStatuses.detail.sort_order')}>
                  {workOrderPaymentStatus.sort_order}
                </RecordField>
                <RecordField label={t('workOrderPaymentStatuses.detail.is_active')}>
                  {workOrderPaymentStatus.is_active ? t('common.yes') : t('common.no')}
                </RecordField>
                <RecordField label={t('workOrderPaymentStatuses.detail.allows_delivery')}>
                  {workOrderPaymentStatus.allows_delivery ? t('common.yes') : t('common.no')}
                </RecordField>
              </RecordFieldList>
            </RecordSection>
          </RecordSectionsGrid>
        </RecordCard>
      </RecordBody>

      <RecordMeta>
        {createdAt ? (
          <span>
            <span className="font-medium">{t('workOrderPaymentStatuses.detail.created_at')}</span>{' '}
            <span aria-hidden="true">·</span> {createdAt}
          </span>
        ) : null}
        {updatedAt ? (
          <span>
            <span className="font-medium">{t('workOrderPaymentStatuses.detail.updated_at')}</span>{' '}
            <span aria-hidden="true">·</span> {updatedAt}
          </span>
        ) : null}
      </RecordMeta>
    </RecordCanvas>
  )
}
