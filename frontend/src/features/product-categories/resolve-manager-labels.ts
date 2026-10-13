import type { ProductCategoryDetail } from '@/features/product-categories/types'

export interface ResolvedManagerLabel {
  position: number
  label: string
  inherited: boolean
}

/**
 * Merges own + inherited manager labels for display, own winning per position
 * (spec 0080), skipping positions with neither. Positions are no longer
 * bounded to a fixed 1..4 range (spec 0080 A1): the union of whatever
 * positions either map carries drives the list, sorted ascending.
 */
export function resolveManagerLabels(category: ProductCategoryDetail): ResolvedManagerLabel[] {
  const positions = new Set([
    ...Object.keys(category.manager_labels),
    ...Object.keys(category.inherited_manager_labels),
  ])
  return [...positions]
    .map(Number)
    .sort((a, b) => a - b)
    .map((position): ResolvedManagerLabel | null => {
      const key = String(position)
      const own = category.manager_labels[key]
      if (own) {
        return { position, label: own, inherited: false }
      }
      const fromAncestor = category.inherited_manager_labels[key]
      return fromAncestor ? { position, label: fromAncestor, inherited: true } : null
    })
    .filter((entry): entry is ResolvedManagerLabel => entry !== null)
}
