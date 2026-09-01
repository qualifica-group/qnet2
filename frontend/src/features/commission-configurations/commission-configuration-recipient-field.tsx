import { useTranslation } from 'react-i18next'
import type { Control } from 'react-hook-form'
import { RelationSelectField } from '@/components/form/relation-select-field'
import { REFERENTS_FOR_SELECT_RESOURCE } from '@/features/referents/for-select-api'
import { REGISTRIES_FOR_SELECT_RESOURCE } from '@/features/registries/for-select-api'
import { USERS_FOR_SELECT_RESOURCE } from '@/features/users/for-select-api'
import type { CommissionConfigurationFormValues } from './commission-configuration-schema'
import type { CommissionRelationRef, CommissionRole } from './types'

/**
 * Recipient-picker resource per `recipient_role` (spec 0089 D-8): mirrors the
 * backend's `CommissionRecipientRole::recipientType()` map — Commerciale and
 * Segnalatore point at a referent, Supervisore at a user, Fornitore at a
 * registry. Kept in sync manually since the enum lives server-side; the four
 * roles are exhaustive (`COMMISSION_ROLES`).
 */
const RECIPIENT_RESOURCE_BY_ROLE: Record<CommissionRole, string> = {
  COMMERCIAL: REFERENTS_FOR_SELECT_RESOURCE,
  REPORTER: REFERENTS_FOR_SELECT_RESOURCE,
  SUPERVISOR: USERS_FOR_SELECT_RESOURCE,
  SUPPLIER: REGISTRIES_FOR_SELECT_RESOURCE,
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
  role: CommissionRole
  selected: CommissionRelationRef | null
  labels: RelationLabels
}

/**
 * Destinatario picker (spec 0089): the entity searched by `recipient_id`
 * depends on the currently selected `recipient_role`. The parent form resets
 * `recipient_id` whenever the role changes (AC-016) — this component only
 * renders the picker bound to whatever resource that role implies.
 */
export function CommissionConfigurationRecipientField({ control, role, selected, labels }: Props) {
  const { t } = useTranslation()
  return (
    <RelationSelectField
      control={control}
      name="recipient_id"
      metaKey="recipient_id"
      label={t('commissionConfigurations.form.recipient_id')}
      resource={RECIPIENT_RESOURCE_BY_ROLE[role]}
      searchPlaceholder={t('commissionConfigurations.form.searchRecipient')}
      selected={selected}
      {...labels}
    />
  )
}
