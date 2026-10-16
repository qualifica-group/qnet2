import { useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import { Loader2, Plus, Search } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { cn } from '@/lib/utils'
import { indexCategoryTree } from '@/features/product-categories/business-function-inheritance'
import type { ProductCategoryTreeNode } from '@/features/product-categories/types'
import { categoryPathLabel } from '@/features/product-lines/category-path-label'
import { OfferCatalogItem } from './offer-catalog-item'
import type { OfferCatalogProduct } from './types'
import { useNextPageSentinel, useOfferLinesCatalog } from './use-offer-lines-catalog'

interface OfferLinesCatalogProps {
  /** The classification's categories: chips and scope (D-4). Empty locks the catalogue (AC-011). */
  categoryIds: number[]
  categoryTree: ProductCategoryTreeNode[]
  /** `single` mode (D-6): one click picks, no multi-selection. */
  singleSelect: boolean
  remainingSlots: number
  addedProductIds: Set<number>
  onAdd: (items: OfferCatalogProduct[]) => void
  onPickSingle: (item: OfferCatalogProduct) => void
  /** Height cap of the list on a page surface; the dialog lets it fill the pane instead. */
  listClassName?: string
}

const CHIP_CLASS =
  'rounded-full border px-2.5 py-1 text-xs font-medium outline-none transition-colors focus-visible:ring-[2px] focus-visible:ring-ring/50'

/** The catalogue pane of the offer-lines editor (spec 0268 D-1). */
export function OfferLinesCatalog({
  categoryIds,
  categoryTree,
  singleSelect,
  remainingSlots,
  addedProductIds,
  onAdd,
  onPickSingle,
  listClassName,
}: OfferLinesCatalogProps) {
  const { t } = useTranslation()
  const locked = categoryIds.length === 0
  const catalog = useOfferLinesCatalog({ categoryIds, enabled: !locked, remainingSlots, addedProductIds })
  const { setScrollRoot, setSentinel } = useNextPageSentinel(catalog)
  const nodes = useMemo(() => indexCategoryTree(categoryTree), [categoryTree])
  const listLabel = t('requestManagement.offerLines.catalog.list')

  const activate = (item: OfferCatalogProduct) => (singleSelect ? onPickSingle(item) : catalog.toggle(item))
  const addSelected = () => onAdd(catalog.takeSelection())
  const selectedCount = catalog.selectedItems.length

  return (
    <section
      aria-label={t('requestManagement.offerLines.catalog.title')}
      className="flex min-h-0 min-w-0 flex-col rounded-lg border bg-surface"
    >
      <div className="flex flex-col gap-2 border-b p-3">
        <p className="text-sm font-semibold">{t('requestManagement.offerLines.catalog.title')}</p>
        <div className="relative">
          <Search aria-hidden="true" className="pointer-events-none absolute top-1/2 left-2.5 size-3.5 -translate-y-1/2 text-muted-foreground" />
          <Input
            type="search"
            value={catalog.search}
            onChange={(event: React.ChangeEvent<HTMLInputElement>) => catalog.setSearch(event.target.value)}
            placeholder={t('requestManagement.offerLines.catalog.search')}
            aria-label={t('requestManagement.offerLines.catalog.search')}
            disabled={locked}
            className="h-8 bg-card pl-8 text-sm"
          />
        </div>
        {categoryIds.length > 1 ? (
          <div role="group" aria-label={t('requestManagement.offerLines.catalog.categories')} className="flex flex-wrap gap-1.5">
            <CategoryChip
              label={t('requestManagement.offerLines.catalog.allCategories')}
              active={catalog.activeCategoryId === null}
              onClick={() => catalog.setActiveCategoryId(null)}
            />
            {categoryIds.map((id) => (
              <CategoryChip
                key={id}
                label={nodes.get(id)?.name ?? '…'}
                active={catalog.activeCategoryId === id}
                onClick={() => catalog.setActiveCategoryId(id)}
              />
            ))}
          </div>
        ) : null}
        {locked ? (
          <p className="text-xs text-muted-foreground">{t('requestManagement.offerLines.hintNoCategory')}</p>
        ) : singleSelect ? (
          <p className="text-xs text-muted-foreground">{t('requestManagement.offerLines.catalog.singleHint')}</p>
        ) : remainingSlots === 0 ? (
          <p className="text-xs text-muted-foreground">{t('requestManagement.offerLines.catalog.limitReached')}</p>
        ) : null}
      </div>

      <div
        ref={setScrollRoot}
        role="listbox"
        aria-label={listLabel}
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
            <p className="text-xs text-muted-foreground">{t('requestManagement.offerLines.catalog.error')}</p>
            <Button type="button" variant="outline" size="sm" className="bg-card" onClick={catalog.retry}>
              {t('common.retry')}
            </Button>
          </div>
        ) : catalog.items.length === 0 ? (
          <p className="py-6 text-center text-xs text-muted-foreground">{t('requestManagement.offerLines.catalog.empty')}</p>
        ) : (
          <>
            {catalog.items.map((item) => {
              const selected = catalog.isSelected(item.id)
              return (
                <OfferCatalogItem
                  key={item.id}
                  item={item}
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
          <Button type="button" size="sm" className="w-full" disabled={selectedCount === 0} onClick={addSelected}>
            <Plus aria-hidden="true" className="size-3.5" />
            {selectedCount === 0
              ? t('requestManagement.offerLines.catalog.addNone')
              : t('requestManagement.offerLines.catalog.addSelected', { count: selectedCount })}
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
