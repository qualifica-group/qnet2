/**
 * AG Grid popup cell editor for a `relation` column (spec 0054 D-7, rebuilt by
 * the spec 0055 follow-up): a search box + the `/for-select` result list,
 * rendered DIRECTLY inside AG Grid's popup editor.
 *
 * It used to compose `AsyncPaginatedSelect` (a Radix Popover) inside the cell.
 * That never worked in a real grid, and isolated component tests hid it:
 *  - the Popover portals its content to `document.body`, so the moment it
 *    opened, `stopEditingWhenCellsLoseFocus` saw focus leave the cell and AG
 *    Grid tore the editor down mid-open — clicking the cell appeared to do
 *    nothing at all;
 *  - portalled back inside the editor instead, the popper's fixed positioning
 *    resolved against its static flow position and the list detached from the
 *    cell by the grid's own offset.
 * Both symptoms share one cause: a second floating layer nested inside a popup
 * that IS already the floating layer. The list belongs in the popup, not on top
 * of it — the same shape as `SelectCellEditor`, which had neither bug.
 *
 * Data comes from the SAME `useForSelect` infinite query every other relation
 * picker uses (ADR 0011), so search, paging and the endpoint contract stay
 * shared; only the presentation is local to the cell.
 *
 * Popup rendering is declared statically on the colDef (`cellEditorPopup:
 * true`, `cell-editor-registry.ts`) — the functional-component API has no
 * `isPopup()` callback of its own.
 */
import { useEffect, useRef, useState } from 'react'
import type { CustomCellEditorProps } from 'ag-grid-react'
import { useTranslation } from 'react-i18next'
import { Check, Loader2 } from 'lucide-react'
import { Input } from '@/components/ui/input'
import { UserAvatar } from '@/components/user-avatar'
import { useDebouncedValue } from '@/hooks/use-debounced-value'
import { useForSelect } from '@/features/for-select/use-for-select'
import type { ForSelectItem } from '@/features/for-select/types'
import type { TableRow } from '@/features/table/types'
import { cn } from '@/lib/utils'

/** A relation column's cell value: the related row's `{id, name}` projection, or `null`. */
export interface RelationCellValue {
  id: number
  name: string
}

/** Extra `cellEditorParams` for this editor (spec 0054 D-1): which `/for-select` resource feeds the list. */
export interface RelationCellEditorParams {
  resource: string
  /**
   * Prefix each option with its `UserAvatar` — image when the item carries one,
   * the label's initials otherwise. Opt-in per resource, exactly like
   * `AsyncPaginatedSelect`'s prop of the same name: presence of `avatar_url`
   * cannot stand in for it, since the for-select envelope strips null optionals
   * (`ForSelectResource::toArray`), which is precisely the no-image case.
   */
  showAvatar?: boolean
  /**
   * Row-scoped narrowing of the option list (user directive 2026-07-23):
   * `/for-select` param name -> id of the column supplying its value on the
   * EDITED row (`TableColumn.relation.scope`). Absent, or a row whose scope
   * column is empty, leaves the list unfiltered.
   */
  scope?: Record<string, string>
}

/** Debounce before a typed term reaches the server, matching AsyncPaginatedSelect. */
const SEARCH_DEBOUNCE_MS = 300

/**
 * Resolves `scope` against the row being edited. A scope column holds either a
 * relation projection (`{id, name|label}` — how every derived relation column
 * is mapped server-side) or a bare id; anything else contributes no param, so
 * a row missing the value degrades to the unfiltered list rather than sending
 * a junk filter.
 */
function resolveScopeParams(
  scope: Record<string, string> | undefined,
  row: TableRow | undefined,
): Record<string, number> | undefined {
  if (!scope || !row) {
    return undefined
  }

  const params: Record<string, number> = {}

  for (const [param, columnId] of Object.entries(scope)) {
    const cell = row[columnId]
    const id =
      typeof cell === 'number'
        ? cell
        : typeof (cell as { id?: unknown } | null)?.id === 'number'
          ? (cell as { id: number }).id
          : null
    if (id !== null) {
      params[param] = id
    }
  }

  return Object.keys(params).length > 0 ? params : undefined
}

export function RelationCellEditor(
  props: CustomCellEditorProps<TableRow, RelationCellValue | null> & RelationCellEditorParams,
) {
  const { t } = useTranslation()
  const { value, onValueChange, stopEditing, resource, showAvatar = false, scope, data } = props
  const [search, setSearch] = useState('')
  const debouncedSearch = useDebouncedValue(search, SEARCH_DEBOUNCE_MS)
  const inputRef = useRef<HTMLInputElement>(null)

  // The single click that opened the cell is the only one the operator makes
  // (0053 D-9): they can type straight away.
  useEffect(() => {
    inputRef.current?.focus()
  }, [])

  const {
    data: pages,
    isPending,
    isError,
    refetch,
    hasNextPage,
    isFetchingNextPage,
    fetchNextPage,
  } = useForSelect({
    resource,
    search: debouncedSearch,
    ids: value ? [value.id] : undefined,
    params: resolveScopeParams(scope, data),
  })

  const options = pages?.pages.flatMap((page) => page.items) ?? []

  const pick = (item: ForSelectItem | null) => {
    // Re-picking the current value must not commit: the cell value is an
    // object, so a fresh `{id, name}` would never compare equal to the old one
    // and the hook's no-op guard would let a pointless PATCH through.
    if ((item?.id ?? null) !== (value?.id ?? null)) {
      onValueChange(item ? { id: item.id, name: item.label } : null)
    }
    stopEditing()
  }

  return (
    <div className="w-72 rounded-md border border-border bg-popover shadow-md">
      <div className="p-1">
        <Input
          ref={inputRef}
          value={search}
          onChange={(event: React.ChangeEvent<HTMLInputElement>) => setSearch(event.target.value)}
          placeholder={t('table.relationEditor.searchPlaceholder')}
          aria-label={t('table.relationEditor.searchPlaceholder')}
          className="h-7 text-xs"
        />
      </div>

      <div role="listbox" aria-label={t('table.relationEditor.trigger')} className="max-h-64 overflow-y-auto p-1">
        {isPending ? (
          <div className="flex items-center justify-center py-6">
            <Loader2 className="size-4 animate-spin text-muted-foreground" aria-hidden="true" />
          </div>
        ) : isError ? (
          <div className="flex flex-col items-center gap-2 px-2 py-6 text-center">
            <p className="text-xs text-muted-foreground">{t('table.relationEditor.error')}</p>
            <button
              type="button"
              onClick={() => void refetch()}
              className="text-xs font-medium text-primary underline-offset-4 hover:underline"
            >
              {t('table.relationEditor.retry')}
            </button>
          </div>
        ) : options.length === 0 ? (
          <p className="px-2 py-6 text-center text-xs text-muted-foreground">{t('table.relationEditor.empty')}</p>
        ) : (
          <>
            {value ? (
              <button
                type="button"
                onClick={() => pick(null)}
                className="flex w-full items-center gap-1.5 rounded-sm px-2.5 py-1 text-left text-xs text-muted-foreground hover:bg-accent"
              >
                <span className="size-3.5 shrink-0" aria-hidden="true" />
                {t('table.relationEditor.clear')}
              </button>
            ) : null}
            {options.map((item) => {
              const selected = item.id === value?.id
              return (
                <button
                  key={item.id}
                  type="button"
                  role="option"
                  aria-selected={selected}
                  onClick={() => pick(item)}
                  className={cn(
                    'flex w-full items-center gap-1.5 rounded-sm px-2.5 py-1 text-left text-xs',
                    'hover:bg-accent focus-visible:bg-accent focus-visible:outline-none',
                    selected && 'font-medium',
                  )}
                >
                  <Check
                    className={cn('size-3.5 shrink-0', selected ? 'opacity-100' : 'opacity-0')}
                    aria-hidden="true"
                  />
                  {showAvatar ? (
                    <UserAvatar name={item.label} src={item.avatar_url} size="sm" className="shrink-0" />
                  ) : null}
                  <span className="truncate">{item.label}</span>
                </button>
              )
            })}
            {hasNextPage ? (
              <button
                type="button"
                onClick={() => void fetchNextPage()}
                disabled={isFetchingNextPage}
                className="flex w-full items-center justify-center gap-1.5 rounded-sm px-2.5 py-1 text-xs text-muted-foreground hover:bg-accent"
              >
                {isFetchingNextPage ? (
                  <Loader2 className="size-3.5 animate-spin" aria-hidden="true" />
                ) : (
                  t('table.relationEditor.loadMore')
                )}
              </button>
            ) : null}
          </>
        )}
      </div>
    </div>
  )
}
