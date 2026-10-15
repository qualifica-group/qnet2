import type { Control, FieldPath, FieldValues } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { RelationSelectField, type RelationFieldRef } from '@/components/form/relation-select-field'
import { FormControl } from '@/components/ui/form'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import { MetaField } from '@/features/authorization/MetaField'

/**
 * Editors of a record row edited in place (invoice records, specs 0261/0262):
 * the control alone, authorization-aware through `MetaField`, its label kept
 * for assistive tech by the row (`RecordInlineField`).
 */

interface RecordTextEditorProps<TValues extends FieldValues> {
  control: Control<TValues>
  name: FieldPath<TValues>
  label: string
  type?: 'text' | 'date'
  multiline?: boolean
  required?: boolean
}

export function RecordTextEditor<TValues extends FieldValues>({
  control,
  name,
  label,
  type = 'text',
  multiline = false,
  required,
}: RecordTextEditorProps<TValues>) {
  return (
    <MetaField control={control} name={name} metaKey={name} label={label} required={required}>
      {({ field, disabled, readOnly }) => (
        <FormControl>
          {multiline ? (
            <Textarea className="min-h-16 text-sm" disabled={disabled} readOnly={readOnly} {...field} value={field.value ?? ''} />
          ) : (
            <Input type={type} className="h-8 text-sm" disabled={disabled} readOnly={readOnly} {...field} value={field.value ?? ''} />
          )}
        </FormControl>
      )}
    </MetaField>
  )
}

type RelationProps = Parameters<typeof RelationSelectField>[0]

interface RecordRelationEditorProps<TValues extends FieldValues> {
  control: Control<TValues>
  name: RelationProps['name']
  label: string
  resource: string
  selected: RelationFieldRef | null
  placeholder: string
  params?: Record<string, string | number>
  required?: boolean
}

export function RecordRelationEditor<TValues extends FieldValues>({ control, name, ...props }: RecordRelationEditorProps<TValues>) {
  const { t } = useTranslation()
  return (
    <RelationSelectField
      {...props}
      control={control as unknown as RelationProps['control']}
      name={name}
      metaKey={name}
      searchPlaceholder={t('invoiceEditor.fields.searchPlaceholder')}
      emptyLabel={t('invoiceEditor.fields.selectEmpty')}
      errorLabel={t('invoiceEditor.fields.selectError')}
      clearLabel={t('common.clear')}
      retryLabel={t('common.retry')}
    />
  )
}
