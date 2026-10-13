import { useTranslation } from 'react-i18next'
import { useWatch } from 'react-hook-form'
import { Tag } from 'lucide-react'
import { DetailMonogram } from '@/components/detail/detail-panel'
import { RecordFieldList, RecordSection, RecordSectionsGrid } from '@/components/detail/record-panel'
import { RecordCreateLayout } from '@/components/record-form/record-create-layout'
import { useDraftInlineEdit } from '@/components/record-form/use-draft-inline-edit'
import { Form } from '@/components/ui/form'
import { InvoiceTagRows } from '@/features/invoice-tags/invoice-tag-rows'
import { useInvoiceTagForm } from '@/features/invoice-tags/use-invoice-tag-form'
import type { InvoiceTagDetail } from '@/features/invoice-tags/types'

/** DOM id bridging the header's and the footer's save actions to the RHF `<form>`. */
const INVOICE_TAG_FORM_ID = 'invoice-tag-form'

/** Create mode is static: a fresh object per render would rebuild the form's defaults. */
const CREATE_MODE = { type: 'create' } as const

interface InvoiceTagFormBodyProps {
  onSuccess: (invoiceTag: InvoiceTagDetail) => void
  onCancel: () => void
}

/**
 * The invoice tag create form as a replica of the detail (wave 3 of the "no
 * edit pages" plan): the same rows, closed until clicked (Fatto/Ripristina,
 * nothing saved per row); Salva validates and creates everything at once.
 */
export function InvoiceTagFormBody({ onSuccess, onCancel }: InvoiceTagFormBodyProps) {
  const { t } = useTranslation()
  const { form, serverError, onSubmit } = useInvoiceTagForm({ mode: CREATE_MODE, onSuccess })
  const draft = useDraftInlineEdit(form)
  const name = useWatch({ control: form.control, name: 'name' })
  const heading = name.trim() || t('invoiceTags.form.createTitle')

  return (
    <Form {...form}>
      <RecordCreateLayout
        formId={INVOICE_TAG_FORM_ID}
        onSubmit={form.handleSubmit(onSubmit)}
        title={heading}
        subtitle={t('invoiceTags.form.createSubtitle')}
        media={<DetailMonogram name={heading} icon={<Tag />} />}
        isSubmitting={form.formState.isSubmitting}
        serverError={serverError}
        onCancel={onCancel}
        labels={{
          save: t('invoiceTags.form.save'),
          saving: t('invoiceTags.form.saving'),
          cancel: t('invoiceTags.form.cancel'),
        }}
      >
        <RecordSectionsGrid>
          <RecordSection title={t('invoiceTags.form.sections.identity.title')} full>
            <RecordFieldList>
              <InvoiceTagRows inline={draft} canEdit />
            </RecordFieldList>
          </RecordSection>
        </RecordSectionsGrid>
      </RecordCreateLayout>
    </Form>
  )
}
