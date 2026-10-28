import { useMemo } from 'react'
import { useQuery } from '@tanstack/react-query'
import {
  commissionMapOf,
  supplierCommissionsQuery,
  type SupplierCommissionsParams,
} from '@/features/products/supplier-commissions-api'
import type { SupplierCommission } from '@/features/products/product-suppliers'

/**
 * The Fornitore commissions of a product's suppliers (spec 0320 rev.3 D-22/D-23),
 * one cached request per product (or per category while creating), shared by
 * every row and field that asks. Nothing is requested without suppliers or
 * without a product/category to look them up on.
 */
export function useSupplierCommissions(params: SupplierCommissionsParams) {
  const query = useQuery(supplierCommissionsQuery(params))
  const commissions = useMemo(() => commissionMapOf(query.data), [query.data])

  return {
    /** The commission of that supplier, or null (none, still loading, or no supplier). */
    commissionOf: (supplierId: number | null): SupplierCommission | null =>
      supplierId === null ? null : (commissions.get(supplierId) ?? null),
  }
}
