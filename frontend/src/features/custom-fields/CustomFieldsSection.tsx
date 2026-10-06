import { useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import { SlidersHorizontal } from 'lucide-react'
import type { Control, FieldPath } from 'react-hook-form'
import { FormSection } from '@/components/form-section'
import { useFormField } from '@/components/ui/form'
import { MetaField } from '@/features/authorization/MetaField'
import { useResourcePermissions } from '@/features/authorization/permissions'
import { useResourceMeta } from '@/features/authorization/use-resource-meta'
import { CUSTOM_FIELD_COMPONENT_REGISTRY } from '@/features/custom-fields/field-component-registry'
import { groupByLabel, sortVisibleCustomFields } from '@/features/custom-fields/custom-fields-grouping'
import {
  isCustomFieldDescriptor,
  rawKey,
  type CustomFieldDescriptor,
  type CustomFieldsFormShape,
  type CustomFieldValue,
} from '@/features/custom-fields/types'

interface CustomFieldsSectionProps<TFieldValues extends CustomFieldsFormShape> {
  /** Domain key of the host resource, e.g. `companies` (the same one whose form calls `/meta/{resource}`). */
  resource: string
  control: Control<TFieldValues>
  /**
   * Pre-loaded descriptors, bypassing the internal `useResourceMeta` fetch.
   * The host form already loads `/meta/{resource}` for its own native fields
   * via the SAME query key, so in production this stays unset and the two
   * calls dedupe through the TanStack Query cache; the prop exists for
   * tests/previews that would rather not stand up a query mock.
   */
  fields?: CustomFieldDescriptor[]
  /** Forwarded to the internal `<FormSection>` — see `components/form-section.tsx`. */
  collapsible?: boolean
  defaultOpen?: boolean
  open?: boolean
  onOpenChange?: (open: boolean) => void
}

/**
 * Renders the resource's `source:'custom'` fields (spec 0021 AC-022):
 * ordered by (tab, group, sort_order), each wrapped in `<MetaField>` so the
 * role's visible/editable/required/disabled flags apply exactly like a
 * native field. Renders nothing when the resource has no custom fields
 * (zero-code rollout: mounting this on a resource without any definition is
 * a no-op).
 */
export function CustomFieldsSection<TFieldValues extends CustomFieldsFormShape>({
  resource,
  control,
  fields: providedFields,
  collapsible,
  defaultOpen,
  open,
  onOpenChange,
}: CustomFieldsSectionProps<TFieldValues>) {
  const { t } = useTranslation()
  const { field: fieldPermission } = useResourcePermissions()
  const metaQuery = useResourceMeta(resource, providedFields === undefined)

  const customFields = useMemo(
    () =>
      sortVisibleCustomFields(
        providedFields ?? metaQuery.data?.fields.filter(isCustomFieldDescriptor) ?? [],
        fieldPermission,
      ),
    [fieldPermission, metaQuery.data, providedFields],
  )

  if (customFields.length === 0) {
    return null
  }

  return (
    <>
      {[...groupByLabel(customFields).entries()].map(([group, descriptors]) =>
        group === null ? (
          <FormSection
            key="ungrouped"
            icon={SlidersHorizontal}
            title={t('customFields.section.title')}
            collapsible={collapsible}
            defaultOpen={defaultOpen}
            open={open}
            onOpenChange={onOpenChange}
          >
            {descriptors.map((descriptor) => (
              <CustomFieldItem key={descriptor.key} control={control} descriptor={descriptor} />
            ))}
          </FormSection>
        ) : (
          <FormSection
            key={group}
            icon={SlidersHorizontal}
            title={group}
            collapsible={collapsible}
            defaultOpen={defaultOpen}
            open={open}
            onOpenChange={onOpenChange}
          >
            {descriptors.map((descriptor) => (
              <CustomFieldItem key={descriptor.key} control={control} descriptor={descriptor} />
            ))}
          </FormSection>
        ),
      )}
    </>
  )
}

interface CustomFieldItemProps<TFieldValues extends CustomFieldsFormShape> {
  control: Control<TFieldValues>
  descriptor: CustomFieldDescriptor
}

/**
 * One custom field, gated by `<MetaField>` and rendered via the type→component
 * registry. Exported for the record detail's in-place rows (spec 0200), which
 * open on this very control.
 */
export function CustomFieldItem<TFieldValues extends CustomFieldsFormShape>({
  control,
  descriptor,
}: CustomFieldItemProps<TFieldValues>) {
  const name = `custom_fields.${rawKey(descriptor.key)}` as unknown as FieldPath<TFieldValues>

  return (
    <MetaField
      control={control}
      name={name}
      metaKey={descriptor.key}
      label={descriptor.label}
      description={descriptor.help_text}
    >
      {({ field, disabled, readOnly }) => (
        <CustomFieldControlBridge
          descriptor={descriptor}
          value={field.value as CustomFieldValue}
          onChange={field.onChange as (value: CustomFieldValue) => void}
          disabled={disabled}
          readOnly={readOnly}
        />
      )}
    </MetaField>
  )
}

interface CustomFieldControlBridgeProps {
  descriptor: CustomFieldDescriptor
  value: CustomFieldValue
  onChange: (value: CustomFieldValue) => void
  disabled: boolean
  readOnly: boolean
}

/**
 * Resolves the accessible-error triad (frontend.md §10) via `useFormField()`
 * — called here, one level INSIDE `<MetaField>`'s render prop, because
 * `<FormControl>`'s automatic `Slot` id injection only reaches its immediate
 * JSX child, one level too shallow for a registry-dispatched control (see
 * `CustomFieldControlProps`).
 */
function CustomFieldControlBridge({
  descriptor,
  value,
  onChange,
  disabled,
  readOnly,
}: CustomFieldControlBridgeProps) {
  const { formItemId, formDescriptionId, formMessageId, error } = useFormField()
  const FieldControl = CUSTOM_FIELD_COMPONENT_REGISTRY[descriptor.type]

  return (
    <FieldControl
      descriptor={descriptor}
      value={value}
      onChange={onChange}
      disabled={disabled}
      readOnly={readOnly}
      id={formItemId}
      describedBy={error ? `${formDescriptionId} ${formMessageId}` : formDescriptionId}
      invalid={Boolean(error)}
    />
  )
}
