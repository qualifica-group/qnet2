import { useId, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Eye } from 'lucide-react'
import { type Control, useWatch } from 'react-hook-form'
import { FormSection } from '@/components/form-section'
import { DynamicIcon } from '@/features/custom-fields/dynamic-icon'
import { CUSTOM_FIELD_COMPONENT_REGISTRY } from '@/features/custom-fields/field-component-registry'
import { buildTableConfig } from '@/features/custom-fields/field-definition-table'
import type { FieldDefinitionFormValues } from '@/features/custom-fields/field-definition-form-values'
import {
  type CustomFieldConfig,
  type CustomFieldDescriptor,
  type CustomFieldOption,
  type CustomFieldValue,
} from '@/features/custom-fields/types'

interface DefinitionFieldPreviewProps<T extends FieldDefinitionFormValues> {
  control: Control<T>
  /** The field's display label — watched by the caller under its own identity field (`label` for custom fields, `name` for attributes). */
  label: string
  /** Whether the field is required. Custom field definitions carry their own `validation.required`; attributes have no such concept (required lives on the category pivot) and simply omit it. */
  required?: boolean
}

/** Builds the runtime `config` subset from the form's loose config bag (nulls/empties dropped). */
function buildConfig(values: Partial<FieldDefinitionFormValues>): CustomFieldConfig {
  const bag = values.config
  const config: CustomFieldConfig = {}
  if (!bag) {
    return config
  }
  if (bag.minLength != null) config.minLength = bag.minLength
  if (bag.maxLength != null) config.maxLength = bag.maxLength
  if (bag.rows != null) config.rows = bag.rows
  if (bag.min != null) config.min = bag.min
  if (bag.max != null) config.max = bag.max
  if (bag.step != null) config.step = bag.step
  if (bag.decimals != null) config.decimals = bag.decimals
  if (bag.regex) config.regex = bag.regex
  if (bag.transform) config.transform = bag.transform
  if (bag.display) config.display = bag.display as CustomFieldConfig['display']
  return config
}

/** Preview config of a `table`: only columns complete enough to render (key + label), so half-typed rows do not break the control. */
function buildTablePreviewConfig(values: Partial<FieldDefinitionFormValues>): CustomFieldConfig {
  const table = values.table
  if (!table) {
    return {}
  }
  const usable = table.columns.filter((column) => column?.key && column.label)
  return buildTableConfig({ ...table, columns: usable, selectable_enabled: table.selectable_enabled && Boolean(table.selectable_key && table.selectable_label) })
}

/** Maps the form's enum option rows to runtime options, dropping incomplete rows. */
function buildOptions(values: Partial<FieldDefinitionFormValues>): CustomFieldOption[] {
  return (values.options ?? [])
    .filter((option): option is NonNullable<typeof option> => Boolean(option?.value && option?.label))
    .map((option) => ({
      value: option.value,
      label: option.label,
      color: option.color || null,
      icon: option.icon || null,
    }))
}

/**
 * Live preview of the field being configured, rendered with the very same
 * runtime registry the host forms use — so the admin sees exactly how the field
 * will look to end users (label, icon, help text, placeholder, options) while
 * they edit. Read-only mirror: it maintains its own throwaway value state and
 * feeds nothing back to the form. Sits sticky at the top of the (narrow) sheet.
 *
 * Generic over `T` (see `DefinitionTypePicker` for why `Control<T>` cannot be
 * fixed to `Control<FieldDefinitionFormValues>`).
 */
export function DefinitionFieldPreview<T extends FieldDefinitionFormValues>({
  control,
  label,
  required,
}: DefinitionFieldPreviewProps<T>) {
  const { t } = useTranslation()
  const values = useWatch({ control }) as Partial<FieldDefinitionFormValues>
  const type = values.type ?? 'text'

  const descriptor: CustomFieldDescriptor = {
    key: 'custom.preview',
    type,
    group: null,
    mandatory: false,
    source: 'custom',
    label: label.trim() || t('customFields.form.previewFallbackLabel'),
    help_text: values.help_text?.trim() || null,
    placeholder: values.placeholder?.trim() || null,
    icon: values.icon || null,
    config: type === 'table' ? buildTablePreviewConfig(values) : buildConfig(values),
    options: buildOptions(values),
    relation:
      type === 'relation'
        ? {
            for_select_resource: values.relation_target?.for_select_resource ?? '',
            cardinality: values.relation_target?.cardinality ?? 'one',
          }
        : undefined,
  }

  return (
    <FormSection
      icon={Eye}
      title={t('customFields.form.previewTitle')}
      description={t('customFields.form.previewDescription')}
    >
      <PreviewField key={type} descriptor={descriptor} required={Boolean(required)} />
    </FormSection>
  )
}

interface PreviewFieldProps {
  descriptor: CustomFieldDescriptor
  required: boolean
}

/** Remounted per `type` (via `key`) so its throwaway value resets on type change. */
function PreviewField({ descriptor, required }: PreviewFieldProps) {
  const { t } = useTranslation()
  const [value, setValue] = useState<CustomFieldValue>(null)
  const id = useId()
  const descriptionId = `${id}-help`

  return (
    <div className="flex flex-col gap-2">
      <span className="flex items-center gap-1.5 text-sm font-medium text-foreground">
        <DynamicIcon name={descriptor.icon} className="size-3.5 text-muted-foreground" />
        {descriptor.label}
        {required ? <span className="text-destructive">*</span> : null}
      </span>

      {descriptor.type === 'relation' ? (
        <p className="rounded-md border border-dashed px-3 py-2 text-xs text-muted-foreground">
          {t('customFields.form.previewRelationHint')}
        </p>
      ) : (
        <PreviewControl
          descriptor={descriptor}
          value={value}
          onChange={setValue}
          id={id}
          describedBy={descriptionId}
        />
      )}

      {descriptor.help_text ? (
        <p id={descriptionId} className="text-xs text-muted-foreground">
          {descriptor.help_text}
        </p>
      ) : null}
    </div>
  )
}

interface PreviewControlProps {
  descriptor: CustomFieldDescriptor
  value: CustomFieldValue
  onChange: (value: CustomFieldValue) => void
  id: string
  describedBy: string
}

/** Dispatches to the runtime control for every non-relation type. */
function PreviewControl({ descriptor, value, onChange, id, describedBy }: PreviewControlProps) {
  const FieldControl = CUSTOM_FIELD_COMPONENT_REGISTRY[descriptor.type]
  return (
    <FieldControl
      descriptor={descriptor}
      value={value}
      onChange={onChange}
      disabled={false}
      readOnly={false}
      id={id}
      describedBy={describedBy}
      invalid={false}
    />
  )
}
