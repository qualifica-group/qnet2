import { useTranslation } from 'react-i18next'
import { useWatch } from 'react-hook-form'
import { FileText } from 'lucide-react'
import { DetailMonogram } from '@/components/detail/detail-panel'
import { RecordFieldList, RecordSection, RecordSectionsGrid } from '@/components/detail/record-panel'
import { RecordCreateLayout } from '@/components/record-form/record-create-layout'
import { useDraftInlineEdit } from '@/components/record-form/use-draft-inline-edit'
import { Form } from '@/components/ui/form'
import { MetaFieldRowContext } from '@/features/authorization/meta-field-row-context'
import { useResourcePermissions } from '@/features/authorization/permissions'
import { DocumentLayoutContentEditor } from '@/features/document-layouts/document-layout-content'
import { DocumentLayoutRows } from '@/features/document-layouts/document-layout-rows'
import { useDocumentLayoutForm } from '@/features/document-layouts/use-document-layout-form'
import type { DocumentLayoutDetail } from '@/features/document-layouts/types'

/** DOM id bridging the header's and the footer's save actions to the RHF `<form>`. */
const DOCUMENT_LAYOUT_FORM_ID = 'document-layout-form'

/** Create mode is static: a fresh object per render would rebuild the form's defaults. */
const CREATE_MODE = { type: 'create' } as const

interface DocumentLayoutFormBodyProps {
  onSuccess: (documentLayout: DocumentLayoutDetail) => void
  onCancel: () => void
}

/**
 * The document layout create form as a replica of the detail (wave 3 of the
 * "no edit pages" plan): the header rows closed until clicked
 * (Fatto/Ripristina, nothing saved per row) and the visual editor OPEN, since
 * it is an editor rather than a field (precedent: the quote's line grids,
 * spec 0197); Salva validates and creates everything at once. `config` stays
 * a field-permission-gated field: hidden when not visible, disabled when not
 * editable (spec 0004).
 */
export function DocumentLayoutFormBody({ onSuccess, onCancel }: DocumentLayoutFormBodyProps) {
  const { t } = useTranslation()
  const { field: fieldPermission } = useResourcePermissions()
  const { form, serverError, onSubmit, configErrors } = useDocumentLayoutForm({ mode: CREATE_MODE, onSuccess })
  const draft = useDraftInlineEdit(form)
  const name = useWatch({ control: form.control, name: 'name' })
  const heading = name.trim() || t('documentLayouts.form.createTitle')

  return (
    <Form {...form}>
      <RecordCreateLayout
        formId={DOCUMENT_LAYOUT_FORM_ID}
        onSubmit={form.handleSubmit(onSubmit)}
        title={heading}
        subtitle={t('documentLayouts.form.createSubtitle')}
        media={<DetailMonogram name={heading} icon={<FileText />} />}
        isSubmitting={form.formState.isSubmitting}
        serverError={serverError}
        onCancel={onCancel}
        labels={{
          save: t('documentLayouts.form.save'),
          saving: t('documentLayouts.form.saving'),
          cancel: t('documentLayouts.form.cancel'),
        }}
      >
        <RecordSectionsGrid>
          <RecordSection title={t('documentLayouts.form.sections.identity.title')} full>
            <RecordFieldList>
              <DocumentLayoutRows inline={draft} canEdit creating />
            </RecordFieldList>
          </RecordSection>
          {fieldPermission('config').visible ? (
            <RecordSection title={t('documentLayouts.form.tabs.content')} full>
              {/* The section title already names the editor: its own label stays for assistive tech. */}
              <MetaFieldRowContext.Provider value="config">
                <DocumentLayoutContentEditor layoutId={null} configErrors={configErrors} />
              </MetaFieldRowContext.Provider>
            </RecordSection>
          ) : null}
        </RecordSectionsGrid>
      </RecordCreateLayout>
    </Form>
  )
}
