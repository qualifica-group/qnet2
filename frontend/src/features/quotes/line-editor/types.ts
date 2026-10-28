import type { ReactNode } from 'react'
import type { ForSelectItem } from '@/features/for-select/types'
import type { QuoteProductForSelectMeta } from '@/features/quotes/quote-product-select'
import type { QuoteLineFormValues } from '@/features/quotes/quote-schema'
import type { LineCommissionDefaults } from '@/features/quotes/use-quote-lines-field'

/**
 * The `GET /products/for-select` meta as the catalogue reads it (spec 0268):
 * the Offerte picker's own meta plus the keys it never needed — the category,
 * to resolve the "Radice > Categoria" path off the cached tree, the
 * description on its own line, and the typology's colour token.
 */
export interface QuoteCatalogProductMeta extends QuoteProductForSelectMeta {
  category_id: number | null
  description: string | null
  product_typology: { id: number; name: string; color: string | null } | null
}

export interface QuoteCatalogProduct extends ForSelectItem {
  meta: QuoteCatalogProductMeta
}

/** A product as a row card shows it: what the form row itself (ids only) cannot tell. */
export interface QuoteLineProductView {
  id: number
  name: string
  code: string | null
  categoryId: number | null
  typology: { name: string; color?: string | null } | null
}

/** Which products the catalogue lists: a category scope (revenue) or the whole catalogue (cost). */
export type QuoteCatalogScope =
  | {
      kind: 'categories'
      categoryIds: number[]
      /** The opt-in "Tutto il catalogo" chip (spec 0269 D-2); absent = no chip. */
      fullCatalog?: QuoteFullCatalogSwitch
    }
  | { kind: 'all' }

export interface QuoteFullCatalogSwitch {
  active: boolean
  /** Asks for the whole catalogue (the caller confirms first). */
  onActivate: () => void
  /** Back to the category scope. */
  onRelease: () => void
}

/**
 * The commission defaults a revenue pick brings (spec 0269 D-4/D-6), resolved
 * by `useQuoteLineCommissions`. `null` = nothing may change: a request failed
 * (the toast is already shown) or the operator declined the regeneration.
 */
export interface QuoteLineCommissionsAdapter {
  defaultsForNewRows: (items: QuoteCatalogProduct[]) => Promise<Map<number, LineCommissionDefaults> | null>
  defaultsForReplacement: (row: QuoteLineFormValues, item: QuoteCatalogProduct) => Promise<LineCommissionDefaults | null>
  /** Spec 0320 D-7: the row changed supplier; same `null` contract. */
  defaultsForSupplier: (row: QuoteLineFormValues, supplierId: number) => Promise<LineCommissionDefaults | null>
}

/** What a channel adds to a row card: trailing actions, a level-2 field, a level-3 block. */
export interface QuoteLineCardSlots {
  actions?: ReactNode
  extraField?: ReactNode
  footer?: ReactNode
}

/** A row the editor renders, with its position in the form array (errors are indexed on it). */
export interface VisibleQuoteLine {
  row: QuoteLineFormValues
  index: number
}
