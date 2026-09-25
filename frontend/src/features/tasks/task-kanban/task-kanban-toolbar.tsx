/**
 * The Kanban's own compact toolbar (spec 0157 D-4): search box + the SAME
 * advanced-filters panel the list uses (`AdvancedFilterPanel`), collapsed by
 * default. No grid, no saved views, no export — only what `useTaskKanbanRows`
 * itself owns.
 */
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Filter, Search, X } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Collapsible, CollapsibleContent } from '@/components/ui/collapsible'
import { Input } from '@/components/ui/input'
import { AdvancedFilterPanel, ADVANCED_FILTER_PANEL_ANIMATION } from '@/features/table/advanced-filters/advanced-filter-panel'
import type { UseTaskKanbanFiltersResult } from '@/features/tasks/task-kanban/use-task-kanban-filters'

interface TaskKanbanToolbarProps {
  data: Pick<UseTaskKanbanFiltersResult, 'search' | 'setSearch' | 'descriptors' | 'advancedFilters'>
}

export function TaskKanbanToolbar({ data }: TaskKanbanToolbarProps) {
  const { t } = useTranslation()
  const [filtersOpen, setFiltersOpen] = useState(false)
  const { search, setSearch, descriptors, advancedFilters } = data

  return (
    <div className="flex flex-col gap-2 rounded-xl border border-border bg-card">
      <div className="flex flex-wrap items-center gap-2 px-2.5 py-2">
        <div className="relative w-full max-w-xs">
          <Search aria-hidden="true" className="pointer-events-none absolute left-2.5 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
          <Input
            type="search"
            value={search}
            onChange={(event) => setSearch(event.target.value)}
            placeholder={t('table.search')}
            aria-label={t('table.search')}
            autoComplete="off"
            className="h-9 border-transparent bg-muted/60 pl-8 pr-8 shadow-none focus-visible:border-ring focus-visible:bg-card [&::-webkit-search-cancel-button]:hidden"
          />
          {search ? (
            <button
              type="button"
              onClick={() => setSearch('')}
              aria-label={t('common.clear')}
              className="absolute right-2 top-1/2 flex size-5 -translate-y-1/2 items-center justify-center rounded-sm text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
            >
              <X aria-hidden="true" className="size-3.5" />
            </button>
          ) : null}
        </div>

        {descriptors.length > 0 ? (
          <Button
            variant="outline"
            size="sm"
            className="bg-card"
            aria-pressed={filtersOpen}
            onClick={() => setFiltersOpen((current) => !current)}
          >
            <Filter aria-hidden="true" />
            {t('table.advancedFilters.toggle')}
            {advancedFilters.activeCount > 0 ? (
              <span className="ml-1 rounded-full bg-primary px-1.5 text-[10px] font-semibold text-primary-foreground">
                {advancedFilters.activeCount}
              </span>
            ) : null}
          </Button>
        ) : null}
      </div>

      {descriptors.length > 0 ? (
        <Collapsible open={filtersOpen}>
          <CollapsibleContent className={ADVANCED_FILTER_PANEL_ANIMATION}>
            <AdvancedFilterPanel descriptors={descriptors} filters={advancedFilters} />
          </CollapsibleContent>
        </Collapsible>
      ) : null}
    </div>
  )
}
