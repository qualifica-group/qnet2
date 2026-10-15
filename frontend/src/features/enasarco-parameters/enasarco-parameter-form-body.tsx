import { Percent } from 'lucide-react'
import { useWatch } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { DetailMonogram } from '@/components/detail/detail-panel'
import { RecordCreateLayout } from '@/components/record-form/record-create-layout'
import { useDraftInlineEdit } from '@/components/record-form/use-draft-inline-edit'
import { Form } from '@/components/ui/form'
import { EnasarcoParameterSections } from '@/features/enasarco-parameters/enasarco-parameter-rows'
import { useEnasarcoParameterForm } from '@/features/enasarco-parameters/use-enasarco-parameter-form'
import type { EnasarcoParameterDetail } from '@/features/enasarco-parameters/types'

/** DOM id bridging the header's and the footer's save actions to the RHF `<form>`. */
const ENASARCO_PARAMETER_FORM_ID = 'enasarco-parameter-form'

const CREATE_MODE = { type: 'create' } as const

interface EnasarcoParameterFormBodyProps {
  onSuccess: (parameter: EnasarcoParameterDetail) => void
  onCancel: () => void
}

/**
 * The create form as a replica of the detail (spec 0264 D-5): the same rows,
 * closed until clicked (Fatto/Ripristina, nothing saved per row); Salva
 * validates and creates everything at once.
 */
export function EnasarcoParameterFormBody({ onSuccess, onCancel }: EnasarcoParameterFormBodyProps) {
  const { t } = useTranslation()
  const { form, serverError, onSubmit } = useEnasarcoParameterForm({ mode: CREATE_MODE, onSuccess })
  const draft = useDraftInlineEdit(form)
  const year = useWatch({ control: form.control, name: 'year' }).trim()
  const heading = year !== '' ? t('enasarcoParameters.detail.title', { year }) : t('enasarcoParameters.form.newParameter')

  return (
    <Form {...form}>
      <RecordCreateLayout
        formId={ENASARCO_PARAMETER_FORM_ID}
        onSubmit={form.handleSubmit(onSubmit)}
        title={heading}
        subtitle={t('enasarcoParameters.form.createSubtitle')}
        media={<DetailMonogram name={heading} icon={<Percent />} />}
        isSubmitting={form.formState.isSubmitting}
        serverError={serverError}
        onCancel={onCancel}
        labels={{
          save: t('enasarcoParameters.form.save'),
          saving: t('enasarcoParameters.form.saving'),
          cancel: t('common.cancel'),
        }}
      >
        <EnasarcoParameterSections inline={draft} canEdit />
      </RecordCreateLayout>
    </Form>
  )
}
