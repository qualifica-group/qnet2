/**
 * AG Grid popup cell editor for an `editor: 'multiselect'` column (user
 * directive 2026-07-23): the in-grid twin of the form's multi-select picker —
 * a search box + a checkable `/for-select` list, and the SAME scope management
 * the form offers ("prodotti di interesse": only the options of the row's own
 * scope by default, the whole catalogue behind an explicit confirmation).
 *
 * Two constraints shape the markup, both learned by `RelationCellEditor`:
 *  - the list is rendered INSIDE the popup, never as a nested Radix Popover: a
 *    second floating layer portalled to `document.body` makes
 *    `stopEditingWhenCellsLoseFocus` tear the editor down mid-open;
 *  - for the same reason the unlock confirmation is an INLINE alert panel, not
 *    the `useConfirm` dialog the form uses — a portalled dialog would close the
 *    editor the moment it opened. Same warning, same two choices, no portal.
 *
 * Unlike the single-value editors, a pick does NOT close the popup: a
 * collection is built by toggling several options, and the value is committed
 * when the editor closes (Enter/Esc/click-away, AG Grid's own popup handling).
 */
import { useEffect, useMemo, useRef, useState } from 'react'
import type { CustomCellEditorProps } from 'ag-grid-react'
import { useTranslation } from 'react-i18next'
import { AlertTriangle, Check, Loader2, Lock, LockOpen } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { useDebouncedValue } from '@/hooks/use-debounced-value'
import { useForSelect } from '@/features/for-select/use-for-select'
import type { ForSelectItem } from '@/features/for-select/types'
import { resolveMultiScopeParams } from '@/components/data-table/multi-select-scope'
import type { TableRow } from '@/features/table/types'
import { cn } from '@/lib/utils'

/** A multiselect column's cell value: the related rows' `{id, name}` projections. */
export interface MultiSelectCellValue {
  id: number
  name: string
}

/** Extra `cellEditorParams` for this editor: which `/for-select` resource feeds the list, and how the row narrows it. */
export interface MultiSelectCellEditorParams {
  resource: string
  /**
   * Spec 0075, D-4: the scope is not a default the operator may lift — this
   * domain REFUSES what falls outside it (request-management's coherence
   * rule), so the unlock is not offered at all. Absent/false keeps the
   * opportunities behaviour, where the cross-category pick legitimately adds
   * the missing product line.
   */
  lockScope?: boolean
  /**
   * Row-scoped narrowing, same contract as `RelationCellEditor`'s: `/for-select`
   * param name -> id of the column supplying its value on the EDITED row. Here
   * the value may also be an ARRAY of ids (e.g. `category_ids` from the row's
   * product-line categories), which is what makes the default scope match the
   * form picker's exactly.
   */
  scope?: Record<string, string>
}

/** Debounce before a typed term reaches the server, matching every other picker. */
const SEARCH_DEBOUNCE_MS = 300

export function MultiSelectCellEditor(
  props: CustomCellEditorProps<TableRow, MultiSelectCellValue[] | null> & MultiSelectCellEditorParams,
) {
  const { t } = useTranslation()
  const { value, onValueChange, resource, scope, lockScope = false, data } = props
  const [search, setSearch] = useState('')
  const [unlocked, setUnlocked] = useState(false)
  const [confirmingUnlock, setConfirmingUnlock] = useState(false)
  const debouncedSearch = useDebouncedValue(search, SEARCH_DEBOUNCE_MS)
  const inputRef = useRef<HTMLInputElement>(null)

  // The single click that opened the cell is the only one the operator makes
  // (0053 D-9): they can type straight away.
  useEffect(() => {
    inputRef.current?.focus()
  }, [])

  const selected = useMemo(() => value ?? [], [value])
  const selectedIds = useMemo(() => selected.map((item) => item.id), [selected])
  const scopeParams = useMemo(() => resolveMultiScopeParams(scope, data), [scope, data])

  // Locked with nothing to scope to would silently show the WHOLE catalogue —
  // the opposite of what the lock promises, so the list stays closed until the
  // operator unlocks it explicitly (mirrors the form picker).
  const lockedWithoutScope = !unlocked && scopeParams === undefined

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
    ids: selectedIds.length > 0 ? selectedIds : undefined,
    params: unlocked ? undefined : scopeParams,
    enabled: !lockedWithoutScope,
  })

  const options = pages?.pages.flatMap((page) => page.items) ?? []

  const toggle = (item: ForSelectItem) => {
    const next = selectedIds.includes(item.id)
      ? selected.filter((entry) => entry.id !== item.id)
      : [...selected, { id: item.id, name: item.label }]
    onValueChange(next)
  }

  return (
    <div className="w-72 rounded-md border border-border bg-popover shadow-md">
      <div className="p-1">
        <Input
          ref={inputRef}
          value={search}
          onChange={(event: React.ChangeEvent<HTMLInputElement>) => setSearch(event.target.value)}
          placeholder={t('table.multiSelectEditor.searchPlaceholder')}
          aria-label={t('table.multiSelectEditor.searchPlaceholder')}
          disabled={lockedWithoutScope}
          className="h-7 text-xs"
        />
      </div>

      <div
        role="listbox"
        aria-multiselectable="true"
        aria-label={t('table.multiSelectEditor.list')}
        className="max-h-56 overflow-y-auto p-1"
      >
        {lockedWithoutScope ? (
          <p className="px-2 py-6 text-center text-xs text-muted-foreground">
            {t('table.multiSelectEditor.noScope')}
          </p>
        ) : isPending ? (
          <div className="flex items-center justify-center py-6">
            <Loader2 className="size-4 animate-spin text-muted-foreground" aria-hidden="true" />
          </div>
        ) : isError ? (
          <div className="flex flex-col items-center gap-2 px-2 py-6 text-center">
            <p className="text-xs text-muted-foreground">{t('table.multiSelectEditor.error')}</p>
            <button
              type="button"
              onClick={() => void refetch()}
              className="text-xs font-medium text-primary underline-offset-4 hover:underline"
            >
              {t('table.multiSelectEditor.retry')}
            </button>
          </div>
        ) : options.length === 0 ? (
          <p className="px-2 py-6 text-center text-xs text-muted-foreground">{t('table.multiSelectEditor.empty')}</p>
        ) : (
          <>
            {options.map((item) => {
              const isSelected = selectedIds.includes(item.id)
              return (
                <button
                  key={item.id}
                  type="button"
                  role="option"
                  aria-selected={isSelected}
                  onClick={() => toggle(item)}
                  className={cn(
                    'flex w-full items-center gap-1.5 rounded-sm px-2.5 py-1 text-left text-xs',
                    'hover:bg-accent focus-visible:bg-accent focus-visible:outline-none',
                    isSelected && 'font-medium',
                  )}
                >
                  <Check
                    className={cn('size-3.5 shrink-0', isSelected ? 'opacity-100' : 'opacity-0')}
                    aria-hidden="true"
                  />
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
                  t('table.multiSelectEditor.loadMore')
                )}
              </button>
            ) : null}
          </>
        )}
      </div>

      {lockScope ? (
        <p className="truncate border-t border-border p-1.5 text-xs text-muted-foreground">
          {t('table.multiSelectEditor.hintLocked')}
        </p>
      ) : confirmingUnlock ? (
        // The form's confirmation dialog, inlined (see the module docblock):
        // same warning, same two choices, no portal to lose focus to.
        <div role="alertdialog" aria-label={t('table.multiSelectEditor.unlockTitle')} className="border-t border-border p-2">
          <p className="flex gap-1.5 text-xs text-muted-foreground">
            <AlertTriangle aria-hidden="true" className="mt-0.5 size-3.5 shrink-0 text-amber-600 dark:text-amber-400" />
            <span>
              <span className="block font-medium text-foreground">{t('table.multiSelectEditor.unlockTitle')}</span>
              {t('table.multiSelectEditor.unlockDescription')}
            </span>
          </p>
          <div className="mt-2 flex justify-end gap-1.5">
            <Button type="button" variant="secondary" size="sm" onClick={() => setConfirmingUnlock(false)}>
              {t('common.cancel')}
            </Button>
            <Button
              type="button"
              size="sm"
              onClick={() => {
                setUnlocked(true)
                setConfirmingUnlock(false)
              }}
            >
              {t('table.multiSelectEditor.unlock')}
            </Button>
          </div>
        </div>
      ) : (
        <div className="flex items-center justify-between gap-2 border-t border-border p-1.5">
          <p className="truncate text-xs text-muted-foreground">
            {unlocked ? t('table.multiSelectEditor.hintUnlocked') : t('table.multiSelectEditor.hintScoped')}
          </p>
          <Button
            type="button"
            variant="outline"
            size="sm"
            className="shrink-0 bg-card"
            onClick={unlocked ? () => setUnlocked(false) : () => setConfirmingUnlock(true)}
          >
            {unlocked ? (
              <>
                <Lock aria-hidden="true" className="size-3.5" />
                {t('table.multiSelectEditor.relock')}
              </>
            ) : (
              <>
                <LockOpen aria-hidden="true" className="size-3.5" />
                {t('table.multiSelectEditor.unlock')}
              </>
            )}
          </Button>
        </div>
      )}
    </div>
  )
}
