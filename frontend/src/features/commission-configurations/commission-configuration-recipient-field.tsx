import { useTranslation } from 'react-i18next'
import type { Control } from 'react-hook-form'
import { RelationSelectField } from '@/components/form/relation-select-field'
import { RECIPIENT_RESOURCE_BY_TYPE } from './commission-recipient-resources'
import type { CommissionConfigurationFormValues } from './commission-configuration-schema'
import type { CommissionRecipientType, CommissionRelationRef } from './types'

interface RelationLabels {
  placeholder: string
  emptyLabel: string
  errorLabel: string
  clearLabel: string
  retryLabel: string
}

interface Props {
  control: Control<CommissionConfigurationFormValues>
  type: CommissionRecipientType
  selected: CommissionRelationRef | null
  labels: RelationLabels
}

/**
 * Destinatario picker (spec 0090 D-4): the entity searched by `recipient_id`
 * depends on the currently selected `recipient_type`, itself chosen by the
 * operator among the role's allow-list. The parent form resets `recipient_id`
 * whenever the type changes (AC-019) — this component only renders the
 * picker bound to whatever resource that type implies.
 */
export function CommissionConfigurationRecipientField({ control, type, selected, labels }: Props) {
  const { t } = useTranslation()
  return (
    <RelationSelectField
      control={control}
      name="recipient_id"
      metaKey="recipient_id"
      label={t('commissionConfigurations.form.recipient_id')}
      resource={RECIPIENT_RESOURCE_BY_TYPE[type]}
      searchPlaceholder={t('commissionConfigurations.form.searchRecipient')}
      selected={selected}
      {...labels}
    />
  )
}
