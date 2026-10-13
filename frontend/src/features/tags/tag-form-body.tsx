import { useTranslation } from 'react-i18next'
import { useWatch } from 'react-hook-form'
import { Tag as TagIcon } from 'lucide-react'
import { DetailMonogram } from '@/components/detail/detail-panel'
import { RecordCreateLayout } from '@/components/record-form/record-create-layout'
import { useDraftInlineEdit } from '@/components/record-form/use-draft-inline-edit'
import { Form } from '@/components/ui/form'
import { TagSections } from '@/features/tags/tag-rows'
import { useTagForm } from '@/features/tags/use-tag-form'
import type { TagDetail } from '@/features/tags/types'

/** DOM id bridging the header's and the footer's save actions to the RHF `<form>`. */
const TAG_FORM_ID = 'tag-form'

/** Create mode is static: a fresh object per render would rebuild the form's defaults. */
const CREATE_MODE = { type: 'create' } as const

interface TagFormBodyProps {
  onSuccess: (tag: TagDetail) => void
  onCancel: () => void
}

/**
 * The tag create form as a replica of the detail (wave 3 of the "no edit
 * pages" plan): the same rows, closed until clicked (Fatto/Ripristina,
 * nothing saved per row); Salva validates and creates everything at once.
 */
export function TagFormBody({ onSuccess, onCancel }: TagFormBodyProps) {
  const { t } = useTranslation()
  const { form, serverError, onSubmit } = useTagForm({ mode: CREATE_MODE, onSuccess })
  const draft = useDraftInlineEdit(form)
  const [name, customFields] = useWatch({ control: form.control, name: ['name', 'custom_fields'] })
  const heading = name.trim() || t('tags.form.createTitle')

  return (
    <Form {...form}>
      <RecordCreateLayout
        formId={TAG_FORM_ID}
        onSubmit={form.handleSubmit(onSubmit)}
        title={heading}
        subtitle={t('tags.form.createSubtitle')}
        media={<DetailMonogram name={heading} icon={<TagIcon />} />}
        isSubmitting={form.formState.isSubmitting}
        serverError={serverError}
        onCancel={onCancel}
        labels={{
          save: t('tags.form.save'),
          saving: t('tags.form.saving'),
          cancel: t('tags.form.cancel'),
        }}
      >
        <TagSections form={form} inline={draft} canEdit customFieldValues={customFields} />
      </RecordCreateLayout>
    </Form>
  )
}
