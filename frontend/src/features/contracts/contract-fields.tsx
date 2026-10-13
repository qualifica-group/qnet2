import { useTranslation } from 'react-i18next'
import type { Control } from 'react-hook-form'
import { GENERAL_NOTES_TEXTAREA_CLASS } from '@/components/record-form/layout'
import { FormControl } from '@/components/ui/form'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import { MetaField } from '@/features/authorization/MetaField'
import type { EditContractFormValues } from '@/features/contracts/contract-schema'

/*
 * The contract's own editable fields (the PATCH subset, spec 0072
 * data_contract), one component each, opened by the detail's in-place rows
 * (spec 0219).
 */

interface FieldProps {
  control: Control<EditContractFormValues>
}

/** "Data di rinnovo" / "Data di scadenza": a calendar date, empty = none. */
export function ContractDateField({ control, name, label }: FieldProps & { name: 'renewal_date' | 'expiry_date'; label: string }) {
  return (
    <MetaField control={control} name={name} metaKey={name} label={label}>
      {({ field, disabled, readOnly }) => (
        <FormControl>
          <Input
            type="date"
            disabled={disabled}
            readOnly={readOnly}
            value={field.value ?? ''}
            onChange={(event) => field.onChange(event.target.value || null)}
            onBlur={field.onBlur}
            name={field.name}
            ref={field.ref}
          />
        </FormControl>
      )}
    </MetaField>
  )
}

/** "Note di pagamento". */
export function ContractPaymentNotesField({ control }: FieldProps) {
  const { t } = useTranslation()
  return (
    <MetaField control={control} name="payment_notes" metaKey="payment_notes" label={t('contracts.detail.paymentNotes')}>
      {({ field, disabled, readOnly }) => (
        <FormControl>
          <Textarea
            disabled={disabled}
            readOnly={readOnly}
            rows={3}
            value={field.value ?? ''}
            onChange={(event) => field.onChange(event.target.value || null)}
            onBlur={field.onBlur}
            name={field.name}
            ref={field.ref}
          />
        </FormControl>
      )}
    </MetaField>
  )
}

/** "Commenti": written inside their callout (`NotesCalloutRow`). */
export function ContractCommentsField({ control }: FieldProps) {
  const { t } = useTranslation()
  return (
    <MetaField control={control} name="comments" metaKey="comments" label={t('contracts.detail.comments')}>
      {({ field, disabled, readOnly }) => (
        <FormControl>
          <Textarea
            className={GENERAL_NOTES_TEXTAREA_CLASS}
            rows={4}
            placeholder={t('contracts.detail.commentsPlaceholder')}
            disabled={disabled}
            readOnly={readOnly}
            value={field.value ?? ''}
            onChange={(event) => field.onChange(event.target.value || null)}
            onBlur={field.onBlur}
            name={field.name}
            ref={field.ref}
          />
        </FormControl>
      )}
    </MetaField>
  )
}
