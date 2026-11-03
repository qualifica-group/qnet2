import { useFormContext, type FieldValues, type Path } from 'react-hook-form'
import { FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form'
import { Input } from '@/components/ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Switch } from '@/components/ui/switch'
import { Textarea } from '@/components/ui/textarea'

interface EntryTextFieldProps<T extends FieldValues> {
  name: Path<T>
  label: string
  multiline?: boolean
  required?: boolean
}

/** Text / textarea field wired to RHF with the accessible error triad (FormControl + FormMessage role=alert). */
export function EntryTextField<T extends FieldValues>({ name, label, multiline, required }: EntryTextFieldProps<T>) {
  const { control } = useFormContext<T>()

  return (
    <FormField
      control={control}
      name={name}
      render={({ field }) => (
        <FormItem>
          <FormLabel required={required}>{label}</FormLabel>
          <FormControl>
            {multiline ? (
              <Textarea {...field} value={field.value ?? ''} rows={3} />
            ) : (
              <Input {...field} value={field.value ?? ''} autoComplete="off" />
            )}
          </FormControl>
          <FormMessage />
        </FormItem>
      )}
    />
  )
}

export function EntrySwitchField<T extends FieldValues>({ name, label }: { name: Path<T>; label: string }) {
  const { control } = useFormContext<T>()

  return (
    <FormField
      control={control}
      name={name}
      render={({ field }) => (
        <FormItem className="flex-row items-center justify-between gap-3">
          <FormLabel>{label}</FormLabel>
          <FormControl>
            <Switch checked={field.value === true} onCheckedChange={field.onChange} />
          </FormControl>
        </FormItem>
      )}
    />
  )
}

export interface SelectOption {
  value: string
  label: string
}

interface EntrySelectFieldProps<T extends FieldValues> {
  name: Path<T>
  label: string
  options: SelectOption[]
  /** Maps the chosen option back to the form value (numbers for ids, raw string for enums). */
  parse: (value: string) => unknown
  required?: boolean
  disabled?: boolean
  placeholder?: string
}

export function EntrySelectField<T extends FieldValues>({
  name,
  label,
  options,
  parse,
  required,
  disabled,
  placeholder,
}: EntrySelectFieldProps<T>) {
  const { control } = useFormContext<T>()

  return (
    <FormField
      control={control}
      name={name}
      render={({ field }) => (
        <FormItem>
          <FormLabel required={required}>{label}</FormLabel>
          <Select
            value={field.value === undefined || field.value === null ? '' : String(field.value)}
            onValueChange={(next) => field.onChange(parse(next))}
            disabled={disabled}
          >
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
          <FormMessage />
        </FormItem>
      )}
    />
  )
}
