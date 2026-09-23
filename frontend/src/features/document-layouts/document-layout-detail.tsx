import { useTranslation } from 'react-i18next'
import { FileText } from 'lucide-react'
import { DetailEmpty, DetailMonogram } from '@/components/detail/detail-panel'
import { RecordBody } from '@/components/detail/record-body'
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
import {
  RecordCollaborationCard,
  type RecordCollaborationTab,
} from '@/components/detail/record-collaboration-card'
import { RecordEditButton } from '@/components/detail/record-edit-button'
import { activityLogTab } from '@/features/activity-log/activity-log-tab'
import { formatDateTime } from '@/features/table/cell-renderers'
import type { DocumentLayoutDetailWithPermissions } from '@/features/document-layouts/types'

interface DocumentLayoutDetailViewProps {
  documentLayout: DocumentLayoutDetailWithPermissions
  /** Opens the module's existing edit surface (sheet or page); absent = no edit affordance. */
  onEdit?: () => void
}

/**
 * Read-only detail of a single document layout's METADATA (name/code/
 * description/module/active/default — spec 0069 wave 1), rendered as an
 * enterprise-CRM record (Opportunita' reference kit): the identity/fields
 * card on the left, the Attivita' tab on the right when authorized, a
 * metadata footer. It deliberately does NOT render the block/zone `config`
 * or an A4 preview: that surface belongs to the visual editor
 * (`features/document-layouts/editor/`), a different owner.
 */
export function DocumentLayoutDetailView({ documentLayout, onEdit }: DocumentLayoutDetailViewProps) {
  const { t } = useTranslation()
  const canEdit = documentLayout.permissions.resource.update
  const createdAt = formatDateTime(documentLayout.created_at)
  const updatedAt = formatDateTime(documentLayout.updated_at)

  const tabs: RecordCollaborationTab[] = documentLayout.permissions.actions.view_activity
    ? [activityLogTab('document-layouts', documentLayout.id, t('activityLog.title'))]
    : []

  return (
    <RecordCanvas>
      <RecordBody side={tabs.length > 0 ? <RecordCollaborationCard tabs={tabs} /> : null}>
        <RecordCard>
          <RecordCardHeader
            media={<DetailMonogram name={documentLayout.name} icon={<FileText />} />}
            title={documentLayout.name}
            subtitle={documentLayout.code}
            actions={canEdit && onEdit ? <RecordEditButton onClick={onEdit} /> : null}
          />
          <RecordSectionsGrid>
            <RecordSection title={t('documentLayouts.form.sections.identity.title')} full>
              <RecordFieldList>
                <RecordField label={t('documentLayouts.detail.description')}>
                  {documentLayout.description ? documentLayout.description : <DetailEmpty />}
                </RecordField>
                <RecordField label={t('documentLayouts.detail.module')}>
                  {documentLayout.module_label}
                </RecordField>
                <RecordField label={t('documentLayouts.detail.isActive')}>
                  {documentLayout.is_active ? t('common.yes') : t('common.no')}
                </RecordField>
                <RecordField label={t('documentLayouts.detail.isDefault')}>
                  {documentLayout.is_default ? t('common.yes') : t('common.no')}
                </RecordField>
              </RecordFieldList>
            </RecordSection>
          </RecordSectionsGrid>
        </RecordCard>
      </RecordBody>

      <RecordMeta>
        {createdAt ? (
          <span>
            <span className="font-medium">{t('documentLayouts.detail.createdAt')}</span>{' '}
            <span aria-hidden="true">·</span> {createdAt}
          </span>
        ) : null}
        {updatedAt ? (
          <span>
            <span className="font-medium">{t('documentLayouts.detail.updatedAt')}</span>{' '}
            <span aria-hidden="true">·</span> {updatedAt}
          </span>
        ) : null}
      </RecordMeta>
    </RecordCanvas>
  )
}
