import { useTranslation } from 'react-i18next'
import { useWatch } from 'react-hook-form'
import { CalendarOff } from 'lucide-react'
import { DetailMonogram } from '@/components/detail/detail-panel'
import { RecordFieldList, RecordSection, RecordSectionsGrid } from '@/components/detail/record-panel'
import { RecordCreateLayout } from '@/components/record-form/record-create-layout'
import { useDraftInlineEdit } from '@/components/record-form/use-draft-inline-edit'
import { Form } from '@/components/ui/form'
import { AbsenceTypeRows } from '@/features/absence-types/absence-type-rows'
import { useAbsenceTypeForm } from '@/features/absence-types/use-absence-type-form'
import type { AbsenceType } from '@/features/absence-types/types'

/** DOM id bridging the header's and the footer's save actions to the RHF `<form>`. */
const FORM_ID = 'absence-type-form'

/** Create mode is static: a fresh object per render would rebuild the form's defaults. */
const CREATE_MODE = { type: 'create' } as const

interface AbsenceTypeFormBodyProps {
  onSuccess: (absenceType: AbsenceType) => void
  onCancel: () => void
}

/**
 * The absence type create form as a replica of the detail: the same rows, closed until
 * clicked (nothing saved per row); Save validates and creates everything at once.
 */
export function AbsenceTypeFormBody({ onSuccess, onCancel }: AbsenceTypeFormBodyProps) {
  const { t } = useTranslation()
  const { form, serverError, onSubmit } = useAbsenceTypeForm({ mode: CREATE_MODE, onSuccess })
  const draft = useDraftInlineEdit(form)
  const name = useWatch({ control: form.control, name: 'name' })
  const heading = name.trim() || t('absenceTypes.form.createTitle')

  return (
    <Form {...form}>
      <RecordCreateLayout
        formId={FORM_ID}
        onSubmit={form.handleSubmit(onSubmit)}
        title={heading}
        subtitle={t('absenceTypes.form.createSubtitle')}
        media={<DetailMonogram name={heading} icon={<CalendarOff />} />}
        isSubmitting={form.formState.isSubmitting}
        serverError={serverError}
        onCancel={onCancel}
        labels={{
          save: t('absenceTypes.form.save'),
          saving: t('absenceTypes.form.saving'),
          cancel: t('absenceTypes.form.cancel'),
        }}
      >
        <RecordSectionsGrid>
          <RecordSection title={t('absenceTypes.form.sections.identity.title')} full>
            <RecordFieldList>
              <AbsenceTypeRows inline={draft} canEdit />
            </RecordFieldList>
          </RecordSection>
        </RecordSectionsGrid>
      </RecordCreateLayout>
    </Form>
  )
}
