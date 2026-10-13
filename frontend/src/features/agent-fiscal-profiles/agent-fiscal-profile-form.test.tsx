import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { AxiosError } from 'axios'
import { useController, type Control, type FieldValues } from 'react-hook-form'
import i18n from '@/i18n'
import { AgentFiscalProfileForm } from '@/features/agent-fiscal-profiles/agent-fiscal-profile-form'
import type { AgentFiscalProfileDetailWithPermissions } from '@/features/agent-fiscal-profiles/types'
import type { ResourcePermissions } from '@/features/authorization/types'

const apiMocks = vi.hoisted(() => ({ create: vi.fn(), update: vi.fn() }))

vi.mock('@/features/agent-fiscal-profiles/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/agent-fiscal-profiles/api')>()),
  createAgentFiscalProfile: (...args: unknown[]) => apiMocks.create(...args),
  updateAgentFiscalProfile: (...args: unknown[]) => apiMocks.update(...args),
}))

vi.mock('@/components/form/relation-select-field', () => ({
  RelationSelectField: ({ control, name, label }: { control: Control<FieldValues>; name: string; label: string }) => {
    const { field } = useController({ control, name })
    return (
      <button type="button" onClick={() => field.onChange(5)}>
        {label === 'Agent' ? 'Pick referent' : label}
      </button>
    )
  },
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn() } }))

vi.mock('@/features/for-select/api', () => ({
  FOR_SELECT_PAGE_SIZE: 25,
  fetchForSelect: () => Promise.resolve({ data: [], meta: { has_more: false } }),
}))

vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: () => false, hasRole: () => false, roles: [], isLoading: false }),
}))

const PERMISSIONS: ResourcePermissions = {
  resource: { view: true, create: true, update: true, delete: true, export: true, import: false },
  fields: {},
  actions: {},
}

vi.mock('@/features/agent-fiscal-profiles/use-agent-fiscal-profile-form-meta', () => ({
  useAgentFiscalProfileFormMeta: () => ({ status: 'ready', permissions: PERMISSIONS }),
}))

function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  )
}

function profile(overrides: Partial<AgentFiscalProfileDetailWithPermissions> = {}): AgentFiscalProfileDetailWithPermissions {
  return {
    id: 9,
    referent_id: 5,
    referent: { id: 5, name: 'Mario Rossi' },
    withholding_enabled: true,
    withholding_rate: '23.00',
    withholding_base_percent: '50.00',
    enasarco_enabled: false,
    enasarco_mandate_type: null,
    vat_rate_id: null,
    vat_rate: null,
    valid_from: '2026-01-01',
    valid_until: null,
    notes: null,
    created_at: '2026-10-12T08:00:00Z',
    updated_at: '2026-10-12T08:00:00Z',
    permissions: PERMISSIONS,
    ...overrides,
  }
}

function unprocessable(errors: Record<string, string[]>) {
  return new AxiosError('Unprocessable', '422', undefined, undefined, {
    status: 422,
    data: { success: false, message: 'Validation failed', errors },
  } as never)
}

function renderEdit(onSuccess = vi.fn(), overrides: Partial<AgentFiscalProfileDetailWithPermissions> = {}) {
  return render(
    <AgentFiscalProfileForm mode={{ type: 'edit', profile: profile(overrides) }} onSuccess={onSuccess} onCancel={vi.fn()} />,
    { wrapper: wrapper() },
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  apiMocks.create.mockReset()
  apiMocks.update.mockReset()
})

describe('AgentFiscalProfileForm - conditional fields (spec 0212 AC-020)', () => {
  it('shows the rates while the withholding is on and hides them when switched off', () => {
    renderEdit()

    expect(screen.getByLabelText(/^Withholding rate/)).toHaveValue(23)
    expect(screen.getByLabelText(/^Taxable base/)).toHaveValue(50)

    fireEvent.click(screen.getByRole('switch', { name: /^Apply withholding tax/ }))

    expect(screen.queryByLabelText(/^Withholding rate/)).not.toBeInTheDocument()
    expect(screen.queryByLabelText(/^Taxable base/)).not.toBeInTheDocument()
  })

  it('shows the mandate type only while ENASARCO is on', () => {
    renderEdit()
    expect(screen.queryByRole('combobox', { name: /^Mandate type/ })).not.toBeInTheDocument()

    fireEvent.click(screen.getByRole('switch', { name: /^Apply ENASARCO/ }))

    expect(screen.getByRole('combobox', { name: /^Mandate type/ })).toBeInTheDocument()
  })
})

describe('AgentFiscalProfileForm - create', () => {
  it('does not call the API and flags the missing fields accessibly', async () => {
    render(<AgentFiscalProfileForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    const validFrom = await screen.findByLabelText(/^Valid from/)
    await waitFor(() => expect(validFrom).toHaveAttribute('aria-invalid', 'true'))
    const message = screen.getByText('The start date is required.')
    expect(message).toHaveAttribute('role', 'alert')
    expect(validFrom.getAttribute('aria-describedby')).toContain(message.id)
    expect(apiMocks.create).not.toHaveBeenCalled()
  })
})

describe('AgentFiscalProfileForm - edit', () => {
  it('submits the whole resource to the update endpoint with the profile id', async () => {
    apiMocks.update.mockResolvedValue(profile({ notes: 'ok' }))
    const onSuccess = vi.fn()
    renderEdit(onSuccess)

    fireEvent.change(screen.getByLabelText(/^Notes/), { target: { value: 'ok' } })
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(apiMocks.update).toHaveBeenCalledTimes(1))
    expect(apiMocks.update).toHaveBeenCalledWith(9, {
      referent_id: 5,
      withholding_enabled: true,
      withholding_rate: 23,
      withholding_base_percent: 50,
      enasarco_enabled: false,
      enasarco_mandate_type: null,
      vat_rate_id: null,
      valid_from: '2026-01-01',
      valid_until: null,
      notes: 'ok',
    })
    await waitFor(() => expect(onSuccess).toHaveBeenCalled())
  })

  it('shows the server overlapping-validity error on valid_from with the accessible error triad', async () => {
    const overlapping = 'The validity overlaps another profile of this agent.'
    apiMocks.update.mockRejectedValue(unprocessable({ valid_from: [overlapping] }))
    renderEdit()

    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    const message = await screen.findByText(overlapping)
    expect(message).toHaveAttribute('role', 'alert')
    const validFrom = screen.getByLabelText(/^Valid from/)
    expect(validFrom).toHaveAttribute('aria-invalid', 'true')
    expect(validFrom.getAttribute('aria-describedby')).toContain(message.id)
  })
})

describe('AgentFiscalProfileForm - create submit', () => {
  it('posts the payload to the create endpoint', async () => {
    apiMocks.create.mockResolvedValue(profile())
    const onSuccess = vi.fn()
    render(<AgentFiscalProfileForm mode={{ type: 'create' }} onSuccess={onSuccess} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    fireEvent.click(screen.getByRole('button', { name: 'Pick referent' }))
    fireEvent.change(screen.getByLabelText(/^Valid from/), { target: { value: '2026-02-01' } })
    fireEvent.change(screen.getByLabelText(/^Withholding rate/), { target: { value: '20' } })
    fireEvent.change(screen.getByLabelText(/^Taxable base/), { target: { value: '50' } })
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(apiMocks.create).toHaveBeenCalledTimes(1))
    expect(apiMocks.create).toHaveBeenCalledWith({
      referent_id: 5,
      withholding_enabled: true,
      withholding_rate: 20,
      withholding_base_percent: 50,
      enasarco_enabled: false,
      enasarco_mandate_type: null,
      vat_rate_id: null,
      valid_from: '2026-02-01',
      valid_until: null,
      notes: null,
    })
    expect(apiMocks.update).not.toHaveBeenCalled()
    await waitFor(() => expect(onSuccess).toHaveBeenCalled())
  })
})
