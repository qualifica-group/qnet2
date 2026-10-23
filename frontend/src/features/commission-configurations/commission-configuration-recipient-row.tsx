import { useFormContext, useWatch } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { DetailEmpty } from '@/components/detail/detail-panel'
import { RecordLink } from '@/components/detail/record-link'
import { MetaFieldRowContext } from '@/features/authorization/meta-field-row-context'
import { RecordInlineField, type InlineEdit } from '@/components/record-form/record-inline-field'
import { OptionSelect, SecondaryFieldError } from './commission-configuration-controls'
import { CommissionConfigurationRecipientField } from './commission-configuration-recipient-field'
import type { CommissionConfigurationFormValues } from './commission-configuration-schema'
import { useRelationLabels } from './use-relation-labels'
import {
  COMMISSION_ROLE_ALLOWED_RECIPIENT_TYPES,
  COMMISSION_ROLES,
  SUPPLIER_COMMISSION_DIRECTIONS,
  type CommissionRelationRef,
  type CommissionRecipientType,
  type CommissionRole,
} from './types'

/**
 * Record module of each recipient type. A `user` recipient has no entry: people
 * are not linked as records (see `RecordLink`), so it stays plain text.
 */
const RECIPIENT_DOMAINS: Partial<Record<CommissionRecipientType, string>> = {
  referent: 'referents',
  registry: 'registries',
}

interface CommissionConfigurationRecipientRowProps {
  inline: InlineEdit
  canEdit: boolean
  recipient: CommissionRelationRef | null
}

/**
 * Role, recipient type and recipient are ONE row: the server validates them
 * together (a role the persisted recipient type no longer fits is refused
 * without a valid recipient, spec 0090 D-9), so they save together.
 */
export function CommissionConfigurationRecipientRow({
  inline,
  canEdit,
  recipient,
}: CommissionConfigurationRecipientRowProps) {
  const { t } = useTranslation()
  const { control, getValues, setValue } = useFormContext<CommissionConfigurationFormValues>()
  const [role, recipientType] = useWatch({ control, name: ['recipient_role', 'recipient_type'] })
  const relationLabels = useRelationLabels()
  // The role's admitted recipient identities (spec 0090 D-4): a single entry
  // (SUPPLIER -> registry) hides the type selector entirely (nothing to choose).
  const allowedRecipientTypes = COMMISSION_ROLE_ALLOWED_RECIPIENT_TYPES[role]
  const recipientDomain = RECIPIENT_DOMAINS[recipientType]

  // AC-019: reset the recipient ONLY when the current type is no longer
  // admitted by the new role: a still-valid pick (a referent kept across
  // COMMERCIAL -> SUPERVISOR, spec 0090 D-9) survives the role change.
  const handleRoleChange = (next: string) => {
    // The direction belongs to SUPPLIER rules only (spec 0306 D-1).
    if (next !== 'SUPPLIER') {
      setValue('supplier_commission_direction', null, { shouldDirty: true, shouldValidate: true })
    }
    const allowedForNextRole = COMMISSION_ROLE_ALLOWED_RECIPIENT_TYPES[next as CommissionRole]
    if (!allowedForNextRole.includes(getValues('recipient_type'))) {
      setValue('recipient_type', allowedForNextRole[0], { shouldDirty: true, shouldValidate: true })
      setValue('recipient_id', null, { shouldDirty: true, shouldValidate: true })
    }
  }

  return (
    <RecordInlineField
      field="recipient_role"
      label={t('commissionConfigurations.detail.recipientRow')}
      inline={inline}
      canEdit={canEdit}
      editor={
        // The row's label names the group: each sub-field keeps its own visible label.
        <MetaFieldRowContext.Provider value={null}>
          <div className="grid gap-3 sm:grid-cols-2">
            <OptionSelect name="recipient_role" options={COMMISSION_ROLES} onValueChange={handleRoleChange} />
            {allowedRecipientTypes.length > 1 ? (
              <OptionSelect
                name="recipient_type"
                options={allowedRecipientTypes}
                metaKey="recipient_id"
                onValueChange={() => setValue('recipient_id', null, { shouldDirty: true, shouldValidate: true })}
              />
            ) : null}
            {role === 'SUPPLIER' ? (
              <OptionSelect
                name="supplier_commission_direction"
                options={SUPPLIER_COMMISSION_DIRECTIONS}
                hint={t('commissionConfigurations.form.hints.supplierCommissionDirection')}
              />
            ) : null}
            <CommissionConfigurationRecipientField
              control={control}
              type={recipientType}
              selected={recipient}
              labels={relationLabels}
            />
          </div>
        </MetaFieldRowContext.Provider>
      }
    >
      <div className="flex flex-col gap-0.5">
        <span>{t(`commissionConfigurations.options.recipient_role.${role}`)}</span>
        {recipient ? (
          <span className="text-muted-foreground">
            {t(`commissionConfigurations.options.recipient_type.${recipientType}`)}:{' '}
            {recipientDomain ? (
              <RecordLink domain={recipientDomain} id={recipient.id}>
                {recipient.name}
              </RecordLink>
            ) : (
              recipient.name
            )}
          </span>
        ) : (
          <span className="text-muted-foreground">
            <DetailEmpty />
          </span>
        )}
      </div>
      <SecondaryFieldError name="recipient_type" />
      <SecondaryFieldError name="recipient_id" />
    </RecordInlineField>
  )
}
