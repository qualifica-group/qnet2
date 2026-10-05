import { useId, useRef, useState, type ComponentProps, type KeyboardEvent, type ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { Check, ChevronDown, Star } from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover'
import { Switch } from '@/components/ui/switch'
import { FORM_TAB_TRIGGER_CLASS } from '@/components/form-tab-strip'
import { cn } from '@/lib/utils'
import { filterCategoriesByName } from '@/features/request-management/category-tab-fit'
import type { RequestManagementProductCategory } from '@/features/request-management/types'

/**
 * The menu button reads as one more tab of the strip: same pill, same
 * compact type, raised like the active chip while its menu is open.
 */
const MORE_BUTTON_CLASS = cn(
  'inline-flex shrink-0 items-center font-medium whitespace-nowrap outline-none',
  'focus-visible:ring-[3px] focus-visible:ring-ring/50',
  FORM_TAB_TRIGGER_CLASS,
  'data-[state=open]:bg-card data-[state=open]:text-foreground data-[state=open]:shadow-sm',
)

/** Marks the category buttons the arrow keys move between. */
const OPTION_SELECTOR = '[data-category-option]'

interface CategoryMoreButtonProps extends ComponentProps<'button'> {
  hiddenCount: number
}

/**
 * The strip's menu button, also rendered bare in the measuring layer:
 * "Altre (N)" while tabs are folded, a compact star otherwise — it stays on the
 * strip even when every tab fits, because it also manages the favourites.
 */
export function CategoryMoreButton({ hiddenCount, className, ...props }: CategoryMoreButtonProps) {
  const { t } = useTranslation()
  const menuLabel = t('requestManagement.categoryTabs.menuLabel')

  return (
    <button
      type="button"
      title={menuLabel}
      aria-label={hiddenCount > 0 ? undefined : menuLabel}
      className={cn(MORE_BUTTON_CLASS, className)}
      {...props}
    >
      {hiddenCount > 0 ? t('requestManagement.categoryTabs.more', { count: hiddenCount }) : <Star aria-hidden="true" />}
      <ChevronDown aria-hidden="true" />
    </button>
  )
}

interface RequestManagementCategoryPickerProps {
  /** Every category, in the server's order. */
  categories: RequestManagementProductCategory[]
  favoriteCategoryIds: number[]
  showOnlyFavorites: boolean
  /** The favourites are the competence default, not saved yet (spec 0193): the menu says so. */
  favoritesAreDefault: boolean
  /** How many categories the strip does not show inline. */
  hiddenCount: number
  selectedCategoryId: number | null
  onSelect: (categoryId: number) => void
  onToggleFavorite: (categoryId: number) => void
  onShowOnlyFavoritesChange: (showOnlyFavorites: boolean) => void
}

/**
 * The strip's menu (priority+ navigation, as in the CRMs that show "More"
 * after the views that fit): every category with its request count, a
 * type-ahead filter, a star per category to keep it among the favourites
 * (spec 0184) and the "only favourites" switch. The favourites group is
 * taken when the menu opens, so a star clicked while it is open fills at once
 * but never moves the row from under the pointer. Arrow keys move from the
 * search into the list and through it; Enter in the search picks the first
 * match.
 */
export function RequestManagementCategoryPicker({
  categories,
  favoriteCategoryIds,
  showOnlyFavorites,
  favoritesAreDefault,
  hiddenCount,
  selectedCategoryId,
  onSelect,
  onToggleFavorite,
  onShowOnlyFavoritesChange,
}: RequestManagementCategoryPickerProps) {
  const { t } = useTranslation()
  const switchId = useId()
  const searchRef = useRef<HTMLInputElement>(null)
  const listRef = useRef<HTMLDivElement>(null)
  const [open, setOpen] = useState(false)
  const [search, setSearch] = useState('')
  const [groupedFavoriteIds, setGroupedFavoriteIds] = useState(favoriteCategoryIds)

  const matches = filterCategoriesByName(categories, search)
  const isFavorite = (category: RequestManagementProductCategory) => favoriteCategoryIds.includes(category.id)
  const isGroupedFavorite = (category: RequestManagementProductCategory) => groupedFavoriteIds.includes(category.id)
  const favorites = matches.filter(isGroupedFavorite)
  const others = matches.filter((category) => !isGroupedFavorite(category))
  const hasFavorites = categories.some(isFavorite)

  const handleOpenChange = (next: boolean) => {
    setOpen(next)
    setSearch('')
    setGroupedFavoriteIds(favoriteCategoryIds)
  }

  const pick = (categoryId: number) => {
    onSelect(categoryId)
    handleOpenChange(false)
  }

  const focusOption = (index: number) => {
    listRef.current?.querySelectorAll<HTMLButtonElement>(OPTION_SELECTOR)[index]?.focus()
  }

  const handleSearchKeyDown = (event: KeyboardEvent<HTMLInputElement>) => {
    if (event.key === 'ArrowDown') {
      event.preventDefault()
      focusOption(0)
    } else if (event.key === 'Enter' && matches.length > 0) {
      event.preventDefault()
      pick(matches[0].id)
    }
  }

  const handleListKeyDown = (event: KeyboardEvent<HTMLDivElement>) => {
    if (event.key !== 'ArrowDown' && event.key !== 'ArrowUp') return
    event.preventDefault()
    const options = Array.from(listRef.current?.querySelectorAll<HTMLButtonElement>(OPTION_SELECTOR) ?? [])
    const current = options.findIndex((option) => option === document.activeElement)
    const next = current + (event.key === 'ArrowDown' ? 1 : -1)
    if (next < 0) {
      searchRef.current?.focus()
    } else {
      focusOption(Math.min(next, options.length - 1))
    }
  }

  const renderRows = (rows: RequestManagementProductCategory[]) =>
    rows.map((category) => (
      <CategoryRow
        key={category.id}
        category={category}
        favorite={isFavorite(category)}
        selected={category.id === selectedCategoryId}
        onPick={pick}
        onToggleFavorite={onToggleFavorite}
      />
    ))

  return (
    <Popover open={open} onOpenChange={handleOpenChange}>
      <PopoverTrigger asChild>
        <CategoryMoreButton hiddenCount={hiddenCount} />
      </PopoverTrigger>
      <PopoverContent align="end" className="w-72 max-w-[calc(100vw-2rem)] p-1">
        <div className="p-1">
          <Input
            ref={searchRef}
            autoFocus
            aria-label={t('requestManagement.categoryTabs.searchPlaceholder')}
            placeholder={t('requestManagement.categoryTabs.searchPlaceholder')}
            value={search}
            onChange={(event) => setSearch(event.target.value)}
            onKeyDown={handleSearchKeyDown}
            className="h-8 text-xs"
          />
        </div>
        <div ref={listRef} onKeyDown={handleListKeyDown} className="max-h-72 overflow-y-auto p-1">
          {matches.length === 0 ? (
            <p className="px-2 py-4 text-center text-xs text-muted-foreground">
              {t('requestManagement.categoryTabs.noMatch')}
            </p>
          ) : favorites.length > 0 ? (
            <>
              <CategoryGroup label={t('requestManagement.categoryTabs.favorites')}>{renderRows(favorites)}</CategoryGroup>
              {others.length > 0 ? (
                <CategoryGroup label={t('requestManagement.categoryTabs.otherCategories')}>
                  {renderRows(others)}
                </CategoryGroup>
              ) : null}
            </>
          ) : (
            <ul aria-label={t('requestManagement.categoryTabs.pickerLabel')}>{renderRows(matches)}</ul>
          )}
        </div>
        <div className="flex items-center justify-between gap-2 border-t border-border px-2 py-2">
          <Label htmlFor={switchId} className="text-xs font-normal">
            {t('requestManagement.categoryTabs.showOnlyFavorites')}
          </Label>
          <Switch
            id={switchId}
            checked={showOnlyFavorites}
            disabled={!hasFavorites}
            onCheckedChange={onShowOnlyFavoritesChange}
          />
        </div>
        {hasFavorites && !favoritesAreDefault ? null : (
          <p className="px-2 pb-2 text-[0.65rem] text-muted-foreground">
            {t(
              hasFavorites
                ? 'requestManagement.categoryTabs.favoritesDefaultHint'
                : 'requestManagement.categoryTabs.favoritesHint',
            )}
          </p>
        )}
      </PopoverContent>
    </Popover>
  )
}

function CategoryGroup({ label, children }: { label: string; children: ReactNode }) {
  const headingId = useId()

  return (
    <div className="not-first:mt-1">
      <p id={headingId} className="px-2 py-1 text-[0.65rem] font-medium tracking-wide text-muted-foreground uppercase">
        {label}
      </p>
      <ul aria-labelledby={headingId}>{children}</ul>
    </div>
  )
}

interface CategoryRowProps {
  category: RequestManagementProductCategory
  favorite: boolean
  selected: boolean
  onPick: (categoryId: number) => void
  onToggleFavorite: (categoryId: number) => void
}

function CategoryRow({ category, favorite, selected, onPick, onToggleFavorite }: CategoryRowProps) {
  const { t } = useTranslation()

  return (
    <li className="flex items-center gap-0.5">
      <button
        type="button"
        onClick={() => onToggleFavorite(category.id)}
        aria-pressed={favorite}
        aria-label={t(
          favorite ? 'requestManagement.categoryTabs.removeFavorite' : 'requestManagement.categoryTabs.addFavorite',
          { name: category.name },
        )}
        className="flex size-7 shrink-0 items-center justify-center rounded-sm text-muted-foreground outline-none transition-colors hover:bg-accent hover:text-foreground focus-visible:ring-[2px] focus-visible:ring-ring/50"
      >
        <Star aria-hidden="true" className={cn('size-3.5', favorite && 'fill-favorite text-favorite-border')} />
      </button>
      <button
        type="button"
        data-category-option=""
        aria-current={selected || undefined}
        onClick={() => onPick(category.id)}
        className={cn(
          'flex min-w-0 flex-1 items-center gap-2 rounded-sm px-2 py-1.5 text-left text-xs outline-none',
          'hover:bg-accent focus-visible:bg-accent focus-visible:ring-[2px] focus-visible:ring-ring/50',
          selected && 'font-medium text-primary',
        )}
      >
        <span className="min-w-0 flex-1 truncate" title={category.name}>
          {category.name}
        </span>
        <Badge variant="secondary" className="px-1.5 py-0 text-[0.65rem]">
          {category.requests_count}
        </Badge>
        <Check className={cn('size-3.5 shrink-0', !selected && 'invisible')} aria-hidden="true" />
      </button>
    </li>
  )
}
