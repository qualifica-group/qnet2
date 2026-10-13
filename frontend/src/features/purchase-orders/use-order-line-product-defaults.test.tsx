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

function setup(currentCostTypeId: number | null) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  const wrapper = ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  )
  return renderHook(
    () => {
      const form = useForm<PurchaseOrderFormValues>({
        defaultValues: { lines: [{ cost_type_id: currentCostTypeId }] } as unknown as PurchaseOrderFormValues,
      })
      const apply = useOrderLineProductDefaults(0, form.setValue, currentCostTypeId)
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
