import { CircleDollarSign } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { useWatch } from 'react-hook-form'
import { DetailMonogram } from '@/components/detail/detail-panel'
import { RecordCreateLayout } from '@/components/record-form/record-create-layout'
import { useDraftInlineEdit } from '@/components/record-form/use-draft-inline-edit'
import { Form } from '@/components/ui/form'
import { CommissionConfigurationSections } from './commission-configuration-rows'
import { useCommissionConfigurationDraftRefs } from './use-commission-configuration-draft-refs'
import { useCommissionConfigurationForm } from './use-commission-configuration-form'
import type { CommissionConfigurationDetail, CommissionConfigurationFormMode } from './types'

/** DOM id bridging the header's and the footer's save actions to the RHF `<form>`. */
const COMMISSION_CONFIGURATION_FORM_ID = 'commission-configuration-form'

interface CommissionConfigurationFormBodyProps {
  /** A create, optionally seeded with a supplier (spec 0204 D-4); stable across renders. */
  mode: Extract<CommissionConfigurationFormMode, { type: 'create' }>
  onSuccess: (configuration: CommissionConfigurationDetail) => void
  onCancel: () => void
}

/**
 * The create form as a replica of the detail (wave 4 of the "no edit pages"
 * plan): the same rows, closed until clicked (Fatto/Ripristina, nothing saved
 * per row); Salva validates and creates everything at once.
 */
export function CommissionConfigurationFormBody({ mode, onSuccess, onCancel }: CommissionConfigurationFormBodyProps) {
  const { t } = useTranslation()
  const { form, serverError, onSubmit } = useCommissionConfigurationForm({ mode, onSuccess })
  const draft = useDraftInlineEdit(form)
  const refs = useCommissionConfigurationDraftRefs(form)
  const name = useWatch({ control: form.control, name: 'name' })
  const heading = name.trim() || t('commissionConfigurations.form.new')

  return (
    <Form {...form}>
      <RecordCreateLayout
        formId={COMMISSION_CONFIGURATION_FORM_ID}
        onSubmit={form.handleSubmit(onSubmit)}
        title={heading}
        subtitle={t('commissionConfigurations.form.createSubtitle')}
        media={<DetailMonogram name={heading} icon={<CircleDollarSign />} />}
        isSubmitting={form.formState.isSubmitting}
        serverError={serverError}
        onCancel={onCancel}
        labels={{
          save: t('commissionConfigurations.form.save'),
          saving: t('commissionConfigurations.form.saving'),
          cancel: t('common.cancel'),
        }}
      >
        <CommissionConfigurationSections inline={draft} canEdit refs={refs} />
      </RecordCreateLayout>
    </Form>
  )
}
