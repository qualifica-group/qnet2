import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Bookmark, BookmarkPlus, ListFilter, Plus } from 'lucide-react'
import axios from 'axios'
import { toast } from 'sonner'
import { Button } from '@/components/ui/button'
import { useConfirm } from '@/components/confirm-dialog-context'
import { Input } from '@/components/ui/input'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import {
  Tooltip,
  TooltipContent,
  TooltipTrigger,
} from '@/components/ui/tooltip'
import { FilterViewRow } from '@/features/table/filter-view-row'
import { FilterViewVisibilityPicker } from '@/features/table/filter-view-visibility-picker'
import {
  useCreateFilterView,
  useDeleteFilterView,
  useFilterViews,
  useToggleFilterViewFavorite,
} from '@/features/table/use-filter-views'
import type { FilterRules, FilterViewVisibility, TableFilterView } from '@/features/table/types'
import type { AdvancedFilterValues } from '@/features/table/advanced-filters/types'

/** Server-side max length for a saved filter view's name (spec 0007). */
const VIEW_NAME_MAX_LENGTH = 80

interface FilterViewsControlProps {
  /** Domain key selecting the server-side table definition (e.g. "users"). */
  domain: string
  /** Current AG Grid filterModel, saved verbatim as a new view's filters. */
  currentFilters: Record<string, unknown>
  /**
   * Current active advanced filters (spec 0032 AC-009), saved alongside
   * `currentFilters` as a new view's `advancedFilters`.
   */
  currentAdvancedFilters: AdvancedFilterValues
  /**
   * Applies a saved view's filters to the grid AND its advanced filters to
   * `useAdvancedFilters` (the caller wires `setFilterModel`/`applyValues`).
   */
  onApply: (filters: Record<string, unknown>, advancedFilters: AdvancedFilterValues) => void
  /** Activates a view's custom filter rules instead (spec 0158 D-2). */
  onApplyRules: (rules: FilterRules, meta: { viewId?: number; name?: string }) => void
  /** Opens the rule builder for a brand-new custom filter. */
  onNewCustomFilter: () => void
  /** Opens the rule builder pre-filled with an owned view's rules. */
  onEditCustomFilter: (view: TableFilterView) => void
  /** The view id of the currently-active custom filter, if any (highlights its row). */
  activeCustomFilterViewId?: number
  /** Spec 0158 D-3: gates the "Condivisa" visibility option. */
  canPublish: boolean
}

/**
 * Order-independent equality of two filter models: same keys, same value per
 * key. Lets the panel flag which saved view is the one currently applied.
 */
function sameFilters(a: Record<string, unknown>, b: Record<string, unknown>): boolean {
  const keys = Object.keys(a)
  if (keys.length !== Object.keys(b).length) {
    return false
  }
  return keys.every((key) => key in b && JSON.stringify(a[key]) === JSON.stringify(b[key]))
}

/** Splits the server-ordered view list into favorites / owned / shared-by-others (spec 0158 D-4). */
function partitionViews(views: TableFilterView[]) {
  const favorites = views.filter((view) => view.is_favorite)
  const owned = views.filter((view) => view.owned && !view.is_favorite)
  const shared = views.filter((view) => !view.owned && !view.is_favorite)
  return { favorites, owned, shared }
}

/**
 * Toolbar control that both LISTS the actor's saved filter views (own + others'
 * shared, grouped) and SAVES the current filter set — all inline in one panel,
 * no modal. Applying/deleting are pure wiring (the grid mutation lives in the
 * caller via `onApply`); saving posts the caller-supplied `currentFilters`.
 * Also the entry point for custom filters (spec 0158): "Nuovo filtro
 * personalizzato", editing an owned custom-filter view, and starring
 * favorites — the rule builder dialog itself is mounted by the caller.
 */
export function FilterViewsControl({
  domain,
  currentFilters,
  currentAdvancedFilters,
  onApply,
  onApplyRules,
  onNewCustomFilter,
  onEditCustomFilter,
  activeCustomFilterViewId,
  canPublish,
}: FilterViewsControlProps) {
  const { t } = useTranslation()
  const confirm = useConfirm()
  const { data: views } = useFilterViews(domain)
  const createView = useCreateFilterView(domain)
  const deleteView = useDeleteFilterView(domain)
  const toggleFavorite = useToggleFilterViewFavorite(domain)

  const [open, setOpen] = useState(false)
  const [name, setName] = useState('')
  const [visibility, setVisibility] = useState<FilterViewVisibility>('private')

  const { favorites, owned, shared } = partitionViews(views ?? [])
  const hasViews = favorites.length + owned.length + shared.length > 0
  const count = favorites.length + owned.length + shared.length

  // Either a column filter or an advanced filter is enough to offer saving a
  // view (spec 0032 AC-009: a view can be advanced-filters-only).
  const hasFilters =
    Object.keys(currentFilters).length > 0 || Object.keys(currentAdvancedFilters).length > 0
  const canSave = name.trim().length > 0 && hasFilters && !createView.isPending

  const resetForm = () => {
    setName('')
    setVisibility('private')
  }

  const handleOpenChange = (next: boolean) => {
    setOpen(next)
    if (!next) {
      resetForm()
    }
  }

  const handleApply = (view: TableFilterView) => {
    if (view.rules) {
      onApplyRules(view.rules, { viewId: view.id, name: view.name })
      return
    }
    onApply(view.filters, view.advanced_filters)
  }

  const isActiveView = (view: TableFilterView) =>
    view.rules
      ? view.id === activeCustomFilterViewId
      : sameFilters(view.filters, currentFilters) && sameFilters(view.advanced_filters, currentAdvancedFilters)

  const handleDelete = async (view: TableFilterView) => {
    const confirmed = await confirm({
      tone: 'destructive',
      title: t('table.deleteView'),
      description: t('table.confirmAction'),
    })
    if (!confirmed) {
      return
    }
    try {
      await deleteView.mutateAsync(view.id)
      toast.success(t('table.viewDeleted'))
    } catch {
      toast.error(t('table.viewDeleteError'))
    }
  }

  const handleToggleFavorite = async (view: TableFilterView) => {
    try {
      await toggleFavorite.mutateAsync({ id: view.id, isFavorite: view.is_favorite })
    } catch {
      toast.error(t('table.customFilters.favoriteError'))
    }
  }

  const handleSave = async () => {
    if (!canSave) {
      return
    }
    try {
      await createView.mutateAsync({
        name: name.trim(),
        filters: currentFilters,
        advancedFilters: currentAdvancedFilters,
        visibility,
      })
      toast.success(t('table.viewSaved'))
      resetForm()
      setOpen(false)
    } catch (error) {
      const isDuplicateName =
        axios.isAxiosError(error) &&
        error.response?.status === 422 &&
        Boolean(error.response.data?.errors?.name)
      const isForbidden = axios.isAxiosError(error) && error.response?.status === 403
      toast.error(
        t(isDuplicateName ? 'table.duplicateViewName' : isForbidden ? 'table.customFilters.publishForbidden' : 'table.viewSaveError'),
      )
    }
  }

  const rowProps = (view: TableFilterView) => ({
    view,
    active: isActiveView(view),
    onApply: handleApply,
    onDelete: (target: TableFilterView) => void handleDelete(target),
    onEditRules: onEditCustomFilter,
    onToggleFavorite: (target: TableFilterView) => void handleToggleFavorite(target),
    favoritePending: toggleFavorite.isPending,
  })

  return (
    <DropdownMenu open={open} onOpenChange={handleOpenChange}>
      <Tooltip>
        <TooltipTrigger asChild>
          <DropdownMenuTrigger asChild>
            <Button
              variant="ghost"
              size="icon-sm"
              className="relative text-muted-foreground hover:text-foreground"
              aria-label={t('table.savedFilters')}
            >
              <Bookmark aria-hidden="true" />
              {count > 0 ? (
                <span className="absolute -right-1 -top-1 inline-flex h-4 min-w-4 items-center justify-center rounded-full bg-primary px-1 text-[10px] font-semibold text-primary-foreground">
                  {count}
                </span>
              ) : null}
            </Button>
          </DropdownMenuTrigger>
        </TooltipTrigger>
        <TooltipContent>{t('table.savedFilters')}</TooltipContent>
      </Tooltip>

      <DropdownMenuContent align="start" className="w-80 p-0">
        <div className="flex items-center gap-2 border-b px-3 py-2.5">
          <div className="flex size-7 shrink-0 items-center justify-center rounded-md bg-primary/10 text-primary">
            <Bookmark aria-hidden="true" className="size-4" />
          </div>
          <div className="min-w-0">
            <p className="text-sm font-semibold leading-tight">{t('table.savedFilters')}</p>
            <p className="truncate text-xs text-muted-foreground">
              {t('table.savedFiltersSubtitle')}
            </p>
          </div>
        </div>

        <DropdownMenuItem
          onSelect={(event) => {
            event.preventDefault()
            setOpen(false)
            onNewCustomFilter()
          }}
        >
          <ListFilter aria-hidden="true" />
          {t('table.customFilters.newCustomFilter')}
          <Plus aria-hidden="true" className="ml-auto size-3.5" />
        </DropdownMenuItem>
        <DropdownMenuSeparator />

        <div className="max-h-64 overflow-y-auto p-1">
          {hasViews ? null : (
            <div className="flex flex-col items-center gap-1 px-3 py-6 text-center">
              <Bookmark aria-hidden="true" className="size-5 text-muted-foreground/60" />
              <p className="text-xs text-muted-foreground">{t('table.noSavedViews')}</p>
            </div>
          )}

          {favorites.length > 0 ? (
            <>
              <DropdownMenuLabel className="text-[11px] font-medium uppercase tracking-wide text-muted-foreground">
                {t('table.favoriteViews')}
              </DropdownMenuLabel>
              {favorites.map((view) => (
                <FilterViewRow key={view.id} {...rowProps(view)} />
              ))}
            </>
          ) : null}

          {owned.length > 0 ? (
            <>
              {favorites.length > 0 ? <DropdownMenuSeparator /> : null}
              <DropdownMenuLabel className="text-[11px] font-medium uppercase tracking-wide text-muted-foreground">
                {t('table.myViews')}
              </DropdownMenuLabel>
              {owned.map((view) => (
                <FilterViewRow key={view.id} {...rowProps(view)} />
              ))}
            </>
          ) : null}

          {shared.length > 0 ? (
            <>
              {favorites.length + owned.length > 0 ? <DropdownMenuSeparator /> : null}
              <DropdownMenuLabel className="text-[11px] font-medium uppercase tracking-wide text-muted-foreground">
                {t('table.sharedViews')}
              </DropdownMenuLabel>
              {shared.map((view) => (
                <FilterViewRow key={view.id} {...rowProps(view)} />
              ))}
            </>
          ) : null}
        </div>

        {/* Inline save panel. Radix Menu grabs keystrokes for typeahead and
            closes on Tab; stopping propagation (except Escape) lets the form
            be typed and tabbed through while the panel stays open. */}
        <div
          className="border-t bg-muted/40 px-3 py-3"
          onKeyDown={(event) => {
            if (event.key !== 'Escape') {
              event.stopPropagation()
            }
          }}
        >
          <div className="mb-2 flex items-center gap-1.5">
            <BookmarkPlus aria-hidden="true" className="size-3.5 text-muted-foreground" />
            <span className="text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">
              {t('table.saveViewHeading')}
            </span>
          </div>

          {hasFilters ? (
            <div className="flex flex-col gap-2">
              <Input
                value={name}
                onChange={(event) => setName(event.target.value)}
                onKeyDown={(event) => {
                  if (event.key === 'Enter') {
                    event.preventDefault()
                    void handleSave()
                  }
                }}
                placeholder={t('table.viewNamePlaceholder')}
                aria-label={t('table.viewNamePlaceholder')}
                autoComplete="off"
                maxLength={VIEW_NAME_MAX_LENGTH}
                className="h-8"
              />

              <FilterViewVisibilityPicker value={visibility} onChange={setVisibility} canPublish={canPublish} />

              <Button size="sm" className="w-full" disabled={!canSave} onClick={() => void handleSave()}>
                <BookmarkPlus aria-hidden="true" />
                {t('table.saveView')}
              </Button>
            </div>
          ) : (
            <p className="text-xs text-muted-foreground">{t('table.applyFilterToSaveHint')}</p>
          )}
        </div>
      </DropdownMenuContent>
    </DropdownMenu>
  )
}
