import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { CostTypeForm } from '@/features/cost-types/cost-type-form'
import type { CostTypeDetail } from '@/features/cost-types/types'
import type { ResourcePermissions } from '@/features/authorization/types'

/**
 * Spec 0249 AC-006: the create form is a replica of the detail (closed rows,
 * Save creates everything at once) with nature and color and no order field.
 */

const createCostTypeMock = vi.fn()

vi.mock('@/features/cost-types/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/cost-types/api')>()),
  createCostType: (...args: unknown[]) => createCostTypeMock(...args),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn() } }))

const PERMISSIONS: ResourcePermissions = {
  resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
  fields: {},
  actions: {},
}

vi.mock('@/features/authorization/use-resource-meta', () => ({
  useResourceMeta: () => ({ data: { permissions: PERMISSIONS }, isError: false, refetch: vi.fn() }),
}))

/** A QueryClient per test, never per render: a shared cache makes suites flaky. */
function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => <QueryClientProvider client={client}>{children}</QueryClientProvider>
}

function created(overrides: Partial<CostTypeDetail> = {}): CostTypeDetail {
  return {
    id: 9,
    name: 'Fitti',
    description: null,
    nature: 'indirect',
    color: 'emerald',
    sort_order: 5,
    is_active: true,
    created_at: '2026-10-12T08:00:00Z',
    updated_at: '2026-10-12T08:00:00Z',
    abilities: { update: true, delete: true },
    ...overrides,
  }
}

/** Anchors a translated label so a required field's trailing marker does not break the match. */
function labelFor(key: string): RegExp {
  return new RegExp(`^${i18n.t(key).replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}`)
}

function openRow(key: string) {
  fireEvent.click(screen.getByRole('button', { name: i18n.t('common.inlineEdit.edit', { field: i18n.t(key) }) }))
}

const save = () => fireEvent.click(screen.getAllByRole('button', { name: i18n.t('costTypes.form.save') })[0])

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  createCostTypeMock.mockReset()
})

describe('CostTypeForm - create (spec 0249)', () => {
  it('lists name, description, nature, color and active rows, with no order field', () => {
    render(<CostTypeForm onSuccess={vi.fn()} onCancel={vi.fn()} />, { wrapper: wrapper() })

    for (const key of ['name', 'description', 'nature', 'color', 'isActive']) {
      expect(
        screen.getByRole('button', { name: i18n.t('common.inlineEdit.edit', { field: i18n.t(`costTypes.form.${key}`) }) }),
      ).toBeInTheDocument()
    }
    expect(screen.queryByText('Order')).not.toBeInTheDocument()
    expect(screen.getByText('Direct')).toBeInTheDocument()
  })

  it('shows the error on the closed row and does not call the API when name is empty', async () => {
    render(<CostTypeForm onSuccess={vi.fn()} onCancel={vi.fn()} />, { wrapper: wrapper() })

    save()

    expect(await screen.findByText('Name is required.')).toHaveAttribute('role', 'alert')

    openRow('costTypes.form.name')
    expect(screen.getByLabelText(labelFor('costTypes.form.name'))).toHaveAttribute('aria-invalid', 'true')
    expect(createCostTypeMock).not.toHaveBeenCalled()
  })

  it('submits name, nature and color, never sort_order', async () => {
    createCostTypeMock.mockResolvedValue(created())
    const onSuccess = vi.fn()

    render(<CostTypeForm onSuccess={onSuccess} onCancel={vi.fn()} />, { wrapper: wrapper() })

    openRow('costTypes.form.name')
    fireEvent.change(screen.getByLabelText(labelFor('costTypes.form.name')), { target: { value: 'Fitti' } })
    openRow('costTypes.form.nature')
    fireEvent.click(screen.getByRole('combobox'))
    fireEvent.click(screen.getByRole('option', { name: 'Indirect' }))
    openRow('costTypes.form.color')
    fireEvent.click(screen.getByRole('button', { name: i18n.t('customFields.colors.slate') }))
    fireEvent.click(screen.getByRole('option', { name: i18n.t('customFields.colors.emerald') }))
    save()

    await waitFor(() => expect(createCostTypeMock).toHaveBeenCalledTimes(1))
    expect(createCostTypeMock).toHaveBeenCalledWith({
      name: 'Fitti',
      description: null,
      nature: 'indirect',
      color: 'emerald',
      is_active: true,
    })
    await waitFor(() => expect(onSuccess).toHaveBeenCalledWith(created()))
  })
})
