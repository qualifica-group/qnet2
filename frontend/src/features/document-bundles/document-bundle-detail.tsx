import { useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import { FolderArchive } from 'lucide-react'
import { DetailMonogram } from '@/components/detail/detail-panel'
import { RecordBody } from '@/components/detail/record-body'
import {
  RecordCanvas,
  RecordCardHeader,
  RecordField,
  RecordFieldList,
  RecordMeta,
  RecordSection,
  RecordSectionsGrid,
} from '@/components/detail/record-panel'
import type { RecordCollaborationTab } from '@/components/detail/record-collaboration-card'
import { useInlineEditingField, useRecordInlineEdit } from '@/components/record-form/use-record-inline-edit'
import { Form } from '@/components/ui/form'
import { activityLogTab } from '@/features/activity-log/activity-log-tab'
import { DocumentsSection } from '@/features/attachments/documents-section'
import { useAbilities } from '@/features/auth/use-abilities'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import { DOCUMENT_BUNDLE_ATTACHABLE_ALIAS } from '@/features/document-bundles/api'
import { DocumentBundleRows } from '@/features/document-bundles/document-bundle-rows'
import {
  useDocumentBundleForm,
  type DocumentBundleFormValues,
} from '@/features/document-bundles/use-document-bundle-form'
import { formatDateTime } from '@/features/table/cell-renderers'
import type { DocumentBundleFormMode, DocumentBundleWithPermissions } from '@/features/document-bundles/types'

interface DocumentBundleDetailViewProps {
  documentBundle: DocumentBundleWithPermissions
  /** Called after an in-place save, so the host refreshes whatever lists the record (spec 0195 D-7). */
  onChanged?: () => void
}

/**
 * Detail of a single document bundle on the record kit: the identity/fields
 * card on the left, the file manager + Attivita' tabs on the right. There is
 * no edit page (wave 3 of the "no edit pages" plan): every row edits IN
 * PLACE, one at a time, each save a PATCH of that field alone. Files reuse the
 * existing `DocumentsSection` (spec 0175 FE-03), gated by the generic
 * `attachments.*` abilities — same per-collection-not-per-record boundary
 * already documented on `registries`/`work-orders` (there is no
 * `document-bundles.viewDocuments` ability).
 */
export function DocumentBundleDetailView(props: DocumentBundleDetailViewProps) {
  // The rows read the field permissions, so the provider wraps the whole detail.
  return (
    <ResourcePermissionsProvider permissions={props.documentBundle.permissions}>
      <DocumentBundleDetailContent {...props} />
    </ResourcePermissionsProvider>
  )
}

function DocumentBundleDetailContent({ documentBundle, onChanged }: DocumentBundleDetailViewProps) {
  const { t } = useTranslation()
  const { can } = useAbilities()
  const editing = useInlineEditingField()
  // Stable per saved record: the form re-syncs its values on a new one.
  const mode = useMemo<DocumentBundleFormMode>(() => ({ type: 'edit', documentBundle }), [documentBundle])
  const { form, onSubmit, serverError, clearServerError } = useDocumentBundleForm({
    mode,
    onSuccess: () => {
      editing.close()
      onChanged?.()
    },
  })
  const inline = useRecordInlineEdit<DocumentBundleFormValues>({
    editing,
    form,
    onSubmit,
    serverError,
    clearServerError,
    genericErrorMessage: t('documentBundles.form.genericError'),
  })
  const canEdit = documentBundle.permissions.resource.update
  const createdAt = formatDateTime(documentBundle.created_at)
  const updatedAt = formatDateTime(documentBundle.updated_at)

  const tabs: RecordCollaborationTab[] = documentBundle.permissions.actions.view_activity
    ? [activityLogTab('document-bundles', documentBundle.id, t('activityLog.title'))]
    : []

  return (
    <RecordCanvas>
      <RecordBody
        collaborationTabs={tabs}
        header={
          <RecordCardHeader
            media={<DetailMonogram name={documentBundle.name} icon={<FolderArchive />} />}
            title={documentBundle.name}
            legacyId={documentBundle.old_id}
          />
        }
      >
        {/* Provider only (no DOM): the in-place rows share the edit form. */}
        <Form {...form}>
          <RecordSectionsGrid>
            <RecordSection title={t('documentBundles.form.sections.identity.title')} full>
              <RecordFieldList>
                <DocumentBundleRows inline={inline} canEdit={canEdit} />
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
        </Form>
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
