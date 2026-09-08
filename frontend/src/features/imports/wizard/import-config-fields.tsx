import { useMemo } from 'react'
import { type Control, type FieldPath, useWatch } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form'
import { Input } from '@/components/ui/input'
import { useForSelectLabels } from '@/features/for-select/use-for-select'
import { dependencyProductCategoryIds } from '@/features/imports/wizard/import-config-dependency'
import { ImportConfigMultiSelect } from '@/features/imports/wizard/import-config-multi-select'
import { ImportConfigRelationSelect } from '@/features/imports/wizard/import-config-relation-select'
import type { ImportMappingFormValues } from '@/features/imports/wizard/import-mapping-schema'
import type { ImportGlobalFieldDescriptor } from '@/features/imports/wizard/types'

interface ImportConfigFieldsProps {
  globalFields: ImportGlobalFieldDescriptor[]
  control: Control<ImportMappingFormValues>
  /**
   * Global field ids whose value comes from a mapped file column, per row
   * (spec 0108 D-2): they are not rendered at all — there is no run-wide
   * value to pick — and whatever `depends_on` them renders disabled, with the
   * reason spelled out (AC-034).
   */
  fromFileFieldIds?: string[]
}

/**
 * The global-configuration controls (AC-021) — one per `global_fields` entry
 * of the run's definition: a multi-select for a `multiple` descriptor (spec
 * 0094 AC-050, e.g. `product_ids`), a relation select when `for_select_resource`
 * is set, a plain number input otherwise. Rendered inside the mapping step's
 * form under the `global_config.<id>` path, so the single mapping submit
 * persists these together with the column mapping and dedup strategy.
 */
export function ImportConfigFields({ globalFields, control, fromFileFieldIds = [] }: ImportConfigFieldsProps) {
  // Global-field labels arrive from the backend as default-namespace i18n keys
  // (`imports.leads.global.*`) — resolve them through the default translator.
  const { t: tLabel } = useTranslation()

  return (
    <div className="grid gap-4 sm:grid-cols-2">
      {globalFields
        .filter((globalField) => !fromFileFieldIds.includes(globalField.id))
        .map((globalField) => (
        <FormField
          key={globalField.id}
          control={control}
          name={`global_config.${globalField.id}` as FieldPath<ImportMappingFormValues>}
          render={({ field }) => (
            <FormItem>
              <FormLabel required={globalField.required}>{tLabel(globalField.label)}</FormLabel>
              {/*
                `FormControl` (Radix `Slot`) clones its `id`/`aria-describedby`/
                `aria-invalid` onto this single child (frontend.md §10's
                accessible-error triad) — `ImportConfigFieldControl` accepts and
                re-threads them onto whichever concrete control it renders.
              */}
              <FormControl>
                <ImportConfigFieldControl
                  field={globalField}
                  globalFields={globalFields}
                  control={control}
                  value={field.value as number | number[] | null}
                  onChange={field.onChange}
                  triggerLabel={tLabel(globalField.label)}
                  dependencyFromFile={
                    globalField.depends_on != null && fromFileFieldIds.includes(globalField.depends_on)
                  }
                />
              </FormControl>
              <FormMessage role="alert" />
            </FormItem>
          )}
        />
      ))}
    </div>
  )
}

/** Accessible-error triad props `Slot` injects via `FormControl` (frontend.md §10), threaded down to the leaf control. */
interface AccessibleControlProps {
  id?: string
  'aria-describedby'?: string
  'aria-invalid'?: boolean
}

interface ImportConfigFieldControlProps extends AccessibleControlProps {
  field: ImportGlobalFieldDescriptor
  globalFields: ImportGlobalFieldDescriptor[]
  control: Control<ImportMappingFormValues>
  value: number | number[] | null
  onChange: (next: number | number[] | null) => void
  triggerLabel: string
  /** True when this field's `depends_on` target is fed per row from the file (spec 0108). */
  dependencyFromFile?: boolean
}

/** Branches a single global field's control by descriptor shape (`multiple` / relation / plain number). */
function ImportConfigFieldControl({
  field,
  globalFields,
  control,
  value,
  onChange,
  triggerLabel,
  dependencyFromFile,
  id,
  'aria-describedby': ariaDescribedBy,
  'aria-invalid': ariaInvalid,
}: ImportConfigFieldControlProps) {
  if (field.multiple && field.depends_on) {
    return (
      <ImportConfigDependentMultiSelect
        field={field}
        dependsOnFieldId={field.depends_on}
        dependencyFromFile={dependencyFromFile}
        globalFields={globalFields}
        control={control}
        value={(value as number[] | null) ?? []}
        onChange={onChange}
        triggerLabel={triggerLabel}
        id={id}
        aria-describedby={ariaDescribedBy}
        aria-invalid={ariaInvalid}
      />
    )
  }

  if (field.multiple) {
    return (
      <ImportConfigMultiSelect
        resource={field.for_select_resource ?? ''}
        value={(value as number[] | null) ?? []}
        onChange={onChange}
        triggerLabel={triggerLabel}
        id={id}
        aria-describedby={ariaDescribedBy}
        aria-invalid={ariaInvalid}
      />
    )
  }

  if (field.for_select_resource) {
    return (
      <ImportConfigRelationSelect
        resource={field.for_select_resource}
        value={(value as number | null) ?? null}
        onChange={onChange}
        triggerLabel={triggerLabel}
      />
    )
  }

  return (
    <Input
      type="number"
      id={id}
      aria-describedby={ariaDescribedBy}
      aria-invalid={ariaInvalid}
      value={(value as number | null) ?? ''}
      onChange={(event) => onChange(event.target.value === '' ? null : Number(event.target.value))}
    />
  )
}

interface ImportConfigDependentMultiSelectProps extends AccessibleControlProps {
  field: ImportGlobalFieldDescriptor
  dependsOnFieldId: string
  /** True when the dependency is resolved per row from a file column, not run-wide (spec 0108). */
  dependencyFromFile?: boolean
  globalFields: ImportGlobalFieldDescriptor[]
  control: Control<ImportMappingFormValues>
  value: number[]
  onChange: (next: number[]) => void
  triggerLabel: string
}

/**
 * A `multiple` field scoped by another global field's current value (spec
 * 0094 AC-050/AC-055: `product_ids` depends on `campaign_id`). Reads the
 * dependency's own for-select item — via the SAME ids-keyed label query the
 * relation select uses to resolve its trigger label, so no extra request —
 * and extracts `meta.product_category_ids` to scope the picker's `category_ids`
 * param, mirroring `ProductsOfInterestField`. Disabled until the dependency
 * has a value: there is nothing to scope to yet (AC-040 parity on the Lead
 * form's own picker).
 */
function ImportConfigDependentMultiSelect({
  field,
  dependsOnFieldId,
  dependencyFromFile,
  globalFields,
  control,
  value,
  onChange,
  triggerLabel,
  id,
  'aria-describedby': ariaDescribedBy,
  'aria-invalid': ariaInvalid,
}: ImportConfigDependentMultiSelectProps) {
  const { t } = useTranslation('importWizard')
  const { t: tDefault } = useTranslation()
  const dependsOnField = globalFields.find((candidate) => candidate.id === dependsOnFieldId)

  const dependsOnValue = useWatch({
    control,
    name: `global_config.${dependsOnFieldId}` as FieldPath<ImportMappingFormValues>,
  }) as number | null

  const dependencyItems = useForSelectLabels({
    resource: dependsOnField?.for_select_resource ?? '',
    ids: dependsOnValue != null ? [dependsOnValue] : [],
    enabled: dependsOnValue != null && dependsOnField?.for_select_resource != null,
  })

  const dependencyItem = dependsOnValue != null ? (dependencyItems.get(dependsOnValue) ?? null) : null
  const categoryIds = useMemo(() => dependencyProductCategoryIds(dependencyItem), [dependencyItem])
  const params = useMemo(() => ({ category_ids: categoryIds }), [categoryIds])
  // Spec 0108 (D-6/AC-034): a dependency read per row from the file leaves
  // this field nothing run-wide to scope against — disabled for a DIFFERENT
  // reason than "not chosen yet", and the hint says which.
  const disabled = dependencyFromFile === true || dependsOnValue == null

  return (
    <div className="flex flex-col gap-1">
      <ImportConfigMultiSelect
        resource={field.for_select_resource ?? ''}
        value={value}
        onChange={onChange}
        triggerLabel={triggerLabel}
        params={params}
        disabled={disabled}
        id={id}
        aria-describedby={ariaDescribedBy}
        aria-invalid={ariaInvalid}
      />
      {disabled ? (
        <p className="text-xs text-muted-foreground">
          {dependencyFromFile
            ? t('config.multiSelect.perRowDependencyHint', {
                field: dependsOnField ? tDefault(dependsOnField.label) : dependsOnFieldId,
              })
            : t('config.multiSelect.disabledHint')}
        </p>
      ) : null}
    </div>
  )
}
