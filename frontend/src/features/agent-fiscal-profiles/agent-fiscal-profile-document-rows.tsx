import { useFormContext } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { DetailEmpty } from '@/components/detail/detail-panel'
import { RelationSelectField } from '@/components/form/relation-select-field'
import { RecordInlineField, type InlineEdit } from '@/components/record-form/record-inline-field'
import { COST_TYPES_FOR_SELECT_RESOURCE } from '@/features/cost-types/for-select-api'
import { PAYMENT_METHODS_FOR_SELECT_RESOURCE } from '@/features/payment-methods/for-select-api'
import type {
  AgentFiscalProfileFormValues,
  AgentFiscalProfileSchemaOutput,
} from '@/features/agent-fiscal-profiles/agent-fiscal-profile-schema'
import type { AgentFiscalProfileRefs } from '@/features/agent-fiscal-profiles/types'

interface RowProps {
  inline: InlineEdit
  canEdit: boolean
  refs: AgentFiscalProfileRefs
}

/** "Metodo di pagamento": default of the agent statement approval window (spec 0267 D-8). */
export function PaymentMethodRow({ inline, canEdit, refs }: RowProps) {
  const { t } = useTranslation()
  const { control } = useFormContext<AgentFiscalProfileFormValues, unknown, AgentFiscalProfileSchemaOutput>()

  return (
    <RecordInlineField
      field="payment_method_id"
      label={t('agentFiscalProfiles.form.paymentMethod')}
      inline={inline}
      canEdit={canEdit}
      editor={
        <RelationSelectField
          control={control}
          name="payment_method_id"
          metaKey="payment_method_id"
          label={t('agentFiscalProfiles.form.paymentMethod')}
          resource={PAYMENT_METHODS_FOR_SELECT_RESOURCE}
          searchPlaceholder={t('agentFiscalProfiles.form.paymentMethodSearch')}
          selected={refs.paymentMethod}
          placeholder={t('agentFiscalProfiles.form.paymentMethodPlaceholder')}
          emptyLabel={t('agentFiscalProfiles.form.paymentMethodEmpty')}
          errorLabel={t('agentFiscalProfiles.form.paymentMethodError')}
          clearLabel={t('common.clear')}
          retryLabel={t('common.retry')}
        />
      }
    >
      {refs.paymentMethod ? refs.paymentMethod.name : <DetailEmpty />}
    </RecordInlineField>
  )
}

/** "Tipo di costo": default of the agent statement approval window (spec 0267 D-8). */
export function CostTypeRow({ inline, canEdit, refs }: RowProps) {
  const { t } = useTranslation()
  const { control } = useFormContext<AgentFiscalProfileFormValues, unknown, AgentFiscalProfileSchemaOutput>()

  return (
    <RecordInlineField
      field="cost_type_id"
      label={t('agentFiscalProfiles.form.costType')}
      inline={inline}
      canEdit={canEdit}
      editor={
        <RelationSelectField
          control={control}
          name="cost_type_id"
          metaKey="cost_type_id"
          label={t('agentFiscalProfiles.form.costType')}
          resource={COST_TYPES_FOR_SELECT_RESOURCE}
          searchPlaceholder={t('agentFiscalProfiles.form.costTypeSearch')}
          selected={refs.costType}
          placeholder={t('agentFiscalProfiles.form.costTypePlaceholder')}
          emptyLabel={t('agentFiscalProfiles.form.costTypeEmpty')}
          errorLabel={t('agentFiscalProfiles.form.costTypeError')}
          clearLabel={t('common.clear')}
          retryLabel={t('common.retry')}
        />
      }
    >
      {refs.costType ? refs.costType.name : <DetailEmpty />}
    </RecordInlineField>
  )
}
