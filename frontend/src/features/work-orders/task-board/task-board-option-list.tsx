/**
 * The searchable `/for-select` list inside a picker cell's popover (spec 0401
 * D-6): the same data source and option rows as every other relation picker
 * (`useForSelect`, `AsyncSelectOptionRow`), laid out like the Task grid's own
 * relation editor.
 */

import { useState, type ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { Input } from '@/components/ui/input'
import { AsyncSelectOptionRow, OptionsSkeleton } from '@/components/ui/async-paginated-select-option'
import { useDebouncedValue } from '@/hooks/use-debounced-value'
import { flattenForSelectPages, useForSelect } from '@/features/for-select/use-for-select'
import type { ForSelectItem } from '@/features/for-select/types'

const SEARCH_DEBOUNCE_MS = 300

interface TaskBoardOptionListProps {
  resource: string
  /** Names the list for assistive tech. */
  label: string
  selectedIds: number[]
  onSelect: (item: ForSelectItem) => void
  showAvatar?: boolean
  renderItem?: (item: ForSelectItem) => ReactNode
  /** Several options may be checked at once (assignees). */
  isMultiple?: boolean
}

export function TaskBoardOptionList({
  resource,
  label,
  selectedIds,
  onSelect,
  showAvatar = false,
  renderItem,
  isMultiple = false,
}: TaskBoardOptionListProps) {
  const { t } = useTranslation()
  const [search, setSearch] = useState('')
  const debouncedSearch = useDebouncedValue(search, SEARCH_DEBOUNCE_MS)

  const { data, isPending, isError, refetch, hasNextPage, isFetchingNextPage, fetchNextPage } = useForSelect({
    resource,
    search: debouncedSearch,
    ids: selectedIds.length > 0 ? selectedIds : undefined,
  })

  const options = flattenForSelectPages(data?.pages)

  return (
    <div className="flex w-72 max-w-[calc(100vw-2rem)] flex-col gap-1">
      <Input
        autoFocus
        value={search}
        onChange={(event: React.ChangeEvent<HTMLInputElement>) => setSearch(event.target.value)}
        placeholder={t('table.relationEditor.searchPlaceholder')}
        aria-label={t('table.relationEditor.searchPlaceholder')}
        className="h-7 text-xs"
      />

      <div role="listbox" aria-label={label} aria-multiselectable={isMultiple} className="max-h-64 overflow-y-auto">
        {isPending ? (
          <OptionsSkeleton showAvatar={showAvatar} />
        ) : isError ? (
          <div className="flex flex-col items-center gap-2 px-2 py-4 text-center">
            <p className="text-xs text-muted-foreground">{t('table.relationEditor.error')}</p>
            <button type="button" onClick={() => void refetch()} className="text-xs font-medium text-primary hover:underline">
              {t('table.relationEditor.retry')}
            </button>
          </div>
        ) : options.length === 0 ? (
          <p className="px-2 py-4 text-center text-xs text-muted-foreground">{t('table.relationEditor.empty')}</p>
        ) : (
          options.map((item) => (
            <AsyncSelectOptionRow
              key={item.id}
              item={item}
              checked={selectedIds.includes(item.id)}
              disabled={false}
              showAvatar={showAvatar}
              renderItem={renderItem}
              onSelect={onSelect}
            />
          ))
        )}
        {hasNextPage ? (
          <button
            type="button"
            disabled={isFetchingNextPage}
            onClick={() => void fetchNextPage()}
            className="w-full rounded-sm px-2 py-1.5 text-xs font-medium text-primary hover:bg-accent disabled:opacity-50"
          >
            {t('table.relationEditor.loadMore')}
          </button>
        ) : null}
      </div>
    </div>
  )
}
