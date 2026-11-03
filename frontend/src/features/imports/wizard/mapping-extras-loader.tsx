import { useEffect, useMemo, useRef, type ReactNode } from 'react'
import type { FieldPath, UseFormReturn } from 'react-hook-form'
import type { ImportConfigFormValues } from '@/features/imports/wizard/import-config-schema'
import type { ImportMappingFormValues } from '@/features/imports/wizard/import-mapping-schema'
import {
  attributeMappingUpdates,
  NO_ATTRIBUTE_OPTIONS,
  toAttributeOptions,
  type AttributeOption,
} from '@/features/imports/wizard/mapping-attribute-options'
import type { DetectedColumn, ImportRunDetail } from '@/features/imports/wizard/types'
import { useValueMapping, type ValueMappingController } from '@/features/imports/wizard/use-value-mapping'
import { useEffectiveAttributes } from '@/features/product-categories/use-effective-attributes'

/** Domain-specific mapping aids (spec 0348); both empty for the lead domain. */
export interface MappingExtras {
  attributeOptions: AttributeOption[]
  /** `null` when the domain has no value-mapping fields (no "Valori" panel). */
  valueMapping: ValueMappingController | null
}

const NO_EXTRAS: MappingExtras = { attributeOptions: NO_ATTRIBUTE_OPTIONS, valueMapping: null }

interface MappingExtrasLoaderProps {
  run: ImportRunDetail | null
  form: UseFormReturn<ImportMappingFormValues>
  columns: DetectedColumn[]
  /** Column key -> target, as currently selected in the form. */
  keyedMapping: Record<string, string>
  globalConfig: ImportConfigFormValues
  children: (extras: MappingExtras) => ReactNode
}

/**
 * Loads what only some domains need next to the column targets: the offer
 * attributes of the chosen category and the "Valori" matching. It is a
 * component (not a hook of the step) so the lead domain — which has neither —
 * never mounts a query at all.
 */
export function MappingExtrasLoader({ run, children, ...rest }: MappingExtrasLoaderProps) {
  const needsExtras = run != null && ((run.value_mapping_fields?.length ?? 0) > 0 || run.attribute_targets != null)
  if (!run || !needsExtras) {
    return <>{children(NO_EXTRAS)}</>
  }
  return (
    <LoadedExtras run={run} {...rest}>
      {children}
    </LoadedExtras>
  )
}

interface LoadedExtrasProps extends Omit<MappingExtrasLoaderProps, 'run'> {
  run: ImportRunDetail
}

function LoadedExtras({ run, form, columns, keyedMapping, globalConfig, children }: LoadedExtrasProps) {
  const dependsOn = run.attribute_targets?.depends_on
  const rawCategory = dependsOn ? globalConfig[dependsOn] : null
  const categoryId = typeof rawCategory === 'number' ? rawCategory : null
  const attributes = useEffectiveAttributes(categoryId, run.attribute_targets?.context ?? 'quote')
  const attributeOptions = useMemo(
    () => (attributes.data ? toAttributeOptions(attributes.data) : NO_ATTRIBUTE_OPTIONS),
    [attributes.data],
  )

  // Applies the catalogue's suggestions once per category, so the operator's
  // later edits are never overwritten by a refetch.
  const appliedFor = useRef<number | null | undefined>(undefined)
  useEffect(() => {
    if (!attributes.data || appliedFor.current === categoryId) return
    appliedFor.current = categoryId
    const updates = attributeMappingUpdates(columns, form.getValues('mapping'), attributeOptions)
    for (const [index, target] of Object.entries(updates)) {
      form.setValue(`mapping.${index}` as FieldPath<ImportMappingFormValues>, target)
    }
  }, [attributes.data, attributeOptions, categoryId, columns, form])

  const valueMapping = useValueMapping({ run, columnMapping: keyedMapping, globalConfig })
  const hasValuePanel = (run.value_mapping_fields?.length ?? 0) > 0

  return <>{children({ attributeOptions, valueMapping: hasValuePanel ? valueMapping : null })}</>
}
