import type { Control } from 'react-hook-form'
import { FormControl } from '@/components/ui/form'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import { MetaField } from '@/features/authorization/MetaField'
import type { FinancialAccountFormValues } from '@/features/financial-accounts/financial-account-schema'

/** Form keys holding a plain string, the only ones a text control can edit. */
export type TextFieldName =
  | 'name'
  | 'iban'
  | 'account_number'
  | 'address_line'
  | 'postal_code'
  | 'card_holder'
  | 'card_number'
  | 'card_expiry'
  | 'notes'

interface FinancialAccountTextFieldProps {
  control: Control<FinancialAccountFormValues>
  name: TextFieldName
  label: string
  required?: boolean
  hint?: string
  placeholder?: string
  autoComplete?: string
  inputMode?: 'numeric' | 'text'
  multiline?: boolean
  className?: string
}

/**
 * Metadata-aware text control shared by every per-type field group: wraps the
 * shared `MetaField` (label, required mark, accessible error message) around
 * an `Input` or, for notes, a `Textarea`.
 */
export function FinancialAccountTextField({
  control,
  name,
  label,
  required,
  hint,
  placeholder,
  autoComplete = 'off',
  inputMode,
  multiline = false,
  className,
}: FinancialAccountTextFieldProps) {
  return (
    <MetaField
      control={control}
      name={name}
      metaKey={name}
      label={label}
      required={required}
      hint={hint}
      className={className}
    >
      {({ field, disabled, readOnly }) => (
        <FormControl>
          {multiline ? (
            <Textarea disabled={disabled} readOnly={readOnly} {...field} />
          ) : (
            <Input
              autoComplete={autoComplete}
              inputMode={inputMode}
              placeholder={placeholder}
              disabled={disabled}
              readOnly={readOnly}
              {...field}
            />
          )}
        </FormControl>
      )}
    </MetaField>
  )
}
