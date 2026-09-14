import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { X } from 'lucide-react'
import { SearchableSelect } from '@/components/ui/searchable-select'
import { useDebouncedValue } from '@/hooks/use-debounced-value'
import { flattenForSelectPages } from '@/features/for-select/use-for-select'
import { useTaskStatusesForSelect } from '@/features/task-statuses/for-select-api'
import type { TaskStatusForSelectItem } from '@/features/task-statuses/for-select-api'

/** Row initial-status groups a template may target (spec 0124 D-4): the backend enforces the same allow-list on save. */
const ALLOWED_STATUS_GROUPS = new Set(['open', 'pending'])

interface TaskTemplateItemStatusSelectProps {
  value: number | null
  onChange: (value: number | null) => void
  disabled?: boolean
  id?: string
  'aria-describedby'?: string
  'aria-invalid'?: boolean
}

/**
 * Single-select for a template row's initial task status (spec 0124 D-4),
 * filtered client-side to `meta.group` `open`/`pending`. Reuses the existing
 * `task-statuses` for-select surface (`features/task-statuses/for-select-api`)
 * rather than `AsyncPaginatedSelect`: that component only DISABLES an option
 * (`isItemDisabled`), it never hides one, and a wrong-group status must never
 * be pickable here. `SearchableSelect` accepts a plain, already-filtered
 * option list, fed by the same paginated for-select query (server search via
 * `onSearchChange`, infinite scroll via `hasNextPage`/`onLoadMore`). A
 * dedicated clear button rides the `action` slot: the field is nullable
 * (D-4), but `SearchableSelect` itself has no clear affordance once a value
 * is picked.
 */
export function TaskTemplateItemStatusSelect({
  value,
  onChange,
  disabled,
  id,
  'aria-describedby': ariaDescribedBy,
  'aria-invalid': ariaInvalid,
}: TaskTemplateItemStatusSelectProps) {
  const { t } = useTranslation()
  const [search, setSearch] = useState('')
  const debouncedSearch = useDebouncedValue(search.trim())

  const query = useTaskStatusesForSelect({
    search: debouncedSearch,
    ids: value !== null ? [value] : undefined,
  })

  const options = flattenForSelectPages(query.data?.pages)
    .filter(
      (item): item is TaskStatusForSelectItem =>
        'meta' in item && ALLOWED_STATUS_GROUPS.has((item as TaskStatusForSelectItem).meta.group),
    )
    .map((item) => ({ id: item.id, name: item.label }))

  const clearAction =
    value !== null && !disabled ? (
      <button
        type="button"
        aria-label={t('taskTemplates.form.items.statusClear')}
        className="shrink-0 rounded-sm p-1 text-muted-foreground outline-none hover:text-foreground focus-visible:ring-[2px] focus-visible:ring-ring/50"
        onClick={() => onChange(null)}
      >
        <X className="size-3.5" aria-hidden="true" />
      </button>
    ) : null

  return (
    <SearchableSelect
      value={value}
      onChange={onChange}
      options={options}
      filter={false}
      onSearchChange={setSearch}
      isPending={query.isPending}
      isError={query.isError}
      onRetry={() => void query.refetch()}
      hasNextPage={query.hasNextPage}
      isFetchingNextPage={query.isFetchingNextPage}
      onLoadMore={() => void query.fetchNextPage()}
      action={clearAction}
      disabled={disabled}
      id={id}
      aria-describedby={ariaDescribedBy}
      aria-invalid={ariaInvalid}
      labels={{
        placeholder: t('taskTemplates.form.items.statusPlaceholder'),
        searchPlaceholder: t('taskTemplates.form.items.statusSearchPlaceholder'),
        empty: t('taskTemplates.form.items.statusEmpty'),
        noMatch: t('taskTemplates.form.items.statusEmpty'),
        error: t('taskTemplates.form.items.statusError'),
        retry: t('common.retry'),
        // The row has no `<label htmlFor>` association (it sits in a compact
        // 3-column grid alongside the estimate/due-offset inputs, mirrored
        // visually by a plain `<Label>`, not a `FormControl` slot) — the
        // trigger's own accessible name carries the field's identity instead.
        triggerLabel: t('taskTemplates.form.items.status'),
      }}
    />
  )
}
