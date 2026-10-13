import { useTranslation } from 'react-i18next'
import { useWatch } from 'react-hook-form'
import { Database } from 'lucide-react'
import { DetailMonogram } from '@/components/detail/detail-panel'
import { RecordCreateLayout } from '@/components/record-form/record-create-layout'
import { useDraftInlineEdit } from '@/components/record-form/use-draft-inline-edit'
import { Form } from '@/components/ui/form'
import { SourceSections } from '@/features/sources/source-rows'
import { useSourceForm } from '@/features/sources/use-source-form'
import type { SourceDetail } from '@/features/sources/types'

/** DOM id bridging the header's and the footer's save actions to the RHF `<form>`. */
const SOURCE_FORM_ID = 'source-form'

/** Create mode is static: a fresh object per render would rebuild the form's defaults. */
const CREATE_MODE = { type: 'create' } as const

interface SourceFormBodyProps {
  onSuccess: (source: SourceDetail) => void
  onCancel: () => void
}

/**
 * The source create form as a replica of the detail (wave 3 of the "no edit
 * pages" plan): the same rows, closed until clicked (Fatto/Ripristina,
 * nothing saved per row); Salva validates and creates everything at once.
 */
export function SourceFormBody({ onSuccess, onCancel }: SourceFormBodyProps) {
  const { t } = useTranslation()
  const { form, serverError, onSubmit } = useSourceForm({ mode: CREATE_MODE, onSuccess })
  const draft = useDraftInlineEdit(form)
  const [name, customFields] = useWatch({ control: form.control, name: ['name', 'custom_fields'] })
  const heading = name.trim() || t('sources.form.createTitle')

  return (
    <Form {...form}>
      <RecordCreateLayout
        formId={SOURCE_FORM_ID}
        onSubmit={form.handleSubmit(onSubmit)}
        title={heading}
        subtitle={t('sources.form.createSubtitle')}
        media={<DetailMonogram name={heading} icon={<Database />} />}
        isSubmitting={form.formState.isSubmitting}
        serverError={serverError}
        onCancel={onCancel}
        labels={{
          save: t('sources.form.save'),
          saving: t('sources.form.saving'),
          cancel: t('sources.form.cancel'),
        }}
      >
        <SourceSections form={form} inline={draft} canEdit customFieldValues={customFields} />
      </RecordCreateLayout>
    </Form>
  )
}
