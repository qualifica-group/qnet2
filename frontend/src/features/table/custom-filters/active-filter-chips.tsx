import { X } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import type { FilterChip } from '@/features/table/custom-filters/use-active-filter-chips'

interface ActiveFilterChipsProps {
  chips: FilterChip[]
  onClearAll: () => void
}

/**
 * The row of removable filter chips below the toolbar (spec 0158 D-5): one
 * chip per active column filter / advanced filter / search term / custom
 * filter, each with its own "x", plus a trailing "Azzera tutto". Renders
 * nothing when there is no active filter. Compact by default (ui-design.md
 * §2): `text-xs`, `size-3.5` icons, a `size-6` (24px) tap target on the "x".
 */
export function ActiveFilterChips({ chips, onClearAll }: ActiveFilterChipsProps) {
  const { t } = useTranslation()

  if (chips.length === 0) {
    return null
  }

  return (
    <div
      role="list"
      aria-label={t('table.customFilters.activeFiltersLabel')}
      className="flex flex-wrap items-center gap-1.5 border-b border-border bg-card px-2.5 py-1.5"
    >
      {chips.map((chip) => (
        <span
          key={chip.id}
          role="listitem"
          className="flex max-w-full items-center gap-1 rounded-md bg-muted px-2 py-0.5 text-xs font-medium text-foreground"
        >
          <span className="truncate">{chip.label}</span>
          <button
            type="button"
            onClick={chip.onRemove}
            aria-label={t('table.customFilters.removeChip', { label: chip.label })}
            className="flex size-6 shrink-0 items-center justify-center rounded-sm text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
          >
            <X aria-hidden="true" className="size-3.5" />
          </button>
        </span>
      ))}

      <Button type="button" variant="ghost" size="xs" onClick={onClearAll}>
        {t('table.customFilters.clearAll')}
      </Button>
    </div>
  )
}
