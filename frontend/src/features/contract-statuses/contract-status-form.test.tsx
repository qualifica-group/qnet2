import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { contractStatuses as contractStatusesEn } from '@/i18n/locales/en-contract-statuses'
import { ContractStatusForm } from '@/features/contract-statuses/contract-status-form'
import type { ContractStatusDetailWithPermissions } from '@/features/contract-statuses/types'
import type { ResourcePermissions } from '@/features/authorization/types'

const createContractStatusMock = vi.fn()
const updateContractStatusMock = vi.fn()

vi.mock('@/features/contract-statuses/api', () => ({
  createContractStatus: (...args: unknown[]) => createContractStatusMock(...args),
  updateContractStatus: (...args: unknown[]) => updateContractStatusMock(...args),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn() } }))

/**
 * Every field resolves as visible+editable (the `MetaField` fallback, since
 * `fields` is empty) — not about authorization metadata. The client-side
 * rules under test (system row, currently-default row, BR-5 cross-field
 * validation) are all independent of the resolved `ResourcePermissions`.
 */
const FULL_ACCESS_PERMISSIONS: ResourcePermissions = {
  resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
  fields: {},
  actions: {},
}

vi.mock('@/features/contract-statuses/use-contract-status-form-meta', () => ({
  useContractStatusFormMeta: () => ({ status: 'ready', permissions: FULL_ACCESS_PERMISSIONS }),
}))

function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  )
}

function contractStatus(
  overrides: Partial<ContractStatusDetailWithPermissions> = {},
): ContractStatusDetailWithPermissions {
  return {
    id: 9,
    name: 'Da programmare',
    description: null,
    color: 'blue',
    sort_order: 10,
    is_active: true,
    is_default: false,
    system_key: null,
    group: 'pending',
    created_at: null as unknown as string,
    updated_at: null as unknown as string,
    permissions: FULL_ACCESS_PERMISSIONS,
    ...overrides,
  }
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
  // `contract-statuses` is registered by another microtask (spec 0072 MT-09,
  // i18n wiring): register the real bundle directly on the shared instance
  // so this suite exercises the actual rendered copy rather than raw keys,
  // independent of that wiring's timing.
  i18n.addResourceBundle('en', 'translation', { contractStatuses: contractStatusesEn }, true, true)
})

beforeEach(() => {
  createContractStatusMock.mockReset()
  updateContractStatusMock.mockReset()
})

describe('ContractStatusForm — create/edit (spec 0072, AC-050)', () => {
  it('renders name, description, color, group, is_active and is_default', () => {
    render(
      <ContractStatusForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    expect(screen.getByLabelText(/^Name/)).toBeInTheDocument()
    expect(screen.getByLabelText(/^Description/)).toBeInTheDocument()
    expect(screen.getByRole('button', { name: /choose a color/i })).toBeInTheDocument()
    expect(screen.getByRole('combobox', { name: 'Group' })).toHaveTextContent('Open')
    expect(screen.getByRole('switch', { name: 'Active' })).toBeChecked()
    expect(screen.getByRole('switch', { name: 'Default' })).not.toBeChecked()
    expect(screen.queryByLabelText(/^Order/)).not.toBeInTheDocument()
  })

  it('submits the full create payload on save', async () => {
    createContractStatusMock.mockResolvedValue(contractStatus())
    const onSuccess = vi.fn()

    render(
      <ContractStatusForm mode={{ type: 'create' }} onSuccess={onSuccess} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    fireEvent.change(screen.getByLabelText(/^Name/), { target: { value: 'Da programmare' } })
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(createContractStatusMock).toHaveBeenCalledTimes(1))
    expect(createContractStatusMock).toHaveBeenCalledWith({
      name: 'Da programmare',
      description: null,
      color: null,
      group: 'open',
      is_active: true,
      is_default: false,
    })
    await waitFor(() => expect(onSuccess).toHaveBeenCalledWith(contractStatus()))
  })

  it('hydrates every field in edit mode', () => {
    render(
      <ContractStatusForm
        mode={{ type: 'edit', contractStatus: contractStatus({ description: 'Nota interna' }) }}
        onSuccess={vi.fn()}
        onCancel={vi.fn()}
      />,
      { wrapper: wrapper() },
    )

    expect(screen.getByLabelText(/^Name/)).toHaveValue('Da programmare')
    expect(screen.getByLabelText(/^Description/)).toHaveValue('Nota interna')
    expect(screen.getByRole('combobox', { name: 'Group' })).toHaveTextContent('Pending')
    expect(screen.getByRole('switch', { name: 'Active' })).toBeChecked()
    expect(screen.getByRole('switch', { name: 'Default' })).not.toBeChecked()
  })

  it('submits only the changed field on a partial update', async () => {
    updateContractStatusMock.mockResolvedValue(contractStatus({ name: 'Programmato' }))

    render(
      <ContractStatusForm
        mode={{ type: 'edit', contractStatus: contractStatus() }}
        onSuccess={vi.fn()}
        onCancel={vi.fn()}
      />,
      { wrapper: wrapper() },
    )

    fireEvent.change(screen.getByLabelText(/^Name/), { target: { value: 'Programmato' } })
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(updateContractStatusMock).toHaveBeenCalledTimes(1))
    const [id, payload] = updateContractStatusMock.mock.calls[0]
    expect(id).toBe(9)
    expect(payload).toEqual({ name: 'Programmato' })
  })
})

describe('ContractStatusForm — system row (D-2, BR-5 rule 1)', () => {
  it('disables description, group, is_active and is_default, while name/color stay editable and no hint leaks onto them', () => {
    render(
      <ContractStatusForm
        mode={{
          type: 'edit',
          contractStatus: contractStatus({ name: 'Sospeso', system_key: 'suspended', group: 'pending', is_default: false }),
        }}
        onSuccess={vi.fn()}
        onCancel={vi.fn()}
      />,
      { wrapper: wrapper() },
    )

    expect(screen.getByLabelText(/^Name/)).not.toBeDisabled()
    expect(screen.getByRole('button', { name: /Blue/ })).not.toBeDisabled()
    expect(screen.getByLabelText(/^Description/)).toBeDisabled()
    expect(screen.getByRole('combobox', { name: 'Group' })).toBeDisabled()
    expect(screen.getByRole('switch', { name: 'Active' })).toBeDisabled()
    expect(screen.getByRole('switch', { name: 'Default' })).toBeDisabled()
    expect(screen.getAllByRole('button', { name: 'More information' }).length).toBeGreaterThan(0)
  })
})

describe('ContractStatusForm — currently-default row (BR-5 rule 3)', () => {
  it('locks only is_default (not is_active/group) on a non-system default row, with a reassign hint', () => {
    render(
      <ContractStatusForm
        mode={{
          type: 'edit',
          contractStatus: contractStatus({ name: 'Da validare', system_key: null, is_default: true }),
        }}
        onSuccess={vi.fn()}
        onCancel={vi.fn()}
      />,
      { wrapper: wrapper() },
    )

    expect(screen.getByRole('switch', { name: 'Default' })).toBeDisabled()
    expect(screen.getByRole('switch', { name: 'Default' })).toBeChecked()
    expect(screen.getByRole('switch', { name: 'Active' })).not.toBeDisabled()
    expect(screen.getByRole('combobox', { name: 'Group' })).not.toBeDisabled()
    expect(screen.getByRole('button', { name: 'More information' })).toBeInTheDocument()
  })
})

describe('ContractStatusForm — BR-5 rule 2 (is_default requires is_active)', () => {
  it('blocks submission and shows a field error when marking default while inactive', async () => {
    render(
      <ContractStatusForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    fireEvent.change(screen.getByLabelText(/^Name/), { target: { value: 'Nuovo stato' } })
    fireEvent.click(screen.getByRole('switch', { name: 'Default' }))
    fireEvent.click(screen.getByRole('switch', { name: 'Active' }))
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() =>
      expect(screen.getByText('A default status must be active.')).toBeInTheDocument(),
    )
    expect(createContractStatusMock).not.toHaveBeenCalled()
  })
})
