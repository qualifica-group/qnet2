import { useTranslation } from 'react-i18next'
import { useWatch } from 'react-hook-form'
import { Tags } from 'lucide-react'
import { DetailMonogram } from '@/components/detail/detail-panel'
import { RecordFieldList, RecordSection, RecordSectionsGrid } from '@/components/detail/record-panel'
import { RecordCreateLayout } from '@/components/record-form/record-create-layout'
import { useDraftInlineEdit } from '@/components/record-form/use-draft-inline-edit'
import { Form } from '@/components/ui/form'
import { CostTypeRows } from '@/features/cost-types/cost-type-rows'
import { useCostTypeForm } from '@/features/cost-types/use-cost-type-form'
import type { CostTypeDetail } from '@/features/cost-types/types'

/** DOM id bridging the header's and the footer's save actions to the RHF `<form>`. */
const COST_TYPE_FORM_ID = 'cost-type-form'

/** Create mode is static: a fresh object per render would rebuild the form's defaults. */
const CREATE_MODE = { type: 'create' } as const

interface CostTypeFormBodyProps {
  onSuccess: (costType: CostTypeDetail) => void
  onCancel: () => void
}

/**
 * The cost type create form as a replica of the detail: the same rows, closed
 * until clicked (nothing saved per row); Save validates and creates everything
 * at once. There is no order field: new rows go to the end of the list.
 */
export function CostTypeFormBody({ onSuccess, onCancel }: CostTypeFormBodyProps) {
  const { t } = useTranslation()
  const { form, serverError, onSubmit } = useCostTypeForm({ mode: CREATE_MODE, onSuccess })
  const draft = useDraftInlineEdit(form)
  const name = useWatch({ control: form.control, name: 'name' })
  const heading = name.trim() || t('costTypes.form.createTitle')

  return (
    <Form {...form}>
      <RecordCreateLayout
        formId={COST_TYPE_FORM_ID}
        onSubmit={form.handleSubmit(onSubmit)}
        title={heading}
        subtitle={t('costTypes.form.createSubtitle')}
        media={<DetailMonogram name={heading} icon={<Tags />} />}
        isSubmitting={form.formState.isSubmitting}
        serverError={serverError}
        onCancel={onCancel}
        labels={{
          save: t('costTypes.form.save'),
          saving: t('costTypes.form.saving'),
          cancel: t('costTypes.form.cancel'),
        }}
      >
        <RecordSectionsGrid>
          <RecordSection title={t('costTypes.form.sections.identity.title')} full>
            <RecordFieldList>
              <CostTypeRows inline={draft} canEdit />
            </RecordFieldList>
          </RecordSection>
        </RecordSectionsGrid>
      </RecordCreateLayout>
    </Form>
  )
}
