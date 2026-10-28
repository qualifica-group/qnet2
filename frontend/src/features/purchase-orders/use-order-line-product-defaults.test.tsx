import { beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { renderHook, waitFor } from '@testing-library/react'
import { useForm } from 'react-hook-form'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import type { PurchaseOrderFormValues } from '@/features/purchase-orders/purchase-order-schema'
import { useOrderLineProductDefaults } from '@/features/purchase-orders/use-order-line-product-defaults'
import type { ProductDetailWithPermissions } from '@/features/products/types'

/** Spec 0251 AC-009: an order line takes the product's default cost type only while it has none. */

const fetchProductMock = vi.fn<(id: number) => Promise<ProductDetailWithPermissions>>()
vi.mock('@/features/products/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/products/api')>()),
  fetchProduct: (id: number) => fetchProductMock(id),
}))
vi.mock('sonner', () => ({ toast: { error: vi.fn() } }))

function product(costTypeId: number | null): ProductDetailWithPermissions {
  return {
    id: 9,
    name: 'Fuel card',
    cost: null,
    vat_rate_id: null,
    vat_rate: null,
    unit_of_measure_id: 1,
    cost_type_id: costTypeId,
  } as ProductDetailWithPermissions
}

function setup(currentCostTypeId: number | null, orderSupplierId: number | null = null) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  const wrapper = ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  )
  return renderHook(
    () => {
      const form = useForm<PurchaseOrderFormValues>({
        defaultValues: { lines: [{ cost_type_id: currentCostTypeId }] } as unknown as PurchaseOrderFormValues,
      })
      const apply = useOrderLineProductDefaults(0, form.setValue, currentCostTypeId, orderSupplierId)
      return { form, apply }
    },
    { wrapper },
  )
}

beforeEach(() => {
  fetchProductMock.mockReset()
})

describe('useOrderLineProductDefaults - cost type (spec 0251 AC-009)', () => {
  it('fills the cost type from the product when the line has none', async () => {
    fetchProductMock.mockResolvedValue(product(6))
    const { result } = setup(null)

    await result.current.apply(9)

    await waitFor(() => expect(result.current.form.getValues('lines.0.cost_type_id')).toBe(6))
  })

  it('keeps the cost type already on the line', async () => {
    fetchProductMock.mockResolvedValue(product(6))
    const { result } = setup(3)

    await result.current.apply(9)

    await waitFor(() => expect(result.current.form.getValues('lines.0.description')).toBe('Fuel card'))
    expect(result.current.form.getValues('lines.0.cost_type_id')).toBe(3)
  })

  it('leaves the line empty when the product has no default', async () => {
    fetchProductMock.mockResolvedValue(product(null))
    const { result } = setup(null)

    await result.current.apply(9)

    await waitFor(() => expect(result.current.form.getValues('lines.0.description')).toBe('Fuel card'))
    expect(result.current.form.getValues('lines.0.cost_type_id')).toBeNull()
  })
})

const SUPPLIERS = [
  { supplier_id: 2, name: 'Beta', price: '112.50', cost: '12.50', is_default: true },
  { supplier_id: 1, name: 'Alfa', price: '110.00', cost: '10.00', is_default: false },
]

function productWithSuppliers(): ProductDetailWithPermissions {
  return { ...product(null), cost: '5.00', suppliers: SUPPLIERS } as ProductDetailWithPermissions
}

describe('useOrderLineProductDefaults - supplier cost (spec 0320 AC-016)', () => {
  it('prices the line with the cost of the order supplier when it supplies the product', async () => {
    fetchProductMock.mockResolvedValue(productWithSuppliers())
    const { result } = setup(null, 1)

    await result.current.apply(9)

    await waitFor(() => expect(result.current.form.getValues('lines.0.unit_price')).toBe(10))
  })

  it('falls back to the default supplier cost for another order supplier', async () => {
    fetchProductMock.mockResolvedValue(productWithSuppliers())
    const { result } = setup(null, 99)

    await result.current.apply(9)

    await waitFor(() => expect(result.current.form.getValues('lines.0.unit_price')).toBe(12.5))
  })

  it('uses the standard cost when the product has no suppliers', async () => {
    fetchProductMock.mockResolvedValue({ ...product(null), cost: '5.00', suppliers: [] } as ProductDetailWithPermissions)
    const { result } = setup(null, 1)

    await result.current.apply(9)

    await waitFor(() => expect(result.current.form.getValues('lines.0.unit_price')).toBe(5))
  })
})
