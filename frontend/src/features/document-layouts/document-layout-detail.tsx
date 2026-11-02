import { useMemo, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Copy, FileText } from 'lucide-react'
import { DetailMonogram } from '@/components/detail/detail-panel'
import { RecordBody } from '@/components/detail/record-body'
import {
  RecordCanvas,
  RecordCardHeader,
  RecordFieldList,
  RecordMeta,
  RecordSection,
  RecordSectionsGrid,
} from '@/components/detail/record-panel'
import type { RecordCollaborationTab } from '@/components/detail/record-collaboration-card'
import { useInlineEditingField, useRecordInlineEdit } from '@/components/record-form/use-record-inline-edit'
import { Button } from '@/components/ui/button'
import { Form } from '@/components/ui/form'
import { activityLogTab } from '@/features/activity-log/activity-log-tab'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import { DocumentLayoutCloneDialog } from '@/features/document-layouts/document-layout-clone-dialog'
import { DocumentLayoutContentRow } from '@/features/document-layouts/document-layout-content'
import { DocumentLayoutRows } from '@/features/document-layouts/document-layout-rows'
import {
  useDocumentLayoutForm,
  type DocumentLayoutFormValues,
} from '@/features/document-layouts/use-document-layout-form'
import { formatDateTime } from '@/features/table/cell-renderers'
import type {
  DocumentLayoutCloneSource,
  DocumentLayoutDetailWithPermissions,
  DocumentLayoutFormMode,
} from '@/features/document-layouts/types'

interface DocumentLayoutDetailViewProps {
  documentLayout: DocumentLayoutDetailWithPermissions
  /** Called after an in-place save, so the host refreshes whatever lists the record (spec 0195 D-7). */
  onChanged?: () => void
}

/**
 * Detail of a single document layout on the record kit: identity band, the
 * "Dettagli" rows, the content as one full-width row (an A4 preview until its
 * pencil opens the visual editor), the Attivita' tab on the right when
 * authorized, a metadata footer. There is no edit page (wave 3 of the "no
 * edit pages" plan): every row edits IN PLACE, one at a time, each save a
 * PATCH of that field alone. `code` and `module` are immutable after create.
 * "Clona" (spec 0339) copies the layout into a new one and opens it.
 */
export function DocumentLayoutDetailView(props: DocumentLayoutDetailViewProps) {
  // The rows read the field permissions, so the provider wraps the whole detail.
  return (
    <ResourcePermissionsProvider permissions={props.documentLayout.permissions}>
      <DocumentLayoutDetailContent {...props} />
    </ResourcePermissionsProvider>
  )
}

function DocumentLayoutDetailContent({ documentLayout, onChanged }: DocumentLayoutDetailViewProps) {
  const { t } = useTranslation()
  const editing = useInlineEditingField()
  const [cloneSource, setCloneSource] = useState<DocumentLayoutCloneSource | null>(null)
  // Stable per saved record: the form re-syncs its values on a new one.
  const mode = useMemo<DocumentLayoutFormMode>(() => ({ type: 'edit', documentLayout }), [documentLayout])
  const { form, onSubmit, serverError, clearServerError, configErrors } = useDocumentLayoutForm({
    mode,
    onSuccess: () => {
      editing.close()
      onChanged?.()
    },
  })
  const inline = useRecordInlineEdit<DocumentLayoutFormValues>({
    editing,
    form,
    onSubmit,
    serverError,
    clearServerError,
    genericErrorMessage: t('documentLayouts.form.genericError'),
  })
  const canEdit = documentLayout.permissions.resource.update
  const canClone = documentLayout.permissions.resource.create
  const createdAt = formatDateTime(documentLayout.created_at)
  const updatedAt = formatDateTime(documentLayout.updated_at)

  const tabs: RecordCollaborationTab[] = documentLayout.permissions.actions.view_activity
    ? [activityLogTab('document-layouts', documentLayout.id, t('activityLog.title'))]
    : []

  return (
    <RecordCanvas>
      <RecordBody
        collaborationTabs={tabs}
        header={
          <RecordCardHeader
            media={<DetailMonogram name={documentLayout.name} icon={<FileText />} />}
            title={documentLayout.name}
            subtitle={documentLayout.code}
            actions={
              canClone ? (
                <Button variant="outline" size="sm" className="bg-card" onClick={() => setCloneSource(documentLayout)}>
                  <Copy aria-hidden="true" />
                  {t('documentLayouts.clone.action')}
                </Button>
              ) : null
            }
          />
        }
      >
        {/* Provider only (no DOM): the in-place rows share the edit form. */}
        <Form {...form}>
          <RecordSectionsGrid>
            <RecordSection title={t('documentLayouts.form.sections.identity.title')} full>
              <RecordFieldList>
                <DocumentLayoutRows inline={inline} canEdit={canEdit} creating={false} />
              </RecordFieldList>
            </RecordSection>
            <RecordSection title={t('documentLayouts.form.tabs.content')} full>
              <DocumentLayoutContentRow
                inline={inline}
                canEdit={canEdit}
                layoutId={documentLayout.id}
                configErrors={configErrors}
              />
            </RecordSection>
          </RecordSectionsGrid>
        </Form>
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

      <DocumentLayoutCloneDialog source={cloneSource} onClose={() => setCloneSource(null)} />
    </RecordCanvas>
  )
}
