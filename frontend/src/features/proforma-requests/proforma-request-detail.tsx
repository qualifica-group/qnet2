import { useTranslation } from 'react-i18next'
import { ReceiptEuro, MessagesSquare } from 'lucide-react'
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
import { RecordCollaborationCard, type RecordCollaborationTab } from '@/components/detail/record-collaboration-card'
import { RecordEditButton } from '@/components/detail/record-edit-button'
import { activityLogTab } from '@/features/activity-log/activity-log-tab'
import { useAbilities } from '@/features/auth/use-abilities'
import { NotesSection } from '@/features/notes/notes-section'
import { formatDateTime } from '@/features/table/cell-renderers'
import { PROFORMA_REQUESTS_DOMAIN } from '@/features/proforma-requests/api'
import type { ProformaRequestWithPermissions } from '@/features/proforma-requests/types'

interface ProformaRequestDetailViewProps {
  proformaRequest: ProformaRequestWithPermissions
  /** Opens the module's edit surface; absent = no edit affordance. */
  onEdit?: () => void
}

/** Read-only detail of a proforma request with its CRM notes thread and activity log. */
export function ProformaRequestDetailView({ proformaRequest: request, onEdit }: ProformaRequestDetailViewProps) {
  const { t } = useTranslation()
  const { can } = useAbilities()
  const { permissions } = request
  const createdAt = formatDateTime(request.created_at)
  const issuedAt = formatDateTime(request.issued_at)

  // Notes are gated on the resource permission alone; the server re-authorizes the thread.
  const tabs: RecordCollaborationTab[] = []
  if (can('proforma-requests.view')) {
    tabs.push({
      value: 'notes',
      label: t('notes.section.title'),
      icon: <MessagesSquare className="size-3.5" aria-hidden="true" />,
      content: <NotesSection entityType={PROFORMA_REQUESTS_DOMAIN} entityId={request.id} showHeader={false} />,
    })
  }
  if (permissions.actions.view_activity) {
    tabs.push(activityLogTab(PROFORMA_REQUESTS_DOMAIN, request.id, t('activityLog.title')))
  }

  return (
    <RecordCanvas>
      <RecordBody side={tabs.length > 0 ? <RecordCollaborationCard tabs={tabs} /> : null}>
        <RecordCard>
          <RecordCardHeader
            media={<DetailMonogram name={request.work_order.code} icon={<ReceiptEuro />} />}
            title={t('proformaRequests.detail.title', { code: request.work_order.code })}
            subtitle={t(`proformaRequests.kinds.${request.kind}`)}
            actions={permissions.resource.update && onEdit ? <RecordEditButton onClick={onEdit} /> : null}
          />
          <RecordSectionsGrid>
            <RecordSection title={t('proformaRequests.detail.sectionTitle')} full>
              <RecordFieldList>
                <RecordField label={t('proformaRequests.columns.work_order_title')}>
                  {request.work_order.title ? request.work_order.title : <DetailEmpty />}
                </RecordField>
                <RecordField label={t('proformaRequests.columns.company')}>
                  {request.company ? request.company.name : <DetailEmpty />}
                </RecordField>
                <RecordField label={t('proformaRequests.columns.customer')}>
                  {request.customer ? request.customer.name : <DetailEmpty />}
                </RecordField>
                <RecordField label={t('proformaRequests.columns.status')}>
                  {t(`proformaRequests.statuses.${request.status}`)}
                </RecordField>
                {issuedAt ? (
                  <RecordField label={t('proformaRequests.detail.issuedAt')}>{issuedAt}</RecordField>
                ) : null}
                <RecordField label={t('proformaRequests.columns.supplier')}>
                  {request.supplier ? request.supplier.name : <DetailEmpty />}
                </RecordField>
                <RecordField label={t('proformaRequests.columns.payment_method')}>
                  {request.payment_method ? request.payment_method.name : <DetailEmpty />}
                </RecordField>
                <RecordField label={t('proformaRequests.columns.assigned_to')}>
                  {request.assigned_to.name}
                </RecordField>
                <RecordField label={t('proformaRequests.columns.assigned_by')}>
                  {request.assigned_by.name}
                </RecordField>
                <RecordField label={t('proformaRequests.columns.note')}>
                  <span className="whitespace-pre-wrap">{request.note}</span>
                </RecordField>
              </RecordFieldList>
            </RecordSection>
          </RecordSectionsGrid>
        </RecordCard>
      </RecordBody>

      {createdAt ? (
        <RecordMeta>
          <span>
            <span className="font-medium">{t('proformaRequests.columns.created_at')}</span>{' '}
            <span aria-hidden="true">·</span> {createdAt}
          </span>
        </RecordMeta>
      ) : null}
    </RecordCanvas>
  )
}
