import { useTranslation } from 'react-i18next'
import { useWatch } from 'react-hook-form'
import { ListChecks } from 'lucide-react'
import { DetailMonogram } from '@/components/detail/detail-panel'
import { RecordSection, RecordSectionsGrid } from '@/components/detail/record-panel'
import { RecordCreateLayout } from '@/components/record-form/record-create-layout'
import { Form } from '@/components/ui/form'
import { ChecklistItemsEditor } from '@/features/document-checklists/checklist-items-editor'
import { ChecklistSyncLogsSection } from '@/features/document-checklists/checklist-sync-logs-section'
import { DocumentChecklistFormBody } from '@/features/document-checklists/document-checklist-form-body'
import { useDocumentChecklistForm } from '@/features/document-checklists/use-document-checklist-form'
import type { DocumentChecklist, DocumentChecklistWithPermissions } from '@/features/document-checklists/types'

const DOCUMENT_CHECKLIST_FORM_ID = 'document-checklist-form'

interface DocumentChecklistFormProps {
  /** Saved checklist to edit (the detail); absent = create form. */
  checklist?: DocumentChecklist
  /** False for a viewer without update rights: every field is disabled and nothing can be saved. */
  canEdit?: boolean
  onSuccess: (saved: DocumentChecklistWithPermissions) => void
  /** Cancel of the create form; in edit it is not offered (the detail has nothing to cancel to). */
  onCancel: () => void
}

/**
 * Create form and detail of a document checklist in one: the detail IS the
 * form (spec 0403 AC-024). Edit mode adds the "Sincronizzazioni" section and
 * resets the form to the saved record on Annulla.
 */
export function DocumentChecklistForm({ checklist, canEdit = true, onSuccess, onCancel }: DocumentChecklistFormProps) {
  const { t } = useTranslation()
  const { form, serverError, onSubmit, resetToSaved } = useDocumentChecklistForm({ checklist, onSuccess })
  const name = useWatch({ control: form.control, name: 'name' })
  const isEdit = checklist !== undefined
  const heading = name.trim() || t(isEdit ? 'documentChecklists.form.editTitle' : 'documentChecklists.form.createTitle')

  return (
    <Form {...form}>
      <RecordCreateLayout
        formId={DOCUMENT_CHECKLIST_FORM_ID}
        onSubmit={form.handleSubmit(onSubmit)}
        title={heading}
        subtitle={t(isEdit ? 'documentChecklists.form.editSubtitle' : 'documentChecklists.form.createSubtitle')}
        media={<DetailMonogram name={heading} icon={<ListChecks />} />}
        isSubmitting={form.formState.isSubmitting}
        serverError={serverError}
        onCancel={isEdit ? resetToSaved : onCancel}
        labels={{
          save: t('documentChecklists.form.save'),
          saving: t('documentChecklists.form.saving'),
          cancel: t('documentChecklists.form.cancel'),
        }}
      >
        <fieldset disabled={!canEdit} className="min-w-0">
          <RecordSectionsGrid>
            <RecordSection title={t('documentChecklists.form.sections.identity.title')} full>
              <DocumentChecklistFormBody codeLocked={checklist?.code_locked ?? false} />
            </RecordSection>
            <RecordSection title={t('documentChecklists.form.sections.items.title')} full>
              <ChecklistItemsEditor />
            </RecordSection>
          </RecordSectionsGrid>
        </fieldset>
        {checklist ? (
          <RecordSectionsGrid className="border-t">
            <ChecklistSyncLogsSection checklist={checklist} />
          </RecordSectionsGrid>
        ) : null}
      </RecordCreateLayout>
    </Form>
  )
}
