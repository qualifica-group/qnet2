import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { useController, type Control, type FieldValues } from 'react-hook-form'
import i18n from '@/i18n'
import { AgentFiscalProfileForm } from '@/features/agent-fiscal-profiles/agent-fiscal-profile-form'
import type { ResourcePermissions } from '@/features/authorization/types'

/**
 * Spec 0264 D-4: the form is create-only, a replica of the detail with its
 * rows closed; the in-place edits live in `agent-fiscal-profile-detail.test.tsx`.
 */

const createMock = vi.fn()
const useMeta = vi.hoisted(() => vi.fn())

vi.mock('@/features/agent-fiscal-profiles/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/agent-fiscal-profiles/api')>()),
  createAgentFiscalProfile: (...args: unknown[]) => createMock(...args),
}))
vi.mock('@/features/authorization/use-resource-meta', () => ({
  useResourceMeta: (...args: unknown[]) => useMeta(...args),
}))
vi.mock('@/components/form/relation-select-field', () => ({
  RelationSelectField: ({ control, name, label }: { control: Control<FieldValues>; name: string; label: string }) => {
    const { field } = useController({ control, name })
    return (
      <button type="button" onClick={() => field.onChange(5)}>
        {`Pick ${label}`}
      </button>
    )
  },
}))
// The picked ids resolve their closed-row names through the for-select hydration.
vi.mock('@/features/for-select/use-for-select', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/for-select/use-for-select')>()),
  useForSelectLabels: ({ ids }: { ids: number[] }) => new Map(ids.map((id) => [id, { id, label: `Name ${id}` }])),
}))
vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

const PERMISSIONS: ResourcePermissions = {
  resource: { view: true, create: true, update: true, delete: true, export: true, import: false },
  fields: {},
  actions: {},
}

const openRow = (label: string) =>
  fireEvent.click(screen.getByRole('button', { name: i18n.t('common.inlineEdit.edit', { field: label }) }))
const applyRow = () => fireEvent.click(screen.getByRole('button', { name: i18n.t('common.inlineEdit.apply') }))
const save = () => fireEvent.click(screen.getAllByRole('button', { name: 'Save' })[0])

function renderForm(onSuccess = vi.fn(), onCancel = vi.fn()) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(<AgentFiscalProfileForm onSuccess={onSuccess} onCancel={onCancel} />, {
    wrapper: ({ children }: { children: ReactNode }) => <QueryClientProvider client={client}>{children}</QueryClientProvider>,
  })
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  createMock.mockReset()
  useMeta.mockReturnValue({ data: { permissions: PERMISSIONS }, isError: false, refetch: vi.fn() })
})

describe('AgentFiscalProfileForm - create draft (spec 0264 D-4)', () => {
  it('starts with every row closed', () => {
    renderForm()

    expect(screen.queryByRole('textbox')).not.toBeInTheDocument()
    expect(screen.queryByRole('switch')).not.toBeInTheDocument()
    expect(screen.getByRole('heading', { name: 'New fiscal profile' })).toBeInTheDocument()
  })

  it('shows the rates only while the withholding switch of its row is on', () => {
    renderForm()

    openRow('Withholding')
    expect(screen.getByLabelText(/^Withholding rate/)).toBeInTheDocument()
    fireEvent.click(screen.getByRole('switch', { name: /^Apply withholding tax/ }))

    expect(screen.queryByLabelText(/^Withholding rate/)).not.toBeInTheDocument()
    expect(screen.queryByLabelText(/^Taxable base/)).not.toBeInTheDocument()
  })

  it('shows the errors on the closed rows and does not call the API when required data is missing', async () => {
    renderForm()

    save()

    expect(await screen.findByText('The start date is required.')).toHaveAttribute('role', 'alert')
    expect(screen.getByText('The agent is required.')).toBeInTheDocument()
    expect(createMock).not.toHaveBeenCalled()
  })

  it('posts the whole draft on Save', async () => {
    createMock.mockResolvedValue({ id: 9 })
    const onSuccess = vi.fn()
    renderForm(onSuccess)

    openRow('Agent')
    fireEvent.click(screen.getByRole('button', { name: 'Pick Agent' }))
    applyRow()
    expect(screen.getByRole('heading', { name: 'Name 5' })).toBeInTheDocument()
    openRow('Validity')
    fireEvent.change(screen.getByLabelText(/^Valid from/), { target: { value: '2026-02-01' } })
    applyRow()
    openRow('Withholding')
    fireEvent.change(screen.getByLabelText(/^Withholding rate/), { target: { value: '20' } })
    fireEvent.change(screen.getByLabelText(/^Taxable base/), { target: { value: '50' } })
    applyRow()
    save()

    await waitFor(() => expect(createMock).toHaveBeenCalledTimes(1))
    expect(createMock).toHaveBeenCalledWith({
      referent_id: 5,
      withholding_enabled: true,
      withholding_rate: 20,
      withholding_base_percent: 50,
      enasarco_enabled: false,
      enasarco_mandate_type: null,
      vat_rate_id: null,
      payment_method_id: null,
      cost_type_id: null,
      valid_from: '2026-02-01',
      valid_until: null,
      notes: null,
    })
    await waitFor(() => expect(onSuccess).toHaveBeenCalled())
  })

  it('Cancel leaves the form through onCancel', () => {
    const onCancel = vi.fn()
    renderForm(vi.fn(), onCancel)

    fireEvent.click(screen.getAllByRole('button', { name: 'Cancel' })[0])

    expect(onCancel).toHaveBeenCalled()
  })
})
