import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { act, renderHook, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import type { ReactNode } from 'react'
import i18n from '@/i18n'
import { fetchWorkOrderFormContext, updateWorkOrder } from '@/features/work-orders/api'
import { useWorkOrderForm } from '@/features/work-orders/use-work-order-form'
import type { ApplicableAttributeSummary, WorkOrderDetailWithPermissions } from '@/features/work-orders/types'

vi.mock('@/features/work-orders/api', () => ({
  createWorkOrder: vi.fn(),
  updateWorkOrder: vi.fn(),
  fetchWorkOrderFormContext: vi.fn().mockResolvedValue({ applicable_attributes: [], attribute_layout: null }),
  workOrderDetailQueryKey: (id: number) => ['work-orders', 'detail', id],
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

const SITE_ACCESS: ApplicableAttributeSummary = {
  id: 1,
  code: 'site_access',
  name: 'Site access',
  type: 'text',
  description: null,
  help_text: null,
  placeholder: null,
  icon: null,
  config: null,
  relation_target: null,
  is_required: false,
  sort_order: 0,
  options: [],
}

function persistedWorkOrder(overrides: Partial<WorkOrderDetailWithPermissions> = {}): WorkOrderDetailWithPermissions {
  return {
    id: 9,
    code: 'COM-0001',
    title: 'Installazione impianto',
    type: 'processing',
    status: { value: 'open', is_force_closed: false },
    completion_percentage: 0,
    is_force_closed: false,
    force_close_reason: null,
    open_tasks_count: 0,
    callback_date: null,
    start_date: '2026-03-01',
    supervisors: [{ id: 21, name: 'Ada Alberti' }],
    participants: [],
    description: null,
    internal_notes: null,
    contract_number: 'QUO-0004',
    quote: { id: 4, code: 'QUO-0004', title: 'Fornitura annuale' },
    contract: null,
    task_template: null,
    quote_lines: [{ id: 11, sort_order: 1, product: { id: 1, code: 'PRD-0001', name: 'Consulenza' } }],
    applicable_attributes: [SITE_ACCESS],
    attribute_layout: null,
    // An empty PHP map arrives as `[]`: the defaults must still seed every applicable code.
    attribute_values: [] as unknown as Record<string, never>,
    created_at: '2026-01-01T00:00:00Z',
    updated_at: '2026-01-01T00:00:00Z',
    permissions: { resource: { view: true, create: true, update: true, delete: true, export: true, import: true }, fields: {}, actions: {} },
    ...overrides,
  }
}

beforeEach(() => {
  vi.mocked(updateWorkOrder).mockReset()
  vi.mocked(fetchWorkOrderFormContext).mockClear()
})

function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

describe('useWorkOrderForm — offer/lines coherence (AC-072)', () => {
  it('drops every selected product line when the linked offer changes', () => {
    const { result } = renderHook(
      () => useWorkOrderForm({ mode: { type: 'create' }, onSuccess: () => undefined }),
      { wrapper: wrapper() },
    )

    act(() => {
      result.current.form.setValue('quote_line_ids', [1, 2, 3])
    })
    expect(result.current.form.getValues('quote_line_ids')).toEqual([1, 2, 3])

    act(() => {
      result.current.handleQuoteChange()
    })

    expect(result.current.form.getValues('quote_line_ids')).toEqual([])
  })
})

describe('useWorkOrderForm — edit mode, the in-place detail (spec 0195 applied to Commesse)', () => {
  it('reads the persisted Attributes for the persisted lines, without resolving the form context', () => {
    const workOrder = persistedWorkOrder()
    const { result } = renderHook(
      () => useWorkOrderForm({ mode: { type: 'edit', workOrder }, onSuccess: () => undefined }),
      { wrapper: wrapper() },
    )

    expect(result.current.attributeContext.applicable_attributes).toEqual([SITE_ACCESS])
    expect(result.current.form.getValues('attribute_values')).toEqual({ site_access: null })
    expect(fetchWorkOrderFormContext).not.toHaveBeenCalled()
  })

  it('resolves the context again once the lines differ from the persisted ones', async () => {
    const workOrder = persistedWorkOrder()
    const { result } = renderHook(
      () => useWorkOrderForm({ mode: { type: 'edit', workOrder }, onSuccess: () => undefined }),
      { wrapper: wrapper() },
    )

    act(() => {
      result.current.form.setValue('quote_line_ids', [11, 12])
    })

    await waitFor(() => expect(fetchWorkOrderFormContext).toHaveBeenCalledWith([11, 12]))
  })

  it('PATCHes only the changed field and resets the form on the saved record', async () => {
    const workOrder = persistedWorkOrder()
    const saved = { ...workOrder, title: 'Nuovo titolo' }
    vi.mocked(updateWorkOrder).mockResolvedValue(saved)
    const onSuccess = vi.fn()
    const { result } = renderHook(
      () => useWorkOrderForm({ mode: { type: 'edit', workOrder }, onSuccess }),
      { wrapper: wrapper() },
    )

    act(() => {
      result.current.form.setValue('title', 'Nuovo titolo', { shouldDirty: true })
    })
    await act(async () => {
      await result.current.onSubmit(result.current.form.getValues())
    })

    expect(updateWorkOrder).toHaveBeenCalledWith(9, { title: 'Nuovo titolo' })
    expect(onSuccess).toHaveBeenCalledWith(saved)
    expect(result.current.form.formState.isDirty).toBe(false)
    expect(result.current.form.getValues('title')).toBe('Nuovo titolo')
  })
})
