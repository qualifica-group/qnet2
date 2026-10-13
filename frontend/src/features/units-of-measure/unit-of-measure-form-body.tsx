import { useTranslation } from 'react-i18next'
import { useWatch } from 'react-hook-form'
import { Ruler } from 'lucide-react'
import { DetailMonogram } from '@/components/detail/detail-panel'
import { RecordFieldList, RecordSection, RecordSectionsGrid } from '@/components/detail/record-panel'
import { RecordCreateLayout } from '@/components/record-form/record-create-layout'
import { useDraftInlineEdit } from '@/components/record-form/use-draft-inline-edit'
import { Form } from '@/components/ui/form'
import { UnitOfMeasureRows } from '@/features/units-of-measure/unit-of-measure-rows'
import { useUnitOfMeasureForm } from '@/features/units-of-measure/use-unit-of-measure-form'
import type { UnitOfMeasureDetail } from '@/features/units-of-measure/types'

/** DOM id bridging the header's and the footer's save actions to the RHF `<form>`. */
const UNIT_OF_MEASURE_FORM_ID = 'unit-of-measure-form'

/** Create mode is static: a fresh object per render would rebuild the form's defaults. */
const CREATE_MODE = { type: 'create' } as const

interface UnitOfMeasureFormBodyProps {
  onSuccess: (unitOfMeasure: UnitOfMeasureDetail) => void
  onCancel: () => void
}

/**
 * The unit of measure create form as a replica of the detail (wave 3 of the
 * "no edit pages" plan): the same rows, closed until clicked (Fatto/
 * Ripristina, nothing saved per row), plus the create-only `code`; Salva
 * validates and creates everything at once.
 */
export function UnitOfMeasureFormBody({ onSuccess, onCancel }: UnitOfMeasureFormBodyProps) {
  const { t } = useTranslation()
  const { form, serverError, onSubmit } = useUnitOfMeasureForm({ mode: CREATE_MODE, onSuccess })
  const draft = useDraftInlineEdit(form)
  const name = useWatch({ control: form.control, name: 'name' })
  const heading = name.trim() || t('unitsOfMeasure.form.createTitle')

  return (
    <Form {...form}>
      <RecordCreateLayout
        formId={UNIT_OF_MEASURE_FORM_ID}
        onSubmit={form.handleSubmit(onSubmit)}
        title={heading}
        subtitle={t('unitsOfMeasure.form.createSubtitle')}
        media={<DetailMonogram name={heading} icon={<Ruler />} />}
        isSubmitting={form.formState.isSubmitting}
        serverError={serverError}
        onCancel={onCancel}
        labels={{
          save: t('unitsOfMeasure.form.save'),
          saving: t('unitsOfMeasure.form.saving'),
          cancel: t('unitsOfMeasure.form.cancel'),
        }}
      >
        <RecordSectionsGrid>
          <RecordSection title={t('unitsOfMeasure.form.sections.identity.title')} full>
            <RecordFieldList>
              <UnitOfMeasureRows inline={draft} canEdit withCode />
            </RecordFieldList>
          </RecordSection>
        </RecordSectionsGrid>
      </RecordCreateLayout>
    </Form>
  )
}
