import { useTranslation } from 'react-i18next'
import { Check, ListFilter, Lock, Pencil, Star, Trash2, Users } from 'lucide-react'
import { cn } from '@/lib/utils'
import { DropdownMenuItem } from '@/components/ui/dropdown-menu'
import type { TableFilterView } from '@/features/table/types'

interface FilterViewRowProps {
  view: TableFilterView
  active: boolean
  onApply: (view: TableFilterView) => void
  onDelete: (view: TableFilterView) => void
  onEditRules: (view: TableFilterView) => void
  onToggleFavorite: (view: TableFilterView) => void
  favoritePending: boolean
}

/**
 * One saved view row (spec 0007, extended by 0158): a star toggles the
 * actor's favorite (D-4), a leading lock/people glyph telegraphs private vs
 * shared, an optional filter glyph marks a "custom filter" view (`rules`
 * non-null), the name applies the view, an owner-only pencil re-opens the
 * rule builder for a custom filter, and an owner-only trash deletes it.
 */
export function FilterViewRow({
  view,
  active,
  onApply,
  onDelete,
  onEditRules,
  onToggleFavorite,
  favoritePending,
}: FilterViewRowProps) {
  const { t } = useTranslation()
  const VisibilityIcon = view.visibility === 'shared' ? Users : Lock
  const isCustomFilter = view.rules !== null

  return (
    <div className="group/row flex items-center gap-1">
      <button
        type="button"
        onClick={(event) => {
          event.stopPropagation()
          onToggleFavorite(view)
        }}
        disabled={favoritePending}
        aria-label={t(view.is_favorite ? 'table.unfavoriteView' : 'table.favoriteView')}
        aria-pressed={view.is_favorite}
        className="flex size-6 shrink-0 items-center justify-center rounded-sm text-muted-foreground transition-colors hover:bg-accent hover:text-foreground disabled:opacity-50"
      >
        <Star aria-hidden="true" className={cn('size-3.5', view.is_favorite && 'fill-current text-primary')} />
      </button>

      <DropdownMenuItem
        className={cn('min-w-0 flex-1 gap-2', active && 'bg-accent/60')}
        title={t('table.applyView')}
        onSelect={() => onApply(view)}
      >
        <VisibilityIcon aria-hidden="true" className="text-muted-foreground" />
        {isCustomFilter ? (
          <ListFilter aria-label={t('table.customFilters.rulesViewIndicator')} className="text-muted-foreground" />
        ) : null}
        <span className="truncate">{view.name}</span>
        <span className="ml-auto flex shrink-0 items-center gap-1.5">
          {active ? (
            <Check aria-label={t('table.viewActive')} className="size-3.5 text-primary" />
          ) : null}
          {!view.owned && view.owner_name ? (
            <span className="truncate text-xs text-muted-foreground">
              {t('table.sharedBy', { name: view.owner_name })}
            </span>
          ) : null}
        </span>
      </DropdownMenuItem>

      {view.owned && isCustomFilter ? (
        <DropdownMenuItem
          className="shrink-0 px-2 opacity-0 transition-opacity group-hover/row:opacity-100 focus:opacity-100"
          aria-label={t('table.customFilters.editRules')}
          onSelect={() => onEditRules(view)}
        >
          <Pencil aria-hidden="true" />
        </DropdownMenuItem>
      ) : null}

      {view.owned ? (
        <DropdownMenuItem
          variant="destructive"
          className="shrink-0 px-2 opacity-0 transition-opacity group-hover/row:opacity-100 focus:opacity-100"
          aria-label={t('table.deleteView')}
          onSelect={() => onDelete(view)}
        >
          <Trash2 aria-hidden="true" />
        </DropdownMenuItem>
      ) : null}
    </div>
  )
}
