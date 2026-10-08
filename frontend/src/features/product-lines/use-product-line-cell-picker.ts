import { useMemo, useState } from 'react'
import { flattenCategoryTree, pruneToPickable, type FlatCategoryOption } from '@/features/product-categories/flatten-tree'
import type { ProductCategoryTreeNode } from '@/features/product-categories/types'
import {
  filterCategoriesUnderRoot,
  isSingleRootBlocked,
  selectableIdsUnderRoot,
} from '@/features/product-lines/category-tree-scope'

/**
 * The step the "add a pair" flow is on: the root category, the OPTIONAL
 * intermediate filter (user directive 2026-10-08), then one of the
 * descendants of whichever of the two scopes the pick.
 */
export type CellPickStep = 'root_category' | 'filter_category' | 'product_category'

/** A picked tree node, as the editor shows it back. */
export interface PickedCategory {
  id: number
  name: string
}

interface UseProductLineCellPickerArgs {
  categoryTree: ProductCategoryTreeNode[]
  /** A pick would ADD a pair beside existing ones: `single` roots are listed disabled (spec 0077 AC-047). */
  addingBesidePairs: boolean
  /** Called with the completed pick; the flow then starts over for the next pair. */
  onPick: (root: PickedCategory, category: PickedCategory) => void
}

/**
 * Owns the step flow of `ProductLinesCellEditor`: which list is shown, the
 * search over it and what a click on an option does. The intermediate filter
 * is UI-only, like the root: only the final category reaches the pair.
 */
export function useProductLineCellPicker({ categoryTree, addingBesidePairs, onPick }: UseProductLineCellPickerArgs) {
  const [step, setStep] = useState<CellPickStep>('root_category')
  const [rootCategory, setRootCategory] = useState<PickedCategory | null>(null)
  const [filterCategory, setFilterCategory] = useState<PickedCategory | null>(null)
  const [search, setSearch] = useState('')

  const filterOptions = useMemo<FlatCategoryOption[]>(
    () =>
      rootCategory === null
        ? []
        : filterCategoriesUnderRoot(categoryTree, rootCategory.id).map((node) => ({
            id: node.id,
            name: node.name,
            depth: 0,
          })),
    [categoryTree, rootCategory],
  )

  const baseOptions = useMemo<FlatCategoryOption[]>(() => {
    // Step 1: every root — always the full list (spec 0132 D-1), never pruned.
    if (step === 'root_category' || rootCategory === null) {
      return categoryTree.map((root) => ({
        id: root.id,
        name: root.name,
        depth: 0,
        disabled: isSingleRootBlocked(root, addingBesidePairs),
      }))
    }
    // Step 2 (optional): the root's grouping children.
    if (step === 'filter_category') {
      return filterOptions
    }
    // Step 3: the `is_selectable` descendants of the filter, or of the root
    // without one, containers kept as disabled context and dead branches
    // pruned (spec 0132 D-1/D-2) — the same tree utilities the form reads.
    const scopeId = filterCategory?.id ?? rootCategory.id
    const scopeNode = findTopOrChild(categoryTree, rootCategory.id, scopeId)
    if (scopeNode === null) {
      return []
    }
    const pickableIds = selectableIdsUnderRoot(categoryTree, scopeId)
    return flattenCategoryTree(pruneToPickable([scopeNode], pickableIds), { pickableIds })
  }, [step, categoryTree, rootCategory, filterCategory, filterOptions, addingBesidePairs])

  const options = useMemo(() => {
    const term = search.trim().toLowerCase()
    return term === '' ? baseOptions : baseOptions.filter((option) => option.name.toLowerCase().includes(term))
  }, [baseOptions, search])

  const goTo = (next: CellPickStep) => {
    setStep(next)
    setSearch('')
  }

  const startOver = () => {
    setRootCategory(null)
    setFilterCategory(null)
    goTo('root_category')
  }

  /** Back from the filter list returns to the category list; anywhere else it starts over. */
  const back = () => (step === 'filter_category' ? goTo('product_category') : startOver())

  const pick = (option: FlatCategoryOption) => {
    const picked = { id: option.id, name: option.name }
    if (step === 'root_category' || rootCategory === null) {
      setRootCategory(picked)
      goTo('product_category')
      return
    }
    if (step === 'filter_category') {
      setFilterCategory(picked)
      goTo('product_category')
      return
    }
    onPick(rootCategory, picked)
    startOver()
  }

  return {
    step,
    rootCategory,
    filterCategory,
    search,
    setSearch,
    options,
    /** The root has grouping children and no filter is applied yet: the "Filtro" action is offered. */
    canFilter: step === 'product_category' && filterCategory === null && filterOptions.length > 0,
    openFilter: () => goTo('filter_category'),
    clearFilter: () => setFilterCategory(null),
    back,
    pick,
  }
}

/** The root itself, or one of its direct children (a filter): the node the category list is scoped to. */
function findTopOrChild(
  nodes: ProductCategoryTreeNode[],
  rootId: number,
  scopeId: number,
): ProductCategoryTreeNode | null {
  const root = nodes.find((node) => node.id === rootId)
  if (root === undefined) {
    return null
  }
  return scopeId === rootId ? root : (root.children.find((child) => child.id === scopeId) ?? null)
}
