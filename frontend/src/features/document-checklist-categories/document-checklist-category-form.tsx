import { useTranslation } from 'react-i18next'
import { useWatch } from 'react-hook-form'
import { ListTree } from 'lucide-react'
import { DetailMonogram } from '@/components/detail/detail-panel'
import { RecordSection, RecordSectionsGrid } from '@/components/detail/record-panel'
import { RecordCreateLayout } from '@/components/record-form/record-create-layout'
import { Form } from '@/components/ui/form'
import { ChecklistSyncLogsSection } from '@/features/document-checklists/checklist-sync-logs-section'
import { fetchDocumentChecklistCategorySyncLogs } from '@/features/document-checklist-categories/api'
import { DocumentChecklistCategoryFormBody } from '@/features/document-checklist-categories/document-checklist-category-form-body'
import { documentChecklistCategoryKeys } from '@/features/document-checklist-categories/query-keys'
import { useDocumentChecklistCategoryForm } from '@/features/document-checklist-categories/use-document-checklist-category-form'
import type {
  DocumentChecklistCategory,
  DocumentChecklistCategoryWithPermissions,
} from '@/features/document-checklist-categories/types'

const CATEGORY_FORM_ID = 'document-checklist-category-form'

interface DocumentChecklistCategoryFormProps {
  /** Saved category to edit (the detail); absent = create form. */
  category?: DocumentChecklistCategory
  /** False for a viewer without update rights: every field is disabled. */
  canEdit?: boolean
  onSuccess: (saved: DocumentChecklistCategoryWithPermissions) => void
  /** Cancel of the create form; in edit it is not offered. */
  onCancel: () => void
}

/**
 * Create form and detail of a category in one (the detail IS the form, like
 * the checklist). Edit mode adds the "Sincronizzazioni" section.
 */
export function DocumentChecklistCategoryForm({
  category,
  canEdit = true,
  onSuccess,
  onCancel,
}: DocumentChecklistCategoryFormProps) {
  const { t } = useTranslation()
  const { form, serverError, onSubmit, resetToSaved } = useDocumentChecklistCategoryForm({ category, onSuccess })
  const name = useWatch({ control: form.control, name: 'name' })
  const isEdit = category !== undefined
  const heading =
    name.trim() || t(isEdit ? 'documentChecklistCategories.form.editTitle' : 'documentChecklistCategories.form.createTitle')

  return (
    <Form {...form}>
      <RecordCreateLayout
        formId={CATEGORY_FORM_ID}
        onSubmit={form.handleSubmit(onSubmit)}
        title={heading}
        subtitle={t(
          isEdit ? 'documentChecklistCategories.form.editSubtitle' : 'documentChecklistCategories.form.createSubtitle',
        )}
        media={<DetailMonogram name={heading} icon={<ListTree />} />}
        isSubmitting={form.formState.isSubmitting}
        serverError={serverError}
        onCancel={isEdit ? resetToSaved : onCancel}
        labels={{
          save: t('documentChecklistCategories.form.save'),
          saving: t('documentChecklistCategories.form.saving'),
          cancel: t('documentChecklistCategories.form.cancel'),
        }}
      >
        <fieldset disabled={!canEdit} className="min-w-0">
          <RecordSectionsGrid>
            <RecordSection title={t('documentChecklistCategories.form.sections.identity.title')} full>
              <DocumentChecklistCategoryFormBody />
            </RecordSection>
          </RecordSectionsGrid>
        </fieldset>
        {category ? (
          <RecordSectionsGrid className="border-t">
            <ChecklistSyncLogsSection
              record={category}
              logsQueryKey={documentChecklistCategoryKeys.syncLogs(category.id)}
              fetchLogs={() => fetchDocumentChecklistCategorySyncLogs(category.id)}
            />
          </RecordSectionsGrid>
        ) : null}
      </RecordCreateLayout>
    </Form>
  )
}
