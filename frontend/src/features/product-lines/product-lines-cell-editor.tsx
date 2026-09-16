/**
 * AG Grid popup cell editor for an `editor: 'product_lines'` column (spec
 * 0075, reshaped by spec 0132 AC-020): the in-grid twin of `ProductLinesField`,
 * editing the very same product-category pairs collection the card form
 * edits — never a free string of category names.
 *
 * Same flow as the form, in the space a cell has: the pairs already on the
 * request are listed as removable chips, and a new one is built in two
 * steps — pick the ROOT category (a node with no parent), then one of its
 * `is_selectable` descendants (containers listed as disabled context, dead
 * branches pruned). The business function is no longer picked here at all:
 * the server derives and persists it from the category, exactly like the
 * card form (spec 0132 D-3). Both steps read the category TREE already
 * cached by `useProductCategoryTree` — the same one the form's
 * `ProductCategoryRootSelect`/`ProductCategoryTreeSelect` read — so this
 * editor makes NO network call of its own (no more `for-select` on business
 * functions or categories).
 *
 * A category is identified alone now (spec 0132: duplicate = same category,
 * the business function is no longer part of the pair's identity), and the
 * value committed to the grid carries only `product_category_id` per pair on
 * the wire — see the note on `resolvePairEntry` below.
 *
 * Two constraints inherited from `RelationCellEditor`/`MultiSelectCellEditor`,
 * both learned the hard way: the lists render INSIDE the popup (a nested Radix
 * Popover portals to `document.body`, and `stopEditingWhenCellsLoseFocus` then
 * tears the editor down mid-open), and nothing here opens a portalled dialog
 * — which is why this editor hand-rolls its own listbox instead of reusing
 * `SearchableSelect` (Radix Popover) like the form does.
 * Like the multiselect editor, a pick does NOT close the popup: a collection is
 * built with several picks and committed when the editor closes.
 *
 * The server stays authoritative on every rule (existence, selectability, the
 * derived function, no repeated category, the coherence with the products of
 * interest): the warning this editor shows is anticipatory, never the check.
 */
import { useEffect, useMemo, useRef, useState } from 'react'
import type { CustomCellEditorProps } from 'ag-grid-react'
import { useTranslation } from 'react-i18next'
import { AlertTriangle, ChevronLeft, Loader2, Plus, X } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { flattenCategoryTree, pruneToPickable, type FlatCategoryOption } from '@/features/product-categories/flatten-tree'
import { useProductCategoryTree } from '@/features/product-categories/use-product-category-tree'
import type { ProductCategoryTreeNode } from '@/features/product-categories/types'
import { resolveRowSetManagementMode, selectableIdsUnderRoot } from '@/features/product-lines/category-tree-scope'
import type { TableRow } from '@/features/table/types'
import { cn } from '@/lib/utils'

/**
 * One pair as the row projects it and as the PATCH sends it back.
 *
 * `root_category_id`/`root_category_name` and `product_category_id`/
 * `product_category_name` are always known: hydrated from the grid row
 * (`RequestRowMapper`) for a persisted pair, or filled in from the tree at
 * pick time for one just built in this popup. `business_function_*` is the
 * row's DERIVED function (spec 0132 D-3): a pair round-tripped from the
 * server carries it, a pair just picked here does not yet — this editor never
 * guesses it client-side (see `pick` below), it only ever reads it back once
 * the commit refreshes the row.
 *
 * Only `product_category_id` reaches the PATCH: `resolvePairEntry`
 * (`features/table/use-table-cell-edit.tsx`) reduces a pair to that one key
 * alone, dropping the `*_name` labels, `root_category_id` (UI-only editor
 * state) and `business_function_id` (server-derived, read-only) — spec 0132
 * `data_contract`.
 */
export interface ProductLineCellValue {
  root_category_id: number
  root_category_name: string
  product_category_id: number
  product_category_name: string
  business_function_id?: number
  business_function_name?: string
}

/** A product of interest as the row projects it, carrying the category it hangs from (spec 0075 D-6). */
interface ProductOfInterestRef {
  id: number
  name: string
  category_id?: number | null
}

/** The row key holding the products whose coverage a removal may break. */
const PRODUCTS_COLUMN = 'products_of_interest'

/** Stable empty tree while the shared query is still loading (mirrors `useProductLinesField`). */
const EMPTY_TREE: ProductCategoryTreeNode[] = []

/** Indent per nesting level in the category step, matching `SearchableSelect`'s own hierarchical lists. */
const INDENT_REM_PER_DEPTH = 0.75

/** The step the "add a pair" flow is on: pick the root category, then one of its descendants. */
type PickStep = 'root_category' | 'product_category'

function pairKey(pair: { product_category_id: number }): string {
  return String(pair.product_category_id)
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
  const [step, setStep] = useState<PickStep>('root_category')
  const [rootCategory, setRootCategory] = useState<{ id: number; name: string } | null>(null)
  const [search, setSearch] = useState('')
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
  const treeQuery = useProductCategoryTree()
  const categoryTree = treeQuery.data ?? EMPTY_TREE
  const singleRowReached = pairs.length > 0 && resolveRowSetManagementMode(pairs, categoryTree) === 'single'

  const pickingCategory = step === 'product_category' && rootCategory !== null

  // Step 1: every root (top-level node, no parent) — always the full list
  // (spec 0132 D-1), never pruned or disabled.
  const rootOptions = useMemo<FlatCategoryOption[]>(
    () => categoryTree.map((root) => ({ id: root.id, name: root.name, depth: 0 })),
    [categoryTree],
  )

  // Step 2: the chosen root's own `is_selectable` descendants, with its
  // non-selectable containers kept as disabled context and dead branches
  // pruned (spec 0132 D-1/D-2) — the same tree utilities the form's
  // `ProductCategoryTreeSelect` reads, scoped to a single root's subtree.
  const categoryOptions = useMemo<FlatCategoryOption[]>(() => {
    if (rootCategory === null) {
      return []
    }
    const rootNode = categoryTree.find((node) => node.id === rootCategory.id)
    if (rootNode === undefined) {
      return []
    }
    const pickableIds = selectableIdsUnderRoot(categoryTree, rootCategory.id)
    return flattenCategoryTree(pruneToPickable([rootNode], pickableIds), { pickableIds })
  }, [categoryTree, rootCategory])

  const options = useMemo(() => {
    const baseOptions = pickingCategory ? categoryOptions : rootOptions
    const term = search.trim().toLowerCase()
    return term === '' ? baseOptions : baseOptions.filter((option) => option.name.toLowerCase().includes(term))
  }, [pickingCategory, categoryOptions, rootOptions, search])

  const startOver = () => {
    setStep('root_category')
    setRootCategory(null)
    setSearch('')
  }

  const pick = (option: FlatCategoryOption) => {
    // Defense in depth: the options are already disabled once the single-mode
    // card holds its one pair, this guards a programmatic call too.
    if (singleRowReached) {
      return
    }

    if (!pickingCategory) {
      setRootCategory({ id: option.id, name: option.name })
      setStep('product_category')
      setSearch('')

      return
    }

    const next: ProductLineCellValue = {
      root_category_id: rootCategory.id,
      root_category_name: rootCategory.name,
      product_category_id: option.id,
      product_category_name: option.name,
    }

    // A category already on the request is a server-side 422 (no repeats):
    // adding it again would only make the commit fail.
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
              <span className="min-w-0 flex-1 truncate text-xs" title={`${pair.root_category_name} › ${pair.product_category_name}`}>
                <span className="text-muted-foreground">{pair.root_category_name}</span>
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
            pickingCategory ? t('table.productLinesEditor.categorySearch') : t('productLines.rootCategorySearch')
          }
          aria-label={
            pickingCategory ? t('table.productLinesEditor.categorySearch') : t('productLines.rootCategorySearch')
          }
          disabled={singleRowReached}
          className="h-7 text-xs"
        />
      </div>

      <p className="px-2 pb-1 text-xs text-muted-foreground">
        {singleRowReached
          ? t('table.productLinesEditor.singleModeReached')
          : pickingCategory
            ? t('table.productLinesEditor.categoryStep', { name: rootCategory.name })
            : t('table.productLinesEditor.rootCategoryStep')}
      </p>

      <div
        role="listbox"
        aria-label={
          pickingCategory ? t('table.productLinesEditor.categorySearch') : t('productLines.rootCategorySearch')
        }
        className="max-h-48 overflow-y-auto p-1"
      >
        {treeQuery.isPending ? (
          <div className="flex items-center justify-center py-6">
            <Loader2 className="size-4 animate-spin text-muted-foreground" aria-hidden="true" />
          </div>
        ) : treeQuery.isError ? (
          <div className="flex flex-col items-center gap-2 px-2 py-6 text-center">
            <p className="text-xs text-muted-foreground">{t('table.productLinesEditor.error')}</p>
            <button
              type="button"
              onClick={() => void treeQuery.refetch()}
              className="text-xs font-medium text-primary underline-offset-4 hover:underline"
            >
              {t('table.productLinesEditor.retry')}
            </button>
          </div>
        ) : options.length === 0 ? (
          <p className="px-2 py-6 text-center text-xs text-muted-foreground">{t('table.productLinesEditor.empty')}</p>
        ) : (
          options.map((option) => {
            const alreadySelected = pickingCategory && selectedKeys.has(pairKey({ product_category_id: option.id }))
            const containerDisabled = pickingCategory && option.disabled === true
            // Three distinct reasons to refuse a pick: the category is already
            // there (`aria-selected`), it is a non-selectable container shown
            // only as context, or the card is full (INV-3) — none of which is
            // a selection state on its own, only a disabled one.
            const blocked = alreadySelected || containerDisabled || singleRowReached

            return (
              <button
                key={option.id}
                type="button"
                role="option"
                aria-selected={alreadySelected}
                disabled={blocked}
                onClick={() => pick(option)}
                style={option.depth ? { paddingLeft: `${INDENT_REM_PER_DEPTH * option.depth}rem` } : undefined}
                className={cn(
                  'flex w-full items-center gap-1.5 rounded-sm px-2.5 py-1 text-left text-xs',
                  'hover:bg-accent focus-visible:bg-accent focus-visible:outline-none',
                  blocked && 'cursor-not-allowed text-muted-foreground',
                )}
              >
                <span className="truncate">{option.name}</span>
              </button>
            )
          })
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
