import { useTranslation } from 'react-i18next'
import { useWatch } from 'react-hook-form'
import { Network } from 'lucide-react'
import { DetailMonogram } from '@/components/detail/detail-panel'
import { RecordCreateLayout } from '@/components/record-form/record-create-layout'
import { useDraftInlineEdit } from '@/components/record-form/use-draft-inline-edit'
import { Form } from '@/components/ui/form'
import { useBusinessFunctionDraftValues } from '@/features/business-functions/use-business-function-draft-values'
import { BusinessFunctionSections } from '@/features/business-functions/business-function-rows'
import { useBusinessFunctionForm } from '@/features/business-functions/use-business-function-form'
import type { BusinessFunctionDetail } from '@/features/business-functions/types'

/** DOM id bridging the header's and the footer's save actions to the RHF `<form>`. */
const BUSINESS_FUNCTION_FORM_ID = 'business-function-form'

/** Create mode is static: a fresh object per render would rebuild the form's defaults. */
const CREATE_MODE = { type: 'create' } as const

interface BusinessFunctionFormBodyProps {
  onSuccess: (businessFunction: BusinessFunctionDetail) => void
  onCancel: () => void
}

/**
 * The business function create form as a replica of the detail (wave 3 of the "no edit
 * pages" plan): the same rows, closed until clicked (Fatto/Ripristina,
 * nothing saved per row); Salva validates and creates everything at once.
 */
export function BusinessFunctionFormBody({ onSuccess, onCancel }: BusinessFunctionFormBodyProps) {
  const { t } = useTranslation()
  const { form, serverError, onSubmit } = useBusinessFunctionForm({ mode: CREATE_MODE, onSuccess })
  const draft = useDraftInlineEdit(form)
  const name = useWatch({ control: form.control, name: 'name' })
  const values = useBusinessFunctionDraftValues(form.control)
  const heading = name.trim() || t('businessFunctions.form.createTitle')

  return (
    <Form {...form}>
      <RecordCreateLayout
        formId={BUSINESS_FUNCTION_FORM_ID}
        onSubmit={form.handleSubmit(onSubmit)}
        title={heading}
        subtitle={t('businessFunctions.form.createSubtitle')}
        media={<DetailMonogram name={heading} icon={<Network />} />}
        isSubmitting={form.formState.isSubmitting}
        serverError={serverError}
        onCancel={onCancel}
        labels={{
          save: t('businessFunctions.form.save'),
          saving: t('businessFunctions.form.saving'),
          cancel: t('businessFunctions.form.cancel'),
        }}
      >
        <BusinessFunctionSections form={form} inline={draft} canEdit values={values} />
      </RecordCreateLayout>
    </Form>
  )
}
