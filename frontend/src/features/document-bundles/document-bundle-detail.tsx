import { useTranslation } from 'react-i18next'
import { FolderArchive } from 'lucide-react'
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
import { DocumentsSection } from '@/features/attachments/documents-section'
import { useAbilities } from '@/features/auth/use-abilities'
import { DOCUMENT_BUNDLE_ATTACHABLE_ALIAS } from '@/features/document-bundles/api'
import { formatDateTime } from '@/features/table/cell-renderers'
import type { DocumentBundleWithPermissions } from '@/features/document-bundles/types'

interface DocumentBundleDetailViewProps {
  documentBundle: DocumentBundleWithPermissions
  /** Opens the module's existing edit surface (sheet or page); absent = no edit affordance. */
  onEdit?: () => void
}

/**
 * Read-only detail of a single document bundle, rendered as an
 * enterprise-CRM record (`task-importances` reference kit): the
 * identity/fields card on the left, the file manager + Attivita' tabs on the
 * right. Files (upload/delete) reuse the existing `DocumentsSection` (spec
 * 0175 FE-03), gated by the generic `attachments.*` abilities — same
 * per-collection-not-per-record boundary already documented on
 * `registries`/`work-orders` (there is no `document-bundles.viewDocuments`
 * ability: viewing this detail at all already required `document-bundles.view`).
 */
export function DocumentBundleDetailView({ documentBundle, onEdit }: DocumentBundleDetailViewProps) {
  const { t } = useTranslation()
  const { can } = useAbilities()
  const canEdit = documentBundle.permissions.resource.update
  const createdAt = formatDateTime(documentBundle.created_at)
  const updatedAt = formatDateTime(documentBundle.updated_at)

  const tabs: RecordCollaborationTab[] = documentBundle.permissions.actions.view_activity
    ? [activityLogTab('document-bundles', documentBundle.id, t('activityLog.title'))]
    : []

  return (
    <RecordCanvas>
      <RecordBody side={tabs.length > 0 ? <RecordCollaborationCard tabs={tabs} /> : null}>
        <RecordCard>
          <RecordCardHeader
            media={<DetailMonogram name={documentBundle.name} icon={<FolderArchive />} />}
            title={documentBundle.name}
            actions={canEdit && onEdit ? <RecordEditButton onClick={onEdit} /> : null}
          />
          <RecordSectionsGrid>
            <RecordSection title={t('documentBundles.form.sections.identity.title')} full>
              <RecordFieldList>
                <RecordField label={t('documentBundles.detail.description')}>
                  {documentBundle.description ? documentBundle.description : <DetailEmpty />}
                </RecordField>
                <RecordField label={t('documentBundles.detail.isActive')}>
                  {documentBundle.is_active ? t('common.yes') : t('common.no')}
                </RecordField>
                <RecordField label={t('documentBundles.detail.filesCount')}>
                  {documentBundle.files_count}
                </RecordField>
              </RecordFieldList>
            </RecordSection>
            <RecordSection title={t('documentBundles.form.sections.files.title')} full>
              <DocumentsSection
                resource={DOCUMENT_BUNDLE_ATTACHABLE_ALIAS}
                id={documentBundle.id}
                canUpload={can('attachments.create')}
                canDelete={can('attachments.delete')}
              />
            </RecordSection>
          </RecordSectionsGrid>
        </RecordCard>
      </RecordBody>

      <RecordMeta>
        {createdAt ? (
          <span>
            <span className="font-medium">{t('documentBundles.detail.created_at')}</span>{' '}
            <span aria-hidden="true">·</span> {createdAt}
          </span>
        ) : null}
        {updatedAt ? (
          <span>
            <span className="font-medium">{t('documentBundles.detail.updated_at')}</span>{' '}
            <span aria-hidden="true">·</span> {updatedAt}
          </span>
        ) : null}
      </RecordMeta>
    </RecordCanvas>
  )
}
