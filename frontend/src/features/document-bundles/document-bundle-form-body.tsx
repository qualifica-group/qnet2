import { useTranslation } from 'react-i18next'
import { useWatch } from 'react-hook-form'
import { FolderArchive } from 'lucide-react'
import { DetailMonogram } from '@/components/detail/detail-panel'
import { RecordFieldList, RecordSection, RecordSectionsGrid } from '@/components/detail/record-panel'
import { RecordCreateLayout } from '@/components/record-form/record-create-layout'
import { useDraftInlineEdit } from '@/components/record-form/use-draft-inline-edit'
import { Form } from '@/components/ui/form'
import { DocumentBundleRows } from '@/features/document-bundles/document-bundle-rows'
import { useDocumentBundleForm } from '@/features/document-bundles/use-document-bundle-form'
import type { DocumentBundle } from '@/features/document-bundles/types'

/** DOM id bridging the header's and the footer's save actions to the RHF `<form>`. */
const DOCUMENT_BUNDLE_FORM_ID = 'document-bundle-form'

/** Create mode is static: a fresh object per render would rebuild the form's defaults. */
const CREATE_MODE = { type: 'create' } as const

interface DocumentBundleFormBodyProps {
  onSuccess: (documentBundle: DocumentBundle) => void
  onCancel: () => void
}

/**
 * The document bundle create form as a replica of the detail (wave 3 of the
 * "no edit pages" plan): the same rows, closed until clicked (Fatto/Ripristina,
 * nothing saved per row); Salva validates and creates everything at once.
 * Files are not a field: the detail's `DocumentsSection` manages them once the
 * bundle exists.
 */
export function DocumentBundleFormBody({ onSuccess, onCancel }: DocumentBundleFormBodyProps) {
  const { t } = useTranslation()
  const { form, serverError, onSubmit } = useDocumentBundleForm({ mode: CREATE_MODE, onSuccess })
  const draft = useDraftInlineEdit(form)
  const name = useWatch({ control: form.control, name: 'name' })
  const heading = name.trim() || t('documentBundles.form.createTitle')

  return (
    <Form {...form}>
      <RecordCreateLayout
        formId={DOCUMENT_BUNDLE_FORM_ID}
        onSubmit={form.handleSubmit(onSubmit)}
        title={heading}
        subtitle={t('documentBundles.form.createSubtitle')}
        media={<DetailMonogram name={heading} icon={<FolderArchive />} />}
        isSubmitting={form.formState.isSubmitting}
        serverError={serverError}
        onCancel={onCancel}
        labels={{
          save: t('documentBundles.form.save'),
          saving: t('documentBundles.form.saving'),
          cancel: t('documentBundles.form.cancel'),
        }}
      >
        <RecordSectionsGrid>
          <RecordSection title={t('documentBundles.form.sections.identity.title')} full>
            <RecordFieldList>
              <DocumentBundleRows inline={draft} canEdit />
            </RecordFieldList>
          </RecordSection>
        </RecordSectionsGrid>
      </RecordCreateLayout>
    </Form>
  )
}
