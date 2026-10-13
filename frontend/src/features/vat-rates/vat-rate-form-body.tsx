import { useTranslation } from 'react-i18next'
import { useWatch } from 'react-hook-form'
import { Percent } from 'lucide-react'
import { DetailMonogram } from '@/components/detail/detail-panel'
import { RecordFieldList, RecordSection, RecordSectionsGrid } from '@/components/detail/record-panel'
import { RecordCreateLayout } from '@/components/record-form/record-create-layout'
import { useDraftInlineEdit } from '@/components/record-form/use-draft-inline-edit'
import { Form } from '@/components/ui/form'
import { CustomFieldRecordSections } from '@/features/custom-fields/custom-field-record-sections'
import { VatRateRows } from '@/features/vat-rates/vat-rate-rows'
import { useVatRateForm } from '@/features/vat-rates/use-vat-rate-form'
import type { VatRateDetail } from '@/features/vat-rates/types'

/** DOM id bridging the header's and the footer's save actions to the RHF `<form>`. */
const VAT_RATE_FORM_ID = 'vat-rate-form'

/** Create mode is static: a fresh object per render would rebuild the form's defaults. */
const CREATE_MODE = { type: 'create' } as const

interface VatRateFormBodyProps {
  onSuccess: (vatRate: VatRateDetail) => void
  onCancel: () => void
}

/**
 * The VAT rate create form as a replica of the detail (wave 3 of the "no edit
 * pages" plan): the same rows and custom fields, closed until clicked
 * (Fatto/Ripristina, nothing saved per row); Salva validates and creates
 * everything at once.
 */
export function VatRateFormBody({ onSuccess, onCancel }: VatRateFormBodyProps) {
  const { t } = useTranslation()
  const { form, serverError, onSubmit } = useVatRateForm({ mode: CREATE_MODE, onSuccess })
  const draft = useDraftInlineEdit(form)
  const name = useWatch({ control: form.control, name: 'name' })
  const customFields = useWatch({ control: form.control, name: 'custom_fields' })
  const heading = name.trim() || t('vatRates.form.createTitle')

  return (
    <Form {...form}>
      <RecordCreateLayout
        formId={VAT_RATE_FORM_ID}
        onSubmit={form.handleSubmit(onSubmit)}
        title={heading}
        subtitle={t('vatRates.form.createSubtitle')}
        media={<DetailMonogram name={heading} icon={<Percent />} className="size-10 text-base [&>svg]:size-5" />}
        isSubmitting={form.formState.isSubmitting}
        serverError={serverError}
        onCancel={onCancel}
        labels={{
          save: t('vatRates.form.save'),
          saving: t('vatRates.form.saving'),
          cancel: t('vatRates.form.cancel'),
        }}
      >
        <RecordSectionsGrid>
          <RecordSection title={t('vatRates.form.sections.identity.title')} full>
            <RecordFieldList>
              <VatRateRows inline={draft} canEdit />
            </RecordFieldList>
          </RecordSection>
          <CustomFieldRecordSections
            resource="vat-rates"
            values={customFields ?? {}}
            inline={draft}
            control={form.control}
          />
        </RecordSectionsGrid>
      </RecordCreateLayout>
    </Form>
  )
}
