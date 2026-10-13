import { useTranslation } from 'react-i18next'
import { useWatch } from 'react-hook-form'
import { ListTree } from 'lucide-react'
import { DetailMonogram } from '@/components/detail/detail-panel'
import { RecordCreateLayout } from '@/components/record-form/record-create-layout'
import { useDraftInlineEdit } from '@/components/record-form/use-draft-inline-edit'
import { Form } from '@/components/ui/form'
import { SectorSections } from '@/features/sectors/sector-rows'
import { useSectorForm } from '@/features/sectors/use-sector-form'
import type { SectorDetail } from '@/features/sectors/types'

/** DOM id bridging the header's and the footer's save actions to the RHF `<form>`. */
const SECTOR_FORM_ID = 'sector-form'

/** Create mode is static: a fresh object per render would rebuild the form's defaults. */
const CREATE_MODE = { type: 'create' } as const

interface SectorFormBodyProps {
  onSuccess: (sector: SectorDetail) => void
  onCancel: () => void
}

/**
 * The sector create form as a replica of the detail (wave 3 of the "no edit
 * pages" plan): the same rows, closed until clicked (Fatto/Ripristina,
 * nothing saved per row); Salva validates and creates everything at once.
 */
export function SectorFormBody({ onSuccess, onCancel }: SectorFormBodyProps) {
  const { t } = useTranslation()
  const { form, serverError, onSubmit } = useSectorForm({ mode: CREATE_MODE, onSuccess })
  const draft = useDraftInlineEdit(form)
  const [name, customFields] = useWatch({ control: form.control, name: ['name', 'custom_fields'] })
  const heading = name.trim() || t('sectors.form.createTitle')

  return (
    <Form {...form}>
      <RecordCreateLayout
        formId={SECTOR_FORM_ID}
        onSubmit={form.handleSubmit(onSubmit)}
        title={heading}
        subtitle={t('sectors.form.createSubtitle')}
        media={<DetailMonogram name={heading} icon={<ListTree />} />}
        isSubmitting={form.formState.isSubmitting}
        serverError={serverError}
        onCancel={onCancel}
        labels={{
          save: t('sectors.form.save'),
          saving: t('sectors.form.saving'),
          cancel: t('sectors.form.cancel'),
        }}
      >
        <SectorSections form={form} inline={draft} canEdit customFieldValues={customFields} />
      </RecordCreateLayout>
    </Form>
  )
}
