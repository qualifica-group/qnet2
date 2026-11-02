import type { GridApi, SetFilterHandler } from 'ag-grid-community'

/**
 * Values a domain adapter wrote into a column's Set Filter through the table
 * handle (preset tabs, the invoice month strip), keyed by grid. AG Grid keeps a Set Filter
 * model value only when the filter's value list holds it, and that list is
 * scoped to the OTHER columns' filters (0004): a preset with no matching row
 * in the current scope (the "Fatture" tab on a month with proformas only) was
 * dropped silently and the tab snapped back to "Tutte". The values getter
 * merges these into the server list so the preset always sticks.
 */
const presetValues = new WeakMap<GridApi, Map<string, string[]>>()

function setModelValues(model: unknown): string[] | null {
  const entry = model as { filterType?: unknown; values?: unknown } | null
  if (entry?.filterType !== 'set' || !Array.isArray(entry.values)) {
    return null
  }
  return entry.values.map(String)
}

/**
 * Records the Set Filter values of a filter-model patch and reloads the value
 * list of the filters already built, BEFORE the caller applies the model.
 * AG Grid's value promises settle synchronously, so the reload replaces the
 * list the filter will validate the incoming model against.
 */
export function registerPresetSetFilterValues(api: GridApi, patch: Record<string, unknown>): void {
  const columns = presetValues.get(api) ?? new Map<string, string[]>()
  presetValues.set(api, columns)
  for (const [columnId, model] of Object.entries(patch)) {
    const values = setModelValues(model)
    if (values === null) {
      columns.delete(columnId)
      continue
    }
    columns.set(columnId, values)
    api.getColumnFilterHandler<Partial<SetFilterHandler>>(columnId)?.refreshFilterValues?.()
  }
}

/**
 * The server value list of a column plus the preset values it does not offer
 * yet: those written through the table handle and those of the model restored
 * at grid creation (saved filters + `forcedFilterModel`), which goes through
 * the same validation and would otherwise lose a value (and, cascading, the
 * other columns' values scoped by it) when the restored scope has no row.
 */
export function withPresetSetFilterValues(
  api: GridApi,
  columnId: string,
  values: (string | null)[],
): (string | null)[] {
  const restored = setModelValues(api.getGridOption('initialState')?.filter?.filterModel?.[columnId]) ?? []
  const presets = [...restored, ...(presetValues.get(api)?.get(columnId) ?? [])]
  const missing = [...new Set(presets)].filter((value) => !values.includes(value))
  return missing.length > 0 ? [...values, ...missing] : values
}
