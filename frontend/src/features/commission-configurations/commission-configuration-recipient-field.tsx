import { useTranslation } from 'react-i18next'
import type { Control } from 'react-hook-form'
import { RelationSelectField } from '@/components/form/relation-select-field'
import { REFERENTS_FOR_SELECT_RESOURCE } from '@/features/referents/for-select-api'
import { REGISTRIES_FOR_SELECT_RESOURCE } from '@/features/registries/for-select-api'
import { USERS_FOR_SELECT_RESOURCE } from '@/features/users/for-select-api'
import type { CommissionConfigurationFormValues } from './commission-configuration-schema'
import type { CommissionRecipientType, CommissionRelationRef } from './types'

/**
 * Recipient-picker resource per `recipient_type` (spec 0090 D-4, emenda 0089
 * D-8): a referent, a user or a registry, whichever the operator picked
 * (or the role's derived default). Kept in sync manually since the enum
 * lives server-side; the three types are exhaustive
 * (`COMMISSION_RECIPIENT_TYPES`).
 */
const RECIPIENT_RESOURCE_BY_TYPE: Record<CommissionRecipientType, string> = {
  referent: REFERENTS_FOR_SELECT_RESOURCE,
  user: USERS_FOR_SELECT_RESOURCE,
  registry: REGISTRIES_FOR_SELECT_RESOURCE,
}

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
