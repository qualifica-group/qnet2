import { useTranslation } from 'react-i18next'
import { useWatch } from 'react-hook-form'
import { Tag } from 'lucide-react'
import { DetailMonogram } from '@/components/detail/detail-panel'
import { RecordCreateLayout } from '@/components/record-form/record-create-layout'
import { useDraftInlineEdit } from '@/components/record-form/use-draft-inline-edit'
import { Form } from '@/components/ui/form'
import { ReferentTypeSections } from '@/features/referent-types/referent-type-rows'
import { useReferentTypeForm } from '@/features/referent-types/use-referent-type-form'
import type { ReferentTypeDetail } from '@/features/referent-types/types'

/** DOM id bridging the header's and the footer's save actions to the RHF `<form>`. */
const REFERENT_TYPE_FORM_ID = 'referent-type-form'

/** Create mode is static: a fresh object per render would rebuild the form's defaults. */
const CREATE_MODE = { type: 'create' } as const

interface ReferentTypeFormBodyProps {
  onSuccess: (referentType: ReferentTypeDetail) => void
  onCancel: () => void
}

/**
 * The referent type create form as a replica of the detail (wave 3 of the "no edit
 * pages" plan): the same rows, closed until clicked (Fatto/Ripristina,
 * nothing saved per row); Salva validates and creates everything at once.
 */
export function ReferentTypeFormBody({ onSuccess, onCancel }: ReferentTypeFormBodyProps) {
  const { t } = useTranslation()
  const { form, serverError, onSubmit } = useReferentTypeForm({ mode: CREATE_MODE, onSuccess })
  const draft = useDraftInlineEdit(form)
  const [name, customFields] = useWatch({ control: form.control, name: ['name', 'custom_fields'] })
  const heading = name.trim() || t('referentTypes.form.createTitle')

  return (
    <Form {...form}>
      <RecordCreateLayout
        formId={REFERENT_TYPE_FORM_ID}
        onSubmit={form.handleSubmit(onSubmit)}
        title={heading}
        subtitle={t('referentTypes.form.createSubtitle')}
        media={<DetailMonogram name={heading} icon={<Tag />} />}
        isSubmitting={form.formState.isSubmitting}
        serverError={serverError}
        onCancel={onCancel}
        labels={{
          save: t('referentTypes.form.save'),
          saving: t('referentTypes.form.saving'),
          cancel: t('referentTypes.form.cancel'),
        }}
      >
        <ReferentTypeSections form={form} inline={draft} canEdit customFieldValues={customFields} />
      </RecordCreateLayout>
    </Form>
  )
}
