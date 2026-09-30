import { useId, useState, type ComponentProps, type KeyboardEvent } from 'react'
import { useTranslation } from 'react-i18next'
import { Check, ChevronDown } from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import { Input } from '@/components/ui/input'
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover'
import { FORM_TAB_TRIGGER_CLASS } from '@/components/form-tab-strip'
import { cn } from '@/lib/utils'
import { filterCategoriesByName } from '@/features/request-management/category-tab-fit'
import type { RequestManagementProductCategory } from '@/features/request-management/types'

/**
 * The "Altre" button reads as one more tab of the strip: same pill, same
 * compact type, raised like the active chip while its menu is open.
 */
const MORE_BUTTON_CLASS = cn(
  'inline-flex shrink-0 items-center font-medium whitespace-nowrap outline-none',
  'focus-visible:ring-[3px] focus-visible:ring-ring/50',
  FORM_TAB_TRIGGER_CLASS,
  'data-[state=open]:bg-card data-[state=open]:text-foreground data-[state=open]:shadow-sm',
)

interface CategoryMoreButtonProps extends ComponentProps<'button'> {
  hiddenCount: number
}

/** The "Altre (N)" button, also rendered bare in the strip's measuring layer. */
export function CategoryMoreButton({ hiddenCount, className, ...props }: CategoryMoreButtonProps) {
  const { t } = useTranslation()

  return (
    <button type="button" className={cn(MORE_BUTTON_CLASS, className)} {...props}>
      {t('requestManagement.categoryTabs.more', { count: hiddenCount })}
      <ChevronDown aria-hidden="true" />
    </button>
  )
}

interface RequestManagementCategoryPickerProps {
  categories: RequestManagementProductCategory[]
  /** How many categories the strip could not show inline. */
  hiddenCount: number
  selectedCategoryId: number | null
  onSelect: (categoryId: number) => void
}

/**
 * The strip's overflow menu (priority+ navigation, as in the CRMs that show
 * "More" after the views that fit): every category — not only the folded ones,
 * so the operator searches one list — with its request count, a type-ahead
 * filter and full keyboard use (arrows, Enter, Esc).
 */
export function RequestManagementCategoryPicker({
  categories,
  hiddenCount,
  selectedCategoryId,
  onSelect,
}: RequestManagementCategoryPickerProps) {
  const { t } = useTranslation()
  const listboxId = useId()
  const [open, setOpen] = useState(false)
  const [search, setSearch] = useState('')
  const [activeIndex, setActiveIndex] = useState(0)

  const matches = filterCategoriesByName(categories, search)
  const optionId = (categoryId: number) => `${listboxId}-${categoryId}`
  const activeCategory = matches[Math.min(activeIndex, matches.length - 1)]

  const handleOpenChange = (next: boolean) => {
    setOpen(next)
    setSearch('')
    // Reopen on the current category, so Enter right away keeps it.
    setActiveIndex(Math.max(0, categories.findIndex((category) => category.id === selectedCategoryId)))
  }

  const pick = (categoryId: number) => {
    onSelect(categoryId)
    handleOpenChange(false)
  }

  const moveActive = (next: number) => {
    const index = Math.min(Math.max(next, 0), matches.length - 1)
    setActiveIndex(index)
    document.getElementById(optionId(matches[index].id))?.scrollIntoView({ block: 'nearest' })
  }

  const handleKeyDown = (event: KeyboardEvent<HTMLInputElement>) => {
    if (matches.length === 0) return
    if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
      event.preventDefault()
      moveActive(activeIndex + (event.key === 'ArrowDown' ? 1 : -1))
    } else if (event.key === 'Enter' && activeCategory) {
      event.preventDefault()
      pick(activeCategory.id)
    }
  }

  return (
    <Popover open={open} onOpenChange={handleOpenChange}>
      <PopoverTrigger asChild>
        <CategoryMoreButton hiddenCount={hiddenCount} />
      </PopoverTrigger>
      <PopoverContent align="end" className="w-72 max-w-[calc(100vw-2rem)] p-1">
        <div className="p-1">
          <Input
            autoFocus
            role="combobox"
            aria-expanded="true"
            aria-controls={listboxId}
            aria-activedescendant={activeCategory ? optionId(activeCategory.id) : undefined}
            aria-label={t('requestManagement.categoryTabs.searchPlaceholder')}
            placeholder={t('requestManagement.categoryTabs.searchPlaceholder')}
            value={search}
            onChange={(event) => {
              setSearch(event.target.value)
              setActiveIndex(0)
            }}
            onKeyDown={handleKeyDown}
            className="h-8 text-xs"
          />
        </div>
        <div
          id={listboxId}
          role="listbox"
          aria-label={t('requestManagement.categoryTabs.pickerLabel')}
          className="max-h-72 overflow-y-auto p-1"
        >
          {matches.length === 0 ? (
            <p className="px-2 py-4 text-center text-xs text-muted-foreground">
              {t('requestManagement.categoryTabs.noMatch')}
            </p>
          ) : (
            matches.map((category) => {
              const selected = category.id === selectedCategoryId
              return (
                <div
                  key={category.id}
                  id={optionId(category.id)}
                  role="option"
                  aria-selected={selected}
                  data-active={category === activeCategory || undefined}
                  onClick={() => pick(category.id)}
                  onMouseMove={() => setActiveIndex(matches.indexOf(category))}
                  className={cn(
                    'flex cursor-pointer items-center gap-2 rounded-sm px-2 py-1.5 text-xs',
                    'data-[active]:bg-accent data-[active]:text-accent-foreground',
                    selected && 'font-medium text-primary',
                  )}
                >
                  <Check className={cn('size-3.5 shrink-0', !selected && 'invisible')} aria-hidden="true" />
                  <span className="min-w-0 flex-1 truncate" title={category.name}>
                    {category.name}
                  </span>
                  <Badge variant="secondary" className="px-1.5 py-0 text-[0.65rem]">
                    {category.requests_count}
                  </Badge>
                </div>
              )
            })
          )}
        </div>
      </PopoverContent>
    </Popover>
  )
}
