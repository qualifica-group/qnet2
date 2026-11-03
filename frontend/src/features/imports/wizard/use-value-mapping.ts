import { useCallback, useMemo, useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { fetchValueMapping } from '@/features/imports/wizard/api'
import type { ImportConfigFormValues } from '@/features/imports/wizard/import-config-schema'
import { importWizardKeys } from '@/features/imports/wizard/query-keys'
import type { ImportRunDetail, ValueMappingEntry, ValueMappings } from '@/features/imports/wizard/types'
import {
  EMPTY_VALUE_MAPPING_FIELDS,
  EMPTY_VALUE_MAPPINGS,
  PRODUCT_CATEGORY_FIELD_ID,
  resolveValueMappings,
  SOURCE_FIELD_ID,
  WORKFLOW_STATUS_FIELD_ID,
} from '@/features/imports/wizard/value-mapping-state'

interface UseValueMappingArgs {
  run: ImportRunDetail
  /** Column key -> target, as currently selected in the (unsaved) form. */
  columnMapping: Record<string, string>
  globalConfig: ImportConfigFormValues
}

function numericConfig(globalConfig: ImportConfigFormValues, fieldId: string): number | null {
  const value = globalConfig[fieldId]
  return typeof value === 'number' ? value : null
}

/**
 * Server suggestions + the operator's choices for the "Valori" panel (spec
 * 0348). The request only runs once a value-mapping field is mapped (and, for
 * `workflow_status`, once the product line is chosen); the response depends
 * only on the mapped columns, the product line and the fallback source, so
 * that signature — not the whole mapping — keys the query.
 */
export function useValueMapping({ run, columnMapping, globalConfig }: UseValueMappingArgs) {
  const [manualState, setManualState] = useState<{ signature: string; choices: ValueMappings }>({
    signature: '',
    choices: EMPTY_VALUE_MAPPINGS,
  })

  const valueFields = run.value_mapping_fields ?? []
  const mappedColumns = Object.entries(columnMapping)
    .filter(([, target]) => valueFields.includes(target))
    .map(([columnKey, target]) => `${target}=${columnKey}`)
    .sort()
  const categoryId = numericConfig(globalConfig, PRODUCT_CATEGORY_FIELD_ID)
  const sourceId = numericConfig(globalConfig, SOURCE_FIELD_ID)
  const needsCategory = mappedColumns.some((entry) => entry.startsWith(`${WORKFLOW_STATUS_FIELD_ID}=`))
  const enabled = mappedColumns.length > 0 && (!needsCategory || categoryId != null)
  const signature = JSON.stringify([mappedColumns, categoryId, sourceId])

  // Choices belong to one mapping/product line: a different signature (other
  // workflow, other column) starts clean so no stale id is ever submitted.
  const manual = manualState.signature === signature ? manualState.choices : EMPTY_VALUE_MAPPINGS

  const query = useQuery({
    queryKey: importWizardKeys.valueMapping(run.resource, run.id, signature),
    queryFn: () =>
      fetchValueMapping(run.resource, run.id, {
        column_mapping: columnMapping,
        global_config: Object.fromEntries(Object.entries(globalConfig).filter(([, value]) => value != null)),
      }),
    enabled,
  })

  const fields = query.data ?? EMPTY_VALUE_MAPPING_FIELDS
  const template = run.matching_template?.value_mappings ?? null
  const resolved = useMemo(() => resolveValueMappings(fields, manual, template), [fields, manual, template])

  const setEntry = useCallback(
    (fieldId: string, value: string, entry: ValueMappingEntry) => {
      setManualState((current) => {
        const base = current.signature === signature ? current.choices : EMPTY_VALUE_MAPPINGS
        return { signature, choices: { ...base, [fieldId]: { ...base[fieldId], [value]: entry } } }
      })
    },
    [signature],
  )

  return {
    fields,
    manual,
    template,
    setEntry,
    isLoading: enabled && query.isPending,
    isError: enabled && query.isError,
    refetch: query.refetch,
    awaitingCategory: needsCategory && categoryId == null,
    unmatched: resolved.unmatched,
    /** Complete entries to send with `configure`; `undefined` while no value field is mapped/loaded. */
    payload: enabled && query.data ? resolved.payload : undefined,
    /** The step cannot be submitted while the panel is loading, failed, or has an unmatched value. */
    blocked: enabled && (query.isPending || query.isError || resolved.unmatched > 0),
  }
}

export type ValueMappingController = ReturnType<typeof useValueMapping>
