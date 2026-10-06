import { useTranslation } from 'react-i18next'
import type { Control } from 'react-hook-form'
import { FormControl } from '@/components/ui/form'
import { Input } from '@/components/ui/input'
import { Switch } from '@/components/ui/switch'
import { Textarea } from '@/components/ui/textarea'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { MetaField } from '@/features/authorization/MetaField'
import { useEnumOptions } from '@/features/config/use-config'
import type { RegistryFormValues } from '@/features/registries/use-registry-form'

/*
 * The anagrafica's "Dati commerciali" fields, one component each, shared by
 * the detail's in-place rows and the create draft's rows (spec 0200).
 */

interface FieldProps {
  control: Control<RegistryFormValues>
}

/** Formats a raw numeric field's RHF value for a controlled `<input type="number">`. */
function numberInputValue(value: number | null): string {
  return value === null ? '' : String(value)
}

export function RegistryVatGroupField({ control }: FieldProps) {
  const { t } = useTranslation()
  return (
    <MetaField control={control} name="vat_group" metaKey="vat_group" label={t('registries.form.vatGroup')}>
      {({ field, disabled, readOnly }) => (
        <FormControl>
          <Input autoComplete="off" disabled={disabled} readOnly={readOnly} {...field} />
        </FormControl>
      )}
    </MetaField>
  )
}

interface SwitchFieldProps extends FieldProps {
  name: 'is_supplier' | 'is_qualified_supplier'
  label: string
}

/** "Fornitore" / "Fornitore qualificato": the latter only meaningful while the former holds. */
export function RegistrySwitchField({ control, name, label }: SwitchFieldProps) {
  return (
    <MetaField control={control} name={name} metaKey={name} label={label}>
      {({ field, disabled }) => (
        <FormControl>
          <Switch checked={field.value} onCheckedChange={field.onChange} disabled={disabled} />
        </FormControl>
      )}
    </MetaField>
  )
}

interface EnumFieldProps extends FieldProps {
  name: 'agreement_status' | 'size_class'
  /** The config enum the options come from. */
  enumKey: 'agreement_status' | 'size_class'
  label: string
  placeholder: string
}

/** "Stato convenzione" / "Classe dimensionale": a config enum as a select. */
export function RegistryEnumField({ control, name, enumKey, label, placeholder }: EnumFieldProps) {
  const options = useEnumOptions(enumKey)
  return (
    <MetaField control={control} name={name} metaKey={name} label={label}>
      {({ field, disabled }) => (
        <Select value={field.value ?? undefined} onValueChange={field.onChange} disabled={disabled}>
          <FormControl>
            <SelectTrigger className="w-full">
              <SelectValue placeholder={placeholder} />
            </SelectTrigger>
          </FormControl>
          <SelectContent>
            {options.map((option) => (
              <SelectItem key={option.value} value={option.value}>
                {option.label}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
      )}
    </MetaField>
  )
}

export function RegistryAgreementNotesField({ control }: FieldProps) {
  const { t } = useTranslation()
  return (
    <MetaField
      control={control}
      name="agreement_notes"
      metaKey="agreement_notes"
      label={t('registries.form.agreementNotes')}
    >
      {({ field, disabled, readOnly }) => (
        <FormControl>
          <Textarea disabled={disabled} readOnly={readOnly} {...field} />
        </FormControl>
      )}
    </MetaField>
  )
}

export function RegistryEmployeeCountField({ control }: FieldProps) {
  const { t } = useTranslation()
  return (
    <MetaField
      control={control}
      name="employee_count"
      metaKey="employee_count"
      label={t('registries.form.employeeCount')}
    >
      {({ field, disabled, readOnly }) => (
        <FormControl>
          <Input
            type="number"
            min={0}
            step="1"
            disabled={disabled}
            readOnly={readOnly}
            value={numberInputValue(field.value)}
            onChange={(event) => field.onChange(event.target.value === '' ? null : Number(event.target.value))}
            onBlur={field.onBlur}
            name={field.name}
            ref={field.ref}
          />
        </FormControl>
      )}
    </MetaField>
  )
}
