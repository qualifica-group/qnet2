/**
 * AG Grid popup cell editor for an `editor: 'product_lines'` column (spec
 * 0075): the in-grid twin of `ProductLinesField`, editing the very same
 * {funzione aziendale, categoria prodotto} collection the form edits — never a
 * free string of category names.
 *
 * Same flow as the form, in the space a cell has: the pairs already on the
 * request are listed as removable chips, and a new one is built in two steps —
 * pick the business function, then pick a category SCOPED to it
 * (`business_function_id` on `/product-categories/for-select`, exactly the
 * param the form select sends). A category is never pickable without its
 * function, which is what keeps the pair coherent by construction.
 *
 * Two constraints inherited from `RelationCellEditor`/`MultiSelectCellEditor`,
 * both learned the hard way: the lists render INSIDE the popup (a nested Radix
 * Popover portals to `document.body`, and `stopEditingWhenCellsLoseFocus` then
 * tears the editor down mid-open), and nothing here opens a portalled dialog.
 * Like the multiselect editor, a pick does NOT close the popup: a collection is
 * built with several picks and committed when the editor closes.
 *
 * The server stays authoritative on every rule (existence, selectability, the
 * category/function match, no repeated pair, the coherence with the products of
 * interest): the warning this editor shows is anticipatory, never the check.
 */
import { useEffect, useMemo, useRef, useState } from 'react'
import type { CustomCellEditorProps } from 'ag-grid-react'
import { useTranslation } from 'react-i18next'
import { AlertTriangle, ChevronLeft, Loader2, Plus, X } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { useDebouncedValue } from '@/hooks/use-debounced-value'
import { useForSelect } from '@/features/for-select/use-for-select'
import { BUSINESS_FUNCTIONS_FOR_SELECT_RESOURCE } from '@/features/business-functions/for-select-api'
import { PRODUCT_CATEGORIES_FOR_SELECT_RESOURCE } from '@/features/product-categories/for-select-api'
import { useProductCategoryTree } from '@/features/product-categories/use-product-category-tree'
import type { ProductCategoryTreeNode } from '@/features/product-categories/types'
import { resolveRowSetManagementMode } from '@/features/product-lines/category-tree-scope'
import type { ForSelectItem } from '@/features/for-select/types'
import type { TableRow } from '@/features/table/types'
import { cn } from '@/lib/utils'

/** One pair as the row projects it and as the PATCH sends it back (the `*_name` keys are dropped at the wire boundary). */
export interface ProductLineCellValue {
  business_function_id: number
  business_function_name: string
  product_category_id: number
  product_category_name: string
}

/** A product of interest as the row projects it, carrying the category it hangs from (spec 0075 D-6). */
interface ProductOfInterestRef {
  id: number
  name: string
  category_id?: number | null
}

/** Debounce before a typed term reaches the server, matching every other picker. */
const SEARCH_DEBOUNCE_MS = 300

/** The row key holding the products whose coverage a removal may break. */
const PRODUCTS_COLUMN = 'products_of_interest'

/** Stable empty tree while the shared query is still loading (mirrors `useProductLinesField`). */
const EMPTY_TREE: ProductCategoryTreeNode[] = []

/** The step the "add a pair" flow is on: pick the function, then its category. */
type PickStep = 'business_function' | 'product_category'

function pairKey(pair: { business_function_id: number; product_category_id: number }): string {
  return `${pair.business_function_id}:${pair.product_category_id}`
}

/** The products of the edited row that no remaining pair covers — what the operator is about to disconnect. */
function uncoveredProducts(row: TableRow | undefined, pairs: ProductLineCellValue[]): string[] {
  const products = Array.isArray(row?.[PRODUCTS_COLUMN]) ? (row[PRODUCTS_COLUMN] as ProductOfInterestRef[]) : []
  const covered = new Set(pairs.map((pair) => pair.product_category_id))

  return products
    .filter((product) => typeof product?.category_id === 'number' && !covered.has(product.category_id))
    .map((product) => product.name)
}

export function ProductLinesCellEditor(props: CustomCellEditorProps<TableRow, ProductLineCellValue[] | null>) {
  const { t } = useTranslation()
  const { value, onValueChange, data } = props
  const [step, setStep] = useState<PickStep>('business_function')
  const [businessFunction, setBusinessFunction] = useState<ForSelectItem | null>(null)
  const [search, setSearch] = useState('')
  const debouncedSearch = useDebouncedValue(search, SEARCH_DEBOUNCE_MS)
  const inputRef = useRef<HTMLInputElement>(null)

  // The single click that opened the cell is the only one the operator makes
  // (0053 D-9): they can type straight away.
  useEffect(() => {
    inputRef.current?.focus()
  }, [])

  const pairs = useMemo(() => value ?? [], [value])
  const selectedKeys = useMemo(() => new Set(pairs.map(pairKey)), [pairs])
  const orphanedProducts = useMemo(() => uncoveredProducts(data, pairs), [data, pairs])

  // Spec 0077 INV-3 (user directive 2026-08-07): the form fields have hidden
  // "Add" on a `single`-mode card since 2026-08-05 — this editor was the last
  // channel where the second pair could still be picked, only to be refused by
  // the server on commit. Resolved off the SAME cached category tree the form
  // reads, so a pair loaded from the grid row carries the mode too.
  const categoryTree = useProductCategoryTree().data ?? EMPTY_TREE
  const singleRowReached = pairs.length > 0 && resolveRowSetManagementMode(pairs, categoryTree) === 'single'

  const pickingCategory = step === 'product_category' && businessFunction !== null

  const {
    data: pages,
    isPending,
    isError,
    refetch,
    hasNextPage,
    isFetchingNextPage,
    fetchNextPage,
  } = useForSelect({
    resource: pickingCategory ? PRODUCT_CATEGORIES_FOR_SELECT_RESOURCE : BUSINESS_FUNCTIONS_FOR_SELECT_RESOURCE,
    search: debouncedSearch,
    params: pickingCategory ? { business_function_id: businessFunction.id } : undefined,
  })

  const options = pages?.pages.flatMap((page) => page.items) ?? []

  const startOver = () => {
    setStep('business_function')
    setBusinessFunction(null)
    setSearch('')
  }

  const pick = (item: ForSelectItem) => {
    // Defense in depth: the options are already disabled once the single-mode
    // card holds its one pair, this guards a programmatic call too.
    if (singleRowReached) {
      return
    }

    if (!pickingCategory) {
      setBusinessFunction(item)
      setStep('product_category')
      setSearch('')

      return
    }

    const next: ProductLineCellValue = {
      business_function_id: businessFunction.id,
      business_function_name: businessFunction.label,
      product_category_id: item.id,
      product_category_name: item.label,
    }

    // A pair already on the request is a server-side 422 (no repeats): adding
    // it again would only make the commit fail.
    if (!selectedKeys.has(pairKey(next))) {
      onValueChange([...pairs, next])
    }

    startOver()
  }

  const remove = (index: number) => {
    onValueChange(pairs.filter((_, position) => position !== index))
  }

  return (
    <div className="w-80 rounded-md border border-border bg-popover shadow-md">
      <ul aria-label={t('table.productLinesEditor.selected')} className="flex flex-col gap-1 border-b border-border p-1.5">
        {pairs.length === 0 ? (
          <li className="px-1 py-1 text-xs text-muted-foreground">{t('table.productLinesEditor.none')}</li>
        ) : (
          pairs.map((pair, index) => (
            <li key={pairKey(pair)} className="flex items-center gap-1.5 rounded-sm bg-muted/40 px-2 py-1">
              <span className="min-w-0 flex-1 truncate text-xs" title={`${pair.business_function_name} › ${pair.product_category_name}`}>
                <span className="text-muted-foreground">{pair.business_function_name}</span>
                <span aria-hidden="true" className="px-1 text-muted-foreground">
                  ›
                </span>
                <span className="font-medium">{pair.product_category_name}</span>
              </span>
              <Button
                type="button"
                variant="ghost"
                size="icon-sm"
                aria-label={t('table.productLinesEditor.remove', { name: pair.product_category_name })}
                onClick={() => remove(index)}
              >
                <X aria-hidden="true" className="size-3.5" />
              </Button>
            </li>
          ))
        )}
      </ul>

      <div className="flex items-center gap-1.5 p-1">
        {pickingCategory ? (
          <Button
            type="button"
            variant="ghost"
            size="icon-sm"
            aria-label={t('table.productLinesEditor.back')}
            onClick={startOver}
          >
            <ChevronLeft aria-hidden="true" className="size-3.5" />
          </Button>
        ) : (
          <Plus aria-hidden="true" className="size-3.5 shrink-0 text-muted-foreground" />
        )}
        <Input
          ref={inputRef}
          value={search}
          onChange={(event: React.ChangeEvent<HTMLInputElement>) => setSearch(event.target.value)}
          placeholder={
            pickingCategory
              ? t('table.productLinesEditor.categorySearch')
              : t('table.productLinesEditor.businessFunctionSearch')
          }
          aria-label={
            pickingCategory
              ? t('table.productLinesEditor.categorySearch')
              : t('table.productLinesEditor.businessFunctionSearch')
          }
          disabled={singleRowReached}
          className="h-7 text-xs"
        />
      </div>

      <p className="px-2 pb-1 text-xs text-muted-foreground">
        {singleRowReached
          ? t('table.productLinesEditor.singleModeReached')
          : pickingCategory
            ? t('table.productLinesEditor.categoryStep', { name: businessFunction.label })
            : t('table.productLinesEditor.businessFunctionStep')}
      </p>

      <div
        role="listbox"
        aria-label={
          pickingCategory
            ? t('table.productLinesEditor.categorySearch')
            : t('table.productLinesEditor.businessFunctionSearch')
        }
        className="max-h-48 overflow-y-auto p-1"
      >
        {isPending ? (
          <div className="flex items-center justify-center py-6">
            <Loader2 className="size-4 animate-spin text-muted-foreground" aria-hidden="true" />
          </div>
        ) : isError ? (
          <div className="flex flex-col items-center gap-2 px-2 py-6 text-center">
            <p className="text-xs text-muted-foreground">{t('table.productLinesEditor.error')}</p>
            <button
              type="button"
              onClick={() => void refetch()}
              className="text-xs font-medium text-primary underline-offset-4 hover:underline"
            >
              {t('table.productLinesEditor.retry')}
            </button>
          </div>
        ) : options.length === 0 ? (
          <p className="px-2 py-6 text-center text-xs text-muted-foreground">{t('table.productLinesEditor.empty')}</p>
        ) : (
          <>
            {options.map((item) => {
              const already =
                pickingCategory &&
                selectedKeys.has(pairKey({ business_function_id: businessFunction.id, product_category_id: item.id }))
              // Two distinct reasons to refuse a pick: the pair is already
              // there (`aria-selected`), or the card is full (INV-3) — which
              // is not a selection state, only a disabled one.
              const blocked = already || singleRowReached

              return (
                <button
                  key={item.id}
                  type="button"
                  role="option"
                  aria-selected={already}
                  disabled={blocked}
                  onClick={() => pick(item)}
                  className={cn(
                    'flex w-full items-center gap-1.5 rounded-sm px-2.5 py-1 text-left text-xs',
                    'hover:bg-accent focus-visible:bg-accent focus-visible:outline-none',
                    blocked && 'cursor-not-allowed text-muted-foreground',
                  )}
                >
                  <span className="truncate">{item.label}</span>
                </button>
              )
            })}
            {hasNextPage ? (
              <button
                type="button"
                onClick={() => void fetchNextPage()}
                disabled={isFetchingNextPage}
                className="flex w-full items-center justify-center gap-1.5 rounded-sm px-2.5 py-1 text-xs text-muted-foreground hover:bg-accent"
              >
                {isFetchingNextPage ? (
                  <Loader2 className="size-3.5 animate-spin" aria-hidden="true" />
                ) : (
                  t('table.productLinesEditor.loadMore')
                )}
              </button>
            ) : null}
          </>
        )}
      </div>

      {orphanedProducts.length > 0 ? (
        // Anticipatory only: the commit itself is refused server-side
        // (ProductCategoryCoherence) with the same list of products.
        <p role="alert" className="flex gap-1.5 border-t border-border p-2 text-xs text-muted-foreground">
          <AlertTriangle aria-hidden="true" className="mt-0.5 size-3.5 shrink-0 text-amber-600 dark:text-amber-400" />
          <span>{t('table.productLinesEditor.uncoveredProducts', { names: orphanedProducts.join(', ') })}</span>
        </p>
      ) : null}
    </div>
  )
}
