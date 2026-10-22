import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useWatch } from 'react-hook-form'
import { CalendarX } from 'lucide-react'
import { DetailMonogram } from '@/components/detail/detail-panel'
import { RecordSection, RecordSectionsGrid } from '@/components/detail/record-panel'
import { RecordCreateLayout } from '@/components/record-form/record-create-layout'
import { Form } from '@/components/ui/form'
import { CompanyClosureFields } from '@/features/company-closures/company-closure-fields'
import { CompanyClosureGenerationSummary } from '@/features/company-closures/company-closure-generation-summary'
import { useCompanyClosureForm } from '@/features/company-closures/use-company-closure-form'
import type { ClosureGeneration, CompanyClosure } from '@/features/company-closures/types'

/** DOM id bridging the header's and the footer's save actions to the RHF `<form>`. */
const FORM_ID = 'company-closure-form'

interface CompanyClosureFormBodyProps {
  /** The closure being edited; absent = create. */
  closure?: CompanyClosure
  /** Called when the user is done: right after saving, or after reading the generation summary. */
  onDone: (closure: CompanyClosure) => void
  onCancel: () => void
}

/**
 * The closure create/edit form. A collective leave answers with the generation summary, shown
 * before leaving the form; a started closure (not repeating yearly) is read-only with the reason.
 */
export function CompanyClosureFormBody({ closure, onDone, onCancel }: CompanyClosureFormBodyProps) {
  const { t } = useTranslation()
  const [summary, setSummary] = useState<{ closure: CompanyClosure; generation: ClosureGeneration } | null>(null)
  const { form, serverError, onSubmit } = useCompanyClosureForm({
    closure,
    onSaved: (saved) => (saved.generation ? setSummary({ closure: saved, generation: saved.generation }) : onDone(saved)),
  })
  const name = useWatch({ control: form.control, name: 'name' })
  const heading = name.trim() || t(closure ? 'companyClosures.form.editTitle' : 'companyClosures.form.createTitle')
  const locked = closure !== undefined && closure.is_started && !closure.repeats_yearly

  if (summary) {
    return <CompanyClosureGenerationSummary generation={summary.generation} onClose={() => onDone(summary.closure)} />
  }

  return (
    <Form {...form}>
      <RecordCreateLayout
        formId={FORM_ID}
        onSubmit={form.handleSubmit(onSubmit)}
        title={heading}
        subtitle={t(closure ? 'companyClosures.form.editSubtitle' : 'companyClosures.form.createSubtitle')}
        media={<DetailMonogram name={heading} icon={<CalendarX />} />}
        isSubmitting={form.formState.isSubmitting}
        serverError={locked ? t('companyClosures.form.startedLocked') : serverError}
        onCancel={onCancel}
        labels={{
          save: t('companyClosures.form.save'),
          saving: t('companyClosures.form.saving'),
          cancel: t('companyClosures.form.cancel'),
        }}
      >
        <RecordSectionsGrid>
          <RecordSection title={t('companyClosures.form.sections.details')} full>
            <CompanyClosureFields
              locked={locked}
              knownSites={closure?.operational_sites ?? []}
              knownAbsenceType={closure?.absence_type ?? null}
            />
          </RecordSection>
        </RecordSectionsGrid>
      </RecordCreateLayout>
    </Form>
  )
}
