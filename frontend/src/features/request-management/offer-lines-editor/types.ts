import type { ForSelectItem } from '@/features/for-select/types'
import type { QuoteProductForSelectMeta } from '@/features/quotes/quote-product-select'

/**
 * The `GET /products/for-select` meta as the catalogue reads it (spec 0268):
 * the Offerte picker's own meta plus the two keys it never needed — the
 * category, to resolve the "Radice > Categoria" path off the cached tree, and
 * the description on its own line (D-5). Typed here so the Offerte type stays
 * untouched (scope/out).
 */
export interface OfferCatalogProductMeta extends QuoteProductForSelectMeta {
  category_id: number | null
  description: string | null
  /** The resource already sends the typology's colour token; the Offerte type never read it. */
  product_typology: { id: number; name: string; color: string | null } | null
}

export interface OfferCatalogProduct extends ForSelectItem {
  meta: OfferCatalogProductMeta
}

/** A product as a row card shows it: what the form row itself (ids only) cannot tell. */
export interface OfferLineProductView {
  id: number
  name: string
  code: string | null
  categoryId: number | null
  typology: { name: string; color?: string | null } | null
}
