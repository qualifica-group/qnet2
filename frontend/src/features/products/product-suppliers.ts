/**
 * The Fornitore commission a supplier earns on a product (spec 0320 rev.3 D-21): the rule the
 * Configuratore would apply today. `value` is a decimal string (a percentage or an amount in euro).
 */
export interface SupplierCommission {
  configuration_id: number
  type: 'PERCENTAGE' | 'FIXED_AMOUNT'
  value: string
  direction: 'PAID' | 'RECEIVED'
}

/**
 * One supplier of a product with its own sale price and purchase cost (spec 0320 D-1/D-2, rev.2 D-14):
 * the shape of `ProductResource.suppliers[]` and of the for-select
 * `meta.suppliers[]`, default first then by name. `price`/`cost` are decimal strings.
 * `commission` (rev.3 D-21) rides the product detail only, never the for-select meta.
 */
export interface ProductSupplierOption {
  supplier_id: number
  name: string
  price: string
  cost: string
  is_default: boolean
  commission?: SupplierCommission | null
}

/** What the price/cost helpers read: a product's suppliers plus its standard price and cost (D-3, D-15). */
export interface ProductSupplierSource {
  suppliers?: ProductSupplierOption[] | null
  price?: string | number | null
  cost?: string | number | null
}

const NO_SUPPLIERS: ProductSupplierOption[] = []

export function productSuppliersOf(source: ProductSupplierSource | null | undefined): ProductSupplierOption[] {
  return source?.suppliers ?? NO_SUPPLIERS
}

export function hasProductSuppliers(source: ProductSupplierSource | null | undefined): boolean {
  return productSuppliersOf(source).length > 0
}

/** The supplier proposed on a new line (D-2); null when the product has none. */
export function defaultProductSupplierOf(source: ProductSupplierSource | null | undefined): ProductSupplierOption | null {
  const suppliers = productSuppliersOf(source)

  return suppliers.find((supplier) => supplier.is_default) ?? suppliers[0] ?? null
}

export function isProductSupplier(source: ProductSupplierSource | null | undefined, supplierId: number | null): boolean {
  return supplierId !== null && productSuppliersOf(source).some((supplier) => supplier.supplier_id === supplierId)
}

/** The two per-supplier amounts (rev.2 D-14). */
export type SupplierAmount = 'price' | 'cost'

/** Shared body of the two lookups: the standard amount without suppliers, else the chosen supplier's own. */
function amountFor(
  source: ProductSupplierSource | null | undefined,
  field: SupplierAmount,
  supplierId: number | null,
): number | null {
  if (!hasProductSuppliers(source)) {
    const standard = source?.[field]

    return standard === null || standard === undefined ? null : Number(standard)
  }

  const supplier = productSuppliersOf(source).find((candidate) => candidate.supplier_id === supplierId)

  return supplier ? Number(supplier[field]) : null
}

/**
 * The cost to precompile for (product, supplier) (D-4): the chosen supplier's
 * cost; with no suppliers at all the product's standard cost. A supplier that
 * is not one of the product's yields null: the caller keeps its own price.
 */
export function productCostFor(source: ProductSupplierSource | null | undefined, supplierId: number | null): number | null {
  return amountFor(source, 'cost', supplierId)
}

/** The sale price to precompile for (product, supplier) (rev.2 D-16): twin of `productCostFor`, over `price`. */
export function productPriceFor(source: ProductSupplierSource | null | undefined, supplierId: number | null): number | null {
  return amountFor(source, 'price', supplierId)
}
