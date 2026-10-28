import { beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { renderHook, waitFor } from '@testing-library/react'
import { useForm } from 'react-hook-form'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import type { PurchaseRequestFormValues } from '@/features/purchase-requests/purchase-request-schema'
import { useLineProductDefaults, useLineSupplierCost } from '@/features/purchase-requests/use-line-product-defaults'
import type { ProductDetailWithPermissions } from '@/features/products/types'

/** Spec 0320 AC-016: an RDA line proposes the product's default supplier and re-prices from a product supplier. */

const fetchProductMock = vi.fn<(id: number) => Promise<ProductDetailWithPermissions>>()
vi.mock('@/features/products/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/products/api')>()),
  fetchProduct: (id: number) => fetchProductMock(id),
}))
vi.mock('sonner', () => ({ toast: { error: vi.fn() } }))

const SUPPLIERS = [
  { supplier_id: 2, name: 'Beta', price: '112.50', cost: '12.50', is_default: true },
  { supplier_id: 1, name: 'Alfa', price: '110.00', cost: '10.00', is_default: false },
]

function product(suppliers: typeof SUPPLIERS): ProductDetailWithPermissions {
  return {
    id: 9,
    name: 'Toner',
    price: '20.00',
    cost: '5.00',
    suppliers,
    vat_rate_id: null,
    vat_rate: null,
    unit_of_measure_id: 1,
  } as unknown as ProductDetailWithPermissions
}

function setup() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  const wrapper = ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  )
  return renderHook(
    () => {
      const form = useForm<PurchaseRequestFormValues>({
        defaultValues: { lines: [{ unit_price: 0, supplier_id: null, supplier_ref: null }] } as unknown as PurchaseRequestFormValues,
      })
      const applyProduct = useLineProductDefaults(0, form.setValue)
      const applySupplierCost = useLineSupplierCost(0, form.setValue)
      return { form, applyProduct, applySupplierCost }
    },
    { wrapper },
  )
}

beforeEach(() => {
  fetchProductMock.mockReset()
})

describe('useLineProductDefaults - suppliers (spec 0320 D-10)', () => {
  it('proposes the default supplier and its cost', async () => {
    fetchProductMock.mockResolvedValue(product(SUPPLIERS))
    const { result } = setup()

    await result.current.applyProduct(9)

    await waitFor(() => expect(result.current.form.getValues('lines.0.supplier_id')).toBe(2))
    expect(result.current.form.getValues('lines.0.unit_price')).toBe(12.5)
    expect(result.current.form.getValues('lines.0.supplier_ref')).toEqual({ id: 2, name: 'Beta' })
  })

  it('keeps the product price and no supplier without suppliers', async () => {
    fetchProductMock.mockResolvedValue(product([]))
    const { result } = setup()

    await result.current.applyProduct(9)

    await waitFor(() => expect(result.current.form.getValues('lines.0.unit_price')).toBe(20))
    expect(result.current.form.getValues('lines.0.supplier_id')).toBeNull()
  })
})

describe('useLineSupplierCost (spec 0320 D-10)', () => {
  it('re-prices from a supplier of the product', async () => {
    fetchProductMock.mockResolvedValue(product(SUPPLIERS))
    const { result } = setup()

    await result.current.applySupplierCost(9, 1)

    await waitFor(() => expect(result.current.form.getValues('lines.0.unit_price')).toBe(10))
  })

  it('leaves the price untouched for an external supplier', async () => {
    fetchProductMock.mockResolvedValue(product(SUPPLIERS))
    const { result } = setup()

    await result.current.applySupplierCost(9, 99)

    await waitFor(() => expect(fetchProductMock).toHaveBeenCalled())
    expect(result.current.form.getValues('lines.0.unit_price')).toBe(0)
  })
})
