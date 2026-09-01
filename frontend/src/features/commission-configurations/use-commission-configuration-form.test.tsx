import { act, renderHook } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { createCommissionConfiguration, updateCommissionConfiguration } from './api'
import { useCommissionConfigurationForm } from './use-commission-configuration-form'
import type { CommissionConfigurationDetailWithPermissions } from './types'

const applyErrors = vi.hoisted(() => vi.fn(() => false))
vi.mock('./api', () => ({
  createCommissionConfiguration: vi.fn(),
  updateCommissionConfiguration: vi.fn(),
}))
vi.mock('@/features/auth/form-errors', () => ({
  applyServerValidationErrors: applyErrors,
}))
vi.mock('sonner', () => ({ toast: { success: vi.fn() } }))

const saved = {
  id: 1,
  name: 'Rule',
  recipient_role: 'COMMERCIAL',
  application_scope: 'PRODUCT',
  product_category_id: null,
  product_category: null,
  product_id: 3,
  product: { id: 3, name: 'Product' },
  commission_type: 'PERCENTAGE',
  value: '5.0000',
  priority: 1,
  valid_from: '2026-07-29',
  valid_until: null,
  status: 'ACTIVE',
  internal_note: null,
  created_at: '2026-07-29T00:00:00Z',
  updated_at: '2026-07-29T00:00:00Z',
} as const

function wrapper() {
  const client = new QueryClient()
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  )
}

describe('useCommissionConfigurationForm', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    applyErrors.mockReturnValue(false)
  })

  it('creates a category rule and normalizes irrelevant/empty fields', async () => {
    vi.mocked(createCommissionConfiguration).mockResolvedValue(saved)
    const onSuccess = vi.fn()
    const { result } = renderHook(
      () => useCommissionConfigurationForm({ mode: { type: 'create' }, onSuccess }),
      { wrapper: wrapper() },
    )
    await act(() => result.current.onSubmit({
      name: 'Category rule',
      recipient_role: 'REPORTER',
      application_scope: 'PRODUCT_CATEGORY',
      product_category_id: 8,
      product_id: 99,
      recipient_id: 42,
      commission_type: 'FIXED_AMOUNT',
      value: 10,
      priority: 2,
      valid_from: '2026-07-29',
      valid_until: '',
      status: 'ACTIVE',
      internal_note: '',
    }))
    expect(createCommissionConfiguration).toHaveBeenCalledWith(expect.objectContaining({
      product_category_id: 8,
      product_id: null,
      recipient_id: 42,
      valid_until: null,
      internal_note: null,
    }))
    expect(onSuccess).toHaveBeenCalledWith(saved)
  })

  it('updates a product rule from authoritative edit defaults', async () => {
    vi.mocked(updateCommissionConfiguration).mockResolvedValue(saved)
    const configuration = {
      ...saved,
      permissions: { resource: {}, fields: {}, actions: {} },
    } as CommissionConfigurationDetailWithPermissions
    const { result } = renderHook(
      () => useCommissionConfigurationForm({
        mode: { type: 'edit', configuration },
        onSuccess: vi.fn(),
      }),
      { wrapper: wrapper() },
    )
    expect(result.current.form.getValues('value')).toBe(5)
    await act(() => result.current.onSubmit({
      ...result.current.form.getValues(),
      product_category_id: 20,
    }))
    expect(updateCommissionConfiguration).toHaveBeenCalledWith(1, expect.objectContaining({
      product_category_id: null,
      product_id: 3,
    }))
  })

  it('shows a generic error only when server validation did not map the failure', async () => {
    vi.mocked(createCommissionConfiguration).mockRejectedValue(new Error('network'))
    const { result } = renderHook(
      () => useCommissionConfigurationForm({ mode: { type: 'create' }, onSuccess: vi.fn() }),
      { wrapper: wrapper() },
    )
    await act(() => result.current.onSubmit(result.current.form.getValues()))
    expect(result.current.serverError).toBeTruthy()

    applyErrors.mockReturnValue(true)
    await act(() => result.current.onSubmit(result.current.form.getValues()))
    expect(applyErrors).toHaveBeenCalled()
  })
})
