import {
  STATUS_COLUMN_KEYS,
  type ColumnStatusesConfig,
  type ColumnStatusesSelection,
  type ColumnStatusWorkflow,
} from '@/features/request-management/column-statuses-api'

/** The saved choice of every column, keyed by column. */
export function selectionOf(config: ColumnStatusesConfig): ColumnStatusesSelection {
  const byKey = new Map(config.columns.map((column) => [column.key, column.status_ids]))

  return Object.fromEntries(STATUS_COLUMN_KEYS.map((key) => [key, byKey.get(key) ?? []])) as ColumnStatusesSelection
}

/** Adds or removes every id of `ids`, keeping the list ascending. */
export function withStatuses(selected: number[], ids: number[], checked: boolean): number[] {
  const next = new Set(selected)
  for (const id of ids) {
    if (checked) {
      next.add(id)
    } else {
      next.delete(id)
    }
  }

  return [...next].sort((a, b) => a - b)
}

function matches(value: string, needle: string): boolean {
  return value.toLocaleLowerCase().includes(needle)
}

/**
 * The status sets the search leaves: a set whose name or one of whose
 * categories matches keeps every status, otherwise only its matching statuses;
 * a set with nothing left is dropped.
 */
export function filterWorkflows(workflows: ColumnStatusWorkflow[], search: string): ColumnStatusWorkflow[] {
  const needle = search.trim().toLocaleLowerCase()
  if (needle === '') {
    return workflows
  }

  return workflows.flatMap((workflow) => {
    if (matches(workflow.name, needle) || workflow.categories.some((category) => matches(category, needle))) {
      return [workflow]
    }

    const statuses = workflow.statuses.filter((status) => matches(status.name, needle))
    return statuses.length > 0 ? [{ ...workflow, statuses }] : []
  })
}
