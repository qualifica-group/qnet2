import type {
  FilterOpenedEvent,
  GridApi,
  IMultiFilter,
  MultiFilterHandler,
  SetFilterHandler,
  SetFilterUi,
} from 'ag-grid-community'

/**
 * Server-side search inside a Set Filter checklist (task 427169).
 *
 * The values endpoint returns at most the first N distinct values, in order:
 * AG Grid's own search box only filters that loaded list, so on a column with
 * thousands of values (work order codes, client names) most of them could
 * never be found. Once a column's list came back truncated, what the user types
 * in the search box is sent to the server as `search` and the list is reloaded;
 * AG Grid then keeps filtering the new list client-side with the same text.
 * A column whose whole list fits keeps the pure client-side search, which also
 * matches the localized labels of badge/enum values.
 */

/** Pause after the last keystroke before the value list is asked again. */
const SEARCH_DEBOUNCE_MS = 300

const MINI_FILTER_INPUT_SELECTOR = '.ag-set-filter .ag-mini-filter input'

/** `buildColumnFilter` puts the Set Filter first in every Multi Filter. */
const MULTI_FILTER_SET_INDEX = 0

const truncatedColumns = new WeakMap<GridApi, Set<string>>()

const hookedInputs = new WeakSet<HTMLInputElement>()

/** Records that a column's value list is truncated: from now on its search goes to the server. */
export function markSetFilterTruncated(api: GridApi, columnId: string): void {
  const columns = truncatedColumns.get(api) ?? new Set<string>()
  truncatedColumns.set(api, columns)
  columns.add(columnId)
}

function isTruncated(api: GridApi, columnId: string): boolean {
  return truncatedColumns.get(api)?.has(columnId) ?? false
}

/**
 * The search text to send with a column's values request: the Set Filter's
 * current search box, only for a truncated column; null otherwise.
 */
export async function setFilterServerSearch(api: GridApi, columnId: string): Promise<string | null> {
  if (!isTruncated(api, columnId)) {
    return null
  }
  const search = (await resolveSetFilterUi(api, columnId))?.getMiniFilter?.()?.trim()

  return search ? search : null
}

/**
 * Re-runs the search box over the list reloaded by a server search. In Excel
 * mode typing checks the matches of the list on screen, which was still the
 * old one: without this the values the server just returned stay unchecked
 * and Apply would ignore them. Called once AG Grid has taken the new list.
 */
export async function checkServerSearchMatches(api: GridApi, columnId: string): Promise<void> {
  const setFilter = await resolveSetFilterUi(api, columnId)
  const search = setFilter?.getMiniFilter?.()
  if (!setFilter?.setMiniFilter || !search) {
    return
  }
  setFilter.setMiniFilter(null)
  setFilter.setMiniFilter(search)
}

async function resolveSetFilterUi(api: GridApi, columnId: string): Promise<Partial<SetFilterUi> | null | undefined> {
  const instance = await api.getColumnFilterInstance<Partial<IMultiFilter & SetFilterUi>>(columnId)

  return instance?.getChildFilterInstance
    ? instance.getChildFilterInstance<Partial<SetFilterUi>>(MULTI_FILTER_SET_INDEX)
    : instance
}

function refreshSetFilterValues(api: GridApi, columnId: string): void {
  const handler = api.getColumnFilterHandler<Partial<MultiFilterHandler & SetFilterHandler>>(columnId)
  const setHandler = handler?.getHandler
    ? handler.getHandler<Partial<SetFilterHandler>>(MULTI_FILTER_SET_INDEX)
    : handler
  setHandler?.refreshFilterValues?.()
}

/**
 * Wire to the grid's `onFilterOpened`: hooks the opened column's search box
 * once, so typing reloads a truncated list from the server.
 */
export function hookSetFilterServerSearch(event: FilterOpenedEvent): void {
  if (!('getColId' in event.column)) {
    return
  }
  const input = event.eGui.querySelector<HTMLInputElement>(MINI_FILTER_INPUT_SELECTOR)
  if (input === null || hookedInputs.has(input)) {
    return
  }
  hookedInputs.add(input)

  const { api } = event
  const columnId = event.column.getColId()
  let timer: ReturnType<typeof setTimeout> | undefined
  input.addEventListener('input', () => {
    clearTimeout(timer)
    timer = setTimeout(() => {
      if (isTruncated(api, columnId)) {
        refreshSetFilterValues(api, columnId)
      }
    }, SEARCH_DEBOUNCE_MS)
  })
}
