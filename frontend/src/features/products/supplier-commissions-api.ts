import { queryOptions, type QueryClient } from '@tanstack/react-query'
import { apiClient } from '@/api/client'
import type { ApiResponse } from '@/api/types'
import {
  defaultProductSupplierOf,
  productSuppliersOf,
  type ProductSupplierSource,
  type SupplierCommission,
} from '@/features/products/product-suppliers'

/**
 * Where to look a supplier's commission up (spec 0320 rev.3 D-22/D-23): a saved
 * product, or the category of a product still being created. The product wins
 * when both are known.
 */
export interface SupplierCommissionsParams {
  productId: number | null
  categoryId: number | null
  supplierIds: readonly number[]
}

export interface SupplierCommissionEntry {
  supplier_id: number
  commission: SupplierCommission | null
}

export type SupplierCommissionMap = ReadonlyMap<number, SupplierCommission | null>

const STALE_TIME_MS = 60_000

/** Sorted and de-duplicated, so the same suppliers in any order share one cache entry. */
function normalizedIds(ids: readonly number[]): number[] {
  return [...new Set(ids)].sort((a, b) => a - b)
}

export function canLookUpSupplierCommissions(params: SupplierCommissionsParams): boolean {
  return params.supplierIds.length > 0 && (params.productId !== null || params.categoryId !== null)
}

export function supplierCommissionsQueryKey(params: SupplierCommissionsParams) {
  return [
    'products',
    'supplier-commissions',
    params.productId !== null ? { productId: params.productId } : { categoryId: params.categoryId },
    normalizedIds(params.supplierIds),
  ] as const
}

async function fetchSupplierCommissions(params: SupplierCommissionsParams): Promise<SupplierCommissionEntry[]> {
  const { data } = await apiClient.get<ApiResponse<SupplierCommissionEntry[]>>('/products/supplier-commissions', {
    params: {
      ...(params.productId !== null ? { product_id: params.productId } : { category_id: params.categoryId }),
      supplier_ids: normalizedIds(params.supplierIds),
    },
  })
  return data.data
}

/** One query definition shared by the hook and the imperative prefetch, so both hit the same cache entry. */
export function supplierCommissionsQuery(params: SupplierCommissionsParams) {
  return queryOptions({
    queryKey: supplierCommissionsQueryKey(params),
    queryFn: () => fetchSupplierCommissions(params),
    enabled: canLookUpSupplierCommissions(params),
    staleTime: STALE_TIME_MS,
  })
}

export function commissionMapOf(entries: readonly SupplierCommissionEntry[] | undefined): SupplierCommissionMap {
  return new Map((entries ?? []).map((entry) => [entry.supplier_id, entry.commission]))
}

const NO_COMMISSIONS: SupplierCommissionMap = new Map()

/**
 * Pick-time lookup for a row about to be created: resolves through the same
 * cache as the hook. A failure yields "no commission": the server forces the
 * zero anyway, the client only anticipates it.
 */
export async function loadSupplierCommissions(
  queryClient: QueryClient,
  params: SupplierCommissionsParams,
): Promise<SupplierCommissionMap> {
  if (!canLookUpSupplierCommissions(params)) {
    return NO_COMMISSIONS
  }
  try {
    return commissionMapOf(await queryClient.fetchQuery(supplierCommissionsQuery(params)))
  } catch {
    return NO_COMMISSIONS
  }
}

/** A picked product as the for-select hands it: its id and the `meta` carrying `suppliers`. */
export interface PickedProduct {
  id: number
  meta: ProductSupplierSource
}

/**
 * Ids of the picked products whose default supplier earns a commission: the
 * cost row they open starts at price 0 (spec 0320 rev.3 D-23).
 */
export async function commissionedDefaultProductIds(
  queryClient: QueryClient,
  items: readonly PickedProduct[],
): Promise<Set<number>> {
  const checks = await Promise.all(
    items.map(async (item) => {
      const supplier = defaultProductSupplierOf(item.meta)
      if (supplier === null) {
        return null
      }
      const commissions = await loadSupplierCommissions(queryClient, {
        productId: item.id,
        categoryId: null,
        supplierIds: productSuppliersOf(item.meta).map((candidate) => candidate.supplier_id),
      })
      return commissions.get(supplier.supplier_id) ? item.id : null
    }),
  )
  return new Set(checks.flatMap((id) => (id === null ? [] : [id])))
}
