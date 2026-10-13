import { act, renderHook, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { commissionConfigurationDetailKey, createCommissionConfiguration, updateCommissionConfiguration } from './api'
import { useCommissionConfigurationForm } from './use-commission-configuration-form'
import type { CommissionConfigurationDetailWithPermissions } from './types'

const applyErrors = vi.hoisted(() => vi.fn(() => false))
vi.mock('./api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('./api')>()),
  createCommissionConfiguration: vi.fn(),
  updateCommissionConfiguration: vi.fn(),
}))
vi.mock('@/features/auth/form-errors', () => ({
  applyServerValidationErrors: applyErrors,
}))
vi.mock('sonner', () => ({ toast: { success: vi.fn() } }))

const PERMISSIONS: CommissionConfigurationDetailWithPermissions['permissions'] = {
  resource: { view: true, create: true, update: true, delete: true, export: true, import: false },
  fields: {},
  actions: {},
}

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
      recipient_type: 'referent',
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

  it('updates through a diff payload and caches the saved record under the detail key', async () => {
    const configuration: CommissionConfigurationDetailWithPermissions = { ...saved, permissions: PERMISSIONS }
    const refreshed = { ...configuration, priority: 7 }
    vi.mocked(updateCommissionConfiguration).mockResolvedValue(refreshed)
    const client = new QueryClient()
    const onSuccess = vi.fn()
    const { result } = renderHook(
      () => useCommissionConfigurationForm({ mode: { type: 'edit', configuration }, onSuccess }),
      {
        wrapper: ({ children }: { children: ReactNode }) => (
          <QueryClientProvider client={client}>{children}</QueryClientProvider>
        ),
      },
    )
    expect(result.current.form.getValues('value')).toBe(5)

    await act(() => result.current.onSubmit({ ...result.current.form.getValues(), priority: 7 }))

    expect(updateCommissionConfiguration).toHaveBeenCalledWith(1, { priority: 7 })
    expect(client.getQueryData(commissionConfigurationDetailKey(1))).toEqual(refreshed)
    expect(onSuccess).toHaveBeenCalledWith(refreshed)
  })

  it('re-syncs the form on a newly saved record and clears the server error on demand', async () => {
    const configuration: CommissionConfigurationDetailWithPermissions = { ...saved, permissions: PERMISSIONS }
    vi.mocked(updateCommissionConfiguration).mockRejectedValue(new Error('network'))
    const { result, rerender } = renderHook(
      ({ record }) => useCommissionConfigurationForm({ mode: { type: 'edit', configuration: record }, onSuccess: vi.fn() }),
      { wrapper: wrapper(), initialProps: { record: configuration } },
    )

    await act(() => result.current.onSubmit(result.current.form.getValues()))
    expect(result.current.serverError).toBeTruthy()
    act(() => result.current.clearServerError())
    expect(result.current.serverError).toBeNull()

    rerender({ record: { ...configuration, name: 'Renamed elsewhere' } })
    await waitFor(() => expect(result.current.form.getValues('name')).toBe('Renamed elsewhere'))
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

  it('seeds a personal SUPPLIER rule on the registry a supplier tab opened it for (spec 0204)', () => {
    const { result } = renderHook(
      () => useCommissionConfigurationForm({ mode: { type: 'create', supplierRegistryId: 12 }, onSuccess: vi.fn() }),
      { wrapper: wrapper() },
    )
    expect(result.current.form.getValues()).toMatchObject({
      recipient_role: 'SUPPLIER',
      application_scope: 'RECIPIENT',
      recipient_type: 'registry',
      recipient_id: 12,
    })
  })

  it('keeps the role-wide COMMERCIAL default without a supplier', () => {
    const { result } = renderHook(
      () => useCommissionConfigurationForm({ mode: { type: 'create', supplierRegistryId: null }, onSuccess: vi.fn() }),
      { wrapper: wrapper() },
    )
    expect(result.current.form.getValues()).toMatchObject({
      recipient_role: 'COMMERCIAL',
      application_scope: 'PRODUCT_CATEGORY',
      recipient_type: 'referent',
      recipient_id: null,
    })
  })
})
