import { useTranslation } from 'react-i18next'
import { SlidersHorizontal } from 'lucide-react'
import { DetailMonogram } from '@/components/detail/detail-panel'
import { RecordSectionsGrid } from '@/components/detail/record-panel'
import { RecordCreateLayout } from '@/components/record-form/record-create-layout'
import { useDraftInlineEdit } from '@/components/record-form/use-draft-inline-edit'
import { Form } from '@/components/ui/form'
import { AttributeSections } from '@/features/attributes/attribute-sections'
import { useAttributeForm } from '@/features/attributes/use-attribute-form'
import type { AttributeDetail, AttributeFormMode } from '@/features/attributes/types'

/** DOM id bridging the header's and the footer's save actions to the RHF `<form>`. */
const ATTRIBUTE_FORM_ID = 'attribute-form'

interface AttributeFormBodyProps {
  mode: Exclude<AttributeFormMode, { type: 'edit' }>
  onSuccess: (attribute: AttributeDetail) => void
  onCancel: () => void
}

/**
 * The attribute create form as a replica of the detail (spec 0240): the
 * record card with its identity band (the title follows the name being typed),
 * the rows closed until clicked (Fatto/Ripristina, nothing saved per row) and
 * the type-dependent editors OPEN, since they are editors rather than fields.
 * No `validation` sub-form: an attribute's `required` lives on the category
 * pivot (`attribute_category.is_required`). All non-render logic lives in
 * `useAttributeForm`.
 */
export function AttributeFormBody({ mode, onSuccess, onCancel }: AttributeFormBodyProps) {
  const { t } = useTranslation()
  const { form, serverError, onSubmit } = useAttributeForm({ mode, onSuccess })
  const draft = useDraftInlineEdit(form)
  const heading = form.watch('name').trim() || t('attributes.form.createTitle')

  return (
    <Form {...form}>
      <RecordCreateLayout
        formId={ATTRIBUTE_FORM_ID}
        onSubmit={form.handleSubmit(onSubmit)}
        title={heading}
        subtitle={t('attributes.form.createSubtitle')}
        media={<DetailMonogram name={heading} icon={<SlidersHorizontal />} />}
        isSubmitting={form.formState.isSubmitting}
        serverError={serverError}
        onCancel={onCancel}
        labels={{
          save: t('attributes.form.save'),
          saving: t('attributes.form.saving'),
          cancel: t('attributes.form.cancel'),
        }}
      >
        <RecordSectionsGrid>
          <AttributeSections form={form} inline={draft} creating canEdit />
        </RecordSectionsGrid>
      </RecordCreateLayout>
    </Form>
  )
}
