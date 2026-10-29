import { useMemo, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { SearchableSelect } from '@/components/ui/searchable-select'
import { useDebouncedValue } from '@/hooks/use-debounced-value'
import { flattenForSelectPages } from '@/features/for-select/use-for-select'
import {
  isWorkOrderStatusItem,
  useWorkOrderStatusesForSelect,
  useWorkOrderStatusLabels,
} from '@/features/task-templates/work-order-status-for-select-api'
import { buildGroupedStatusOptions } from '@/features/task-templates/work-order-status-options'

interface TaskTemplateItemWorkOrderStatusSelectProps {
  value: number | null
  onChange: (value: number | null) => void
  disabled?: boolean
  'aria-describedby'?: string
  'aria-invalid'?: boolean
}

/**
 * Single-select for the work order working status a template row links to
 * (spec 0323 D-5): every status of every workflow plus the default set,
 * grouped by workflow, with the advancement % beside the name. Clearable
 * (the link is optional). Composed on `SearchableSelect` fed by the
 * `work-order-workflow-statuses` for-select; the saved value is labelled by
 * `useWorkOrderStatusLabels`, its own cache entry.
 */
export function TaskTemplateItemWorkOrderStatusSelect({
  value,
  onChange,
  disabled,
  'aria-describedby': ariaDescribedBy,
  'aria-invalid': ariaInvalid,
}: TaskTemplateItemWorkOrderStatusSelectProps) {
  const { t } = useTranslation()
  const [search, setSearch] = useState('')
  const debouncedSearch = useDebouncedValue(search.trim())

  const query = useWorkOrderStatusesForSelect({ search: debouncedSearch })
  const selectedIds = useMemo(() => (value !== null ? [value] : []), [value])
  const selectedLabels = useWorkOrderStatusLabels(selectedIds)

  const options = useMemo(() => {
    const listed = flattenForSelectPages(query.data?.pages).filter(isWorkOrderStatusItem)
    const offPage = [...selectedLabels.values()]
      .filter(isWorkOrderStatusItem)
      .filter((status) => !listed.some((item) => item.id === status.id))
    return buildGroupedStatusOptions([...listed, ...offPage], t)
  }, [query.data?.pages, selectedLabels, t])

  return (
    <SearchableSelect
      value={value}
      onChange={onChange}
      onClear={() => onChange(null)}
      options={options}
      filter={false}
      onSearchChange={setSearch}
      isPending={query.isPending}
      isError={query.isError}
      onRetry={() => void query.refetch()}
      hasNextPage={query.hasNextPage}
      isFetchingNextPage={query.isFetchingNextPage}
      onLoadMore={() => void query.fetchNextPage()}
      disabled={disabled}
      aria-describedby={ariaDescribedBy}
      aria-invalid={ariaInvalid}
      labels={{
        placeholder: t('taskTemplates.form.items.workOrderStatusPlaceholder'),
        searchPlaceholder: t('taskTemplates.form.items.workOrderStatusSearchPlaceholder'),
        empty: t('taskTemplates.form.items.workOrderStatusEmpty'),
        noMatch: t('taskTemplates.form.items.workOrderStatusEmpty'),
        error: t('taskTemplates.form.items.workOrderStatusError'),
        retry: t('common.retry'),
        triggerLabel: t('taskTemplates.form.items.workOrderStatus'),
        clearLabel: t('taskTemplates.form.items.workOrderStatusClear'),
      }}
    />
  )
}
