import { useMemo, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Loader2, Plus, Search } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { cn } from '@/lib/utils'
import { indexCategoryTree } from '@/features/product-categories/business-function-inheritance'
import type { ProductCategoryTreeNode } from '@/features/product-categories/types'
import { categoryPathLabel } from '@/features/product-lines/category-path-label'
import type { QuoteLineVariant } from '@/features/quotes/use-quote-lines-field'
import { QuoteCatalogItem } from './quote-catalog-item'
import type { QuoteCatalogProduct, QuoteCatalogScope } from './types'
import { useNextPageSentinel, useQuoteLineCatalog } from './use-quote-line-catalog'

interface QuoteLineCatalogProps {
  variant: QuoteLineVariant
  scope: QuoteCatalogScope
  categoryTree: ProductCategoryTreeNode[]
  /** Shown while a category scope has nothing to scope by (the catalogue is locked). */
  lockedHint: string
  /** `single` mode: one click picks, no multi-selection. */
  singleSelect: boolean
  remainingSlots: number
  addedProductIds: Set<number>
  /** Resolves `false` when nothing was added: the selection stays to retry. */
  onAdd: (items: QuoteCatalogProduct[]) => Promise<boolean>
  onPickSingle: (item: QuoteCatalogProduct) => void
  /** Height cap of the list on a page surface; the dialog grants it more room. */
  listClassName?: string
}

const CHIP_CLASS =
  'rounded-full border px-2.5 py-1 text-xs font-medium outline-none transition-colors focus-visible:ring-[2px] focus-visible:ring-ring/50'

/** The catalogue pane of the line editor (spec 0268 D-1, generalised by spec 0269). */
export function QuoteLineCatalog({
  variant,
  scope,
  categoryTree,
  lockedHint,
  singleSelect,
  remainingSlots,
  addedProductIds,
  onAdd,
  onPickSingle,
  listClassName,
}: QuoteLineCatalogProps) {
  const { t } = useTranslation()
  const [adding, setAdding] = useState(false)
  const fullCatalog = scope.kind === 'categories' ? scope.fullCatalog : undefined
  const fullActive = fullCatalog?.active === true
  const categoryIds = scope.kind === 'categories' ? scope.categoryIds : []
  const locked = scope.kind === 'categories' && categoryIds.length === 0 && !fullActive
  const catalog = useQuoteLineCatalog({
    scope,
    usage: variant === 'revenue' ? 'SALE' : 'COST',
    enabled: !locked,
    remainingSlots,
    addedProductIds,
  })
  const { setScrollRoot, setSentinel } = useNextPageSentinel(catalog)
  const nodes = useMemo(() => indexCategoryTree(categoryTree), [categoryTree])
  const showChips = scope.kind === 'categories' && (categoryIds.length > 1 || fullCatalog !== undefined)

  const activate = (item: QuoteCatalogProduct) => (singleSelect ? onPickSingle(item) : catalog.toggle(item))
  const pickChip = (categoryId: number | null) => {
    if (fullActive) {
      fullCatalog?.onRelease()
    }
    catalog.setActiveCategoryId(categoryId)
  }
  const addSelected = async () => {
    setAdding(true)
    const added = await onAdd(catalog.selectedItems)
    setAdding(false)
    if (added) {
      catalog.clearSelection()
    }
  }
  const selectedCount = catalog.selectedItems.length

  return (
    <section
      aria-label={t('quotes.lineEditor.catalog.title')}
      className="flex min-h-0 min-w-0 flex-1 flex-col"
    >
      <div className="flex flex-col gap-2 border-b p-3">
        <p className="text-sm font-semibold">{t('quotes.lineEditor.catalog.title')}</p>
        <div className="relative">
          <Search aria-hidden="true" className="pointer-events-none absolute top-1/2 left-2.5 size-3.5 -translate-y-1/2 text-muted-foreground" />
          <Input
            type="search"
            value={catalog.search}
            onChange={(event: React.ChangeEvent<HTMLInputElement>) => catalog.setSearch(event.target.value)}
            placeholder={t('quotes.lineEditor.catalog.search')}
            aria-label={t('quotes.lineEditor.catalog.search')}
            disabled={locked}
            className="h-8 bg-card pl-8 text-sm"
          />
        </div>
        {showChips ? (
          <div role="group" aria-label={t('quotes.lineEditor.catalog.categories')} className="flex flex-wrap gap-1.5">
            {categoryIds.length > 0 ? (
              <CategoryChip
                label={t('quotes.lineEditor.catalog.allCategories')}
                active={!fullActive && catalog.activeCategoryId === null}
                onClick={() => pickChip(null)}
              />
            ) : null}
            {categoryIds.length > 1
              ? categoryIds.map((id) => (
                  <CategoryChip
                    key={id}
                    label={nodes.get(id)?.name ?? '…'}
                    active={!fullActive && catalog.activeCategoryId === id}
                    onClick={() => pickChip(id)}
                  />
                ))
              : null}
            {fullCatalog ? (
              <CategoryChip
                label={t('quotes.lineEditor.catalog.fullCatalog')}
                active={fullActive}
                onClick={fullActive ? () => undefined : fullCatalog.onActivate}
              />
            ) : null}
          </div>
        ) : null}
        {locked ? (
          <p className="text-xs text-muted-foreground">{lockedHint}</p>
        ) : singleSelect ? (
          <p className="text-xs text-muted-foreground">{t('quotes.lineEditor.catalog.singleHint')}</p>
        ) : remainingSlots === 0 ? (
          <p className="text-xs text-muted-foreground">{t('quotes.lineEditor.catalog.limitReached')}</p>
        ) : null}
      </div>

      <div
        ref={setScrollRoot}
        role="listbox"
        aria-label={t('quotes.lineEditor.catalog.list')}
        aria-multiselectable={!singleSelect}
        aria-disabled={locked}
        className={cn('flex min-h-0 flex-1 flex-col gap-0.5 overflow-y-auto p-1.5', listClassName)}
      >
        {locked ? null : catalog.isPending ? (
          <div className="flex justify-center py-6">
            <Loader2 aria-hidden="true" className="size-4 animate-spin text-muted-foreground" />
          </div>
        ) : catalog.isError ? (
          <div className="flex flex-col items-center gap-2 py-6 text-center">
            <p className="text-xs text-muted-foreground">{t('quotes.lineEditor.catalog.error')}</p>
            <Button type="button" variant="outline" size="sm" className="bg-card" onClick={catalog.retry}>
              {t('common.retry')}
            </Button>
          </div>
        ) : catalog.items.length === 0 ? (
          <p className="py-6 text-center text-xs text-muted-foreground">{t('quotes.lineEditor.catalog.empty')}</p>
        ) : (
          <>
            {catalog.items.map((item) => {
              const selected = catalog.isSelected(item.id)
              return (
                <QuoteCatalogItem
                  key={item.id}
                  item={item}
                  variant={variant}
                  categoryPath={item.meta.category_id === null ? null : categoryPathLabel(categoryTree, item.meta.category_id)}
                  singleSelect={singleSelect}
                  selected={selected}
                  alreadyAdded={addedProductIds.has(item.id)}
                  disabled={!singleSelect && !selected && !catalog.canSelectMore}
                  onActivate={activate}
                />
              )
            })}
            {catalog.isFetchingNextPage ? (
              <div className="flex justify-center py-2">
                <Loader2 aria-hidden="true" className="size-4 animate-spin text-muted-foreground" />
              </div>
            ) : null}
            {catalog.hasNextPage ? <div ref={setSentinel} aria-hidden="true" /> : null}
          </>
        )}
      </div>

      {singleSelect || locked ? null : (
        <div className="border-t p-2">
          <Button type="button" size="sm" className="w-full" disabled={selectedCount === 0 || adding} onClick={() => void addSelected()}>
            {adding ? <Loader2 aria-hidden="true" className="size-3.5 animate-spin" /> : <Plus aria-hidden="true" className="size-3.5" />}
            {selectedCount === 0
              ? t('quotes.lineEditor.catalog.addNone')
              : t('quotes.lineEditor.catalog.addSelected', { count: selectedCount })}
          </Button>
        </div>
      )}
    </section>
  )
}

interface CategoryChipProps {
  label: string
  active: boolean
  onClick: () => void
}

function CategoryChip({ label, active, onClick }: CategoryChipProps) {
  return (
    <button
      type="button"
      aria-pressed={active}
      onClick={onClick}
      className={cn(
        CHIP_CLASS,
        active
          ? 'border-primary bg-primary text-primary-foreground'
          : 'bg-card text-muted-foreground hover:text-foreground',
      )}
    >
      {label}
    </button>
  )
}
