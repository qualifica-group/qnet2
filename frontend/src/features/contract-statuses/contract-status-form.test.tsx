import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ContractStatusForm } from '@/features/contract-statuses/contract-status-form'
import type { ContractStatusDetailWithPermissions } from '@/features/contract-statuses/types'
import type { ResourcePermissions } from '@/features/authorization/types'

/**
 * The form is create-only, a replica of the detail with its rows closed
 * (spec 0235); the in-place edits live in `contract-status-detail.test.tsx`.
 */

const createContractStatusMock = vi.fn()

vi.mock('@/features/contract-statuses/api', () => ({
  createContractStatus: (...args: unknown[]) => createContractStatusMock(...args),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn() } }))

/** Every field resolves as visible+editable (the `MetaField` fallback, since `fields` is empty). */
const FULL_ACCESS_PERMISSIONS: ResourcePermissions = {
  resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
  fields: {},
  actions: {},
}

vi.mock('@/features/authorization/use-resource-meta', () => ({
  useResourceMeta: () => ({ data: { permissions: FULL_ACCESS_PERMISSIONS }, isError: false, refetch: vi.fn() }),
}))

/** A QueryClient per test, never per render: a shared cache makes suites flaky. */
function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  )
}

/** Opens a closed row through its pencil. */
function openRow(key: string) {
  fireEvent.click(screen.getByRole('button', { name: i18n.t('common.inlineEdit.edit', { field: i18n.t(key) }) }))
}

/** Picks a token from the `ColorTokenPicker` popover by its localized name. */
function pickColor(name: string) {
  openRow('contractStatuses.form.color')
  fireEvent.click(screen.getByRole('button', { name: /choose a color/i }))
  fireEvent.click(screen.getByRole('option', { name }))
}

const save = () => fireEvent.click(screen.getAllByRole('button', { name: 'Save' })[0])

function contractStatus(): ContractStatusDetailWithPermissions {
  return {
    id: 7,
    name: 'Active',
    description: 'Contract in force',
    color: 'blue',
    sort_order: 3,
    is_active: true,
    is_default: false,
    system_key: null,
    group: 'open',
    created_at: '2026-01-01T09:00:00Z',
    updated_at: '2026-02-15T14:30:00Z',
    permissions: FULL_ACCESS_PERMISSIONS,
  }
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  createContractStatusMock.mockReset()
})

describe('ContractStatusForm — create', () => {
  it('starts with every row closed, with no order input', () => {
    render(<ContractStatusForm onSuccess={vi.fn()} onCancel={vi.fn()} />, { wrapper: wrapper() })

    expect(screen.queryByRole('textbox')).not.toBeInTheDocument()
    openRow('contractStatuses.form.name')
    expect(screen.getByLabelText(/^Name/)).toBeInTheDocument()
    expect(screen.queryByLabelText(/^Order/)).not.toBeInTheDocument()
  })

  it('shows the errors on the closed rows and does not call the API when name and color are missing', async () => {
    render(<ContractStatusForm onSuccess={vi.fn()} onCancel={vi.fn()} />, { wrapper: wrapper() })

    save()

    expect(await screen.findByText('Name is required.')).toHaveAttribute('role', 'alert')
    expect(createContractStatusMock).not.toHaveBeenCalled()
  })

  it('submits the create payload on save, without sort_order', async () => {
    createContractStatusMock.mockResolvedValue(contractStatus())
    const onSuccess = vi.fn()

    render(<ContractStatusForm onSuccess={onSuccess} onCancel={vi.fn()} />, { wrapper: wrapper() })

    openRow('contractStatuses.form.name')
    fireEvent.change(screen.getByLabelText(/^Name/), { target: { value: 'Active' } })
    pickColor('Green')
    save()

    await waitFor(() => expect(createContractStatusMock).toHaveBeenCalledTimes(1))
    expect(createContractStatusMock).toHaveBeenCalledWith({ name: 'Active', description: null, color: 'green', group: 'open', is_active: true, is_default: false })
    await waitFor(() => expect(onSuccess).toHaveBeenCalledWith(contractStatus()))
  })

  it('BR-5: blocks the submit and shows the error on the closed row when marking default while inactive', async () => {
    render(<ContractStatusForm onSuccess={vi.fn()} onCancel={vi.fn()} />, { wrapper: wrapper() })

    openRow('contractStatuses.form.name')
    fireEvent.change(screen.getByLabelText(/^Name/), { target: { value: 'Active' } })
    openRow('contractStatuses.form.isDefault')
    fireEvent.click(screen.getByRole('switch', { name: /Default/ }))
    openRow('contractStatuses.form.isActive')
    fireEvent.click(screen.getByRole('switch', { name: /Active/ }))
    save()

    expect(await screen.findByText(i18n.t('contractStatuses.form.defaultRequiresActive'))).toBeInTheDocument()
    expect(createContractStatusMock).not.toHaveBeenCalled()
  })
})
